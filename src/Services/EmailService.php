<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailLogRepository;
use Closure;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

final class EmailService
{
    /** @var Closure(): PHPMailer|null */
    private readonly ?Closure $mailerFactory;

    /**
     * @param array<string, mixed> $smtpSettings
     * @param (Closure(): PHPMailer)|null $mailerFactory
     */
    public function __construct(
        private readonly array $smtpSettings,
        private readonly EmailLogRepository $emailLogRepository,
        private readonly bool $dryRun = false,
        ?Closure $mailerFactory = null
    ) {
        $this->mailerFactory = $mailerFactory;
    }

    /**
     * Sends an HTML email via SMTP and logs the result to email_logs.
     *
     * @return array{sent: bool, error: ?string}
     */
    public function send(
        int $userId,
        string $recipientEmail,
        string $recipientName,
        string $emailType,
        string $subject,
        string $htmlBody,
        string $plainTextBody = '',
        ?int $orderId = null
    ): array {
        $recipientEmail = trim($recipientEmail);
        $recipientName = trim($recipientName);

        if ($recipientEmail === '' || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'Invalid recipient email address.';
            $this->safeLog($userId, $recipientEmail, $emailType, $subject, 'failed', $error, $orderId);
            return ['sent' => false, 'error' => $error];
        }

        if ($this->dryRun) {
            $this->safeLog($userId, $recipientEmail, $emailType, $subject, 'sent', null, $orderId);
            return ['sent' => true, 'error' => null];
        }

        $mailer = $this->mailerFactory !== null ? ($this->mailerFactory)() : new PHPMailer(true);
        /** @var list<string> $smtpTrace */
        $smtpTrace = [];

        try {
            $mailer->isSMTP();
            $mailer->Host = trim((string) ($this->smtpSettings['host'] ?? '127.0.0.1'));
            $mailer->Port = (int) ($this->smtpSettings['port'] ?? 1025);
            $mailer->Timeout = max(5, (int) ($this->smtpSettings['timeout'] ?? 15));
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $mailer->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $mailer->Debugoutput = static function (string $str, int $level) use (&$smtpTrace): void {
                $line = trim($str);
                if ($line === '') {
                    return;
                }
                // Never record raw auth credentials
                if (stripos($line, 'AUTH PLAIN ') !== false) {
                    $line = 'CLIENT -> SERVER: AUTH PLAIN [redacted]';
                }
                if (count($smtpTrace) < 25) {
                    $smtpTrace[] = $line;
                }
            };

            $user = trim((string) ($this->smtpSettings['user'] ?? ''));
            $pass = (string) ($this->smtpSettings['pass'] ?? '');
            if ($user !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = $user;
                $mailer->Password = $pass;
            } else {
                $mailer->SMTPAuth = false;
            }

            $encryption = strtolower(trim((string) ($this->smtpSettings['encryption'] ?? '')));
            if ($encryption === 'tls' || $encryption === 'starttls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->SMTPAutoTLS = true;
            } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            }

            $verifyPeer = array_key_exists('verify_peer', $this->smtpSettings)
                ? (bool) $this->smtpSettings['verify_peer']
                : true;
            if (!$verifyPeer) {
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $fromEmail = trim((string) ($this->smtpSettings['from_email'] ?? ''));
            if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
                $fromEmail = ($user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL) !== false)
                    ? $user
                    : 'no-reply@ordering-system.local';
            }
            $fromName = trim((string) ($this->smtpSettings['from_name'] ?? 'Produce & Inventory Ordering System'));

            $mailer->setFrom($fromEmail, $fromName, true);

            $replyTo = trim((string) ($this->smtpSettings['reply_to'] ?? ''));
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
                $mailer->addReplyTo($replyTo, $fromName);
            }

            $mailer->addAddress($recipientEmail, $recipientName);

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $plainTextBody !== '' ? $plainTextBody : strip_tags($htmlBody);

            $mailer->send();

            $this->safeLog($userId, $recipientEmail, $emailType, $subject, 'sent', null, $orderId);

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            $baseError = trim($e->getMessage());
            if ($baseError === '' && $mailer->ErrorInfo !== '') {
                $baseError = trim($mailer->ErrorInfo);
            }

            $error = $baseError;
            if ($smtpTrace !== []) {
                $tail = implode(' | ', array_slice($smtpTrace, -6));
                $error = mb_substr($baseError . ' [SMTP: ' . $tail . ']', 0, 2000);
            }

            error_log(sprintf(
                '[EmailService] Failed to send %s to %s via %s:%d: %s',
                $emailType,
                $recipientEmail,
                (string) ($this->smtpSettings['host'] ?? ''),
                (int) ($this->smtpSettings['port'] ?? 0),
                $error
            ));

            $this->safeLog($userId, $recipientEmail, $emailType, $subject, 'failed', $error, $orderId);

            return ['sent' => false, 'error' => $error];
        }
    }

    private function safeLog(
        int $userId,
        string $recipientEmail,
        string $emailType,
        string $subject,
        string $status,
        ?string $errorMessage,
        ?int $orderId
    ): void {
        try {
            $this->emailLogRepository->log(
                $userId,
                $recipientEmail,
                $emailType,
                $subject,
                $status,
                $errorMessage,
                $orderId
            );
        } catch (Throwable $e) {
            error_log('[EmailService] Failed to write email_logs entry: ' . $e->getMessage());
        }
    }
}
