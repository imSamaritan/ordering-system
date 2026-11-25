<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailLogRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class EmailService
{
    /**
     * @param array<string, mixed> $smtpSettings
     */
    public function __construct(
        private readonly array $smtpSettings,
        private readonly EmailLogRepository $emailLogRepository,
        private readonly bool $dryRun = false
    ) {
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
        if ($this->dryRun) {
            $this->emailLogRepository->log(
                $userId,
                $recipientEmail,
                $emailType,
                $subject,
                'sent',
                null,
                $orderId
            );
            return ['sent' => true, 'error' => null];
        }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = (string) ($this->smtpSettings['host'] ?? '127.0.0.1');
            $mailer->Port = (int) ($this->smtpSettings['port'] ?? 1025);
            $mailer->Timeout = 5;
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;

            $user = (string) ($this->smtpSettings['user'] ?? '');
            $pass = (string) ($this->smtpSettings['pass'] ?? '');
            if ($user !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = $user;
                $mailer->Password = $pass;
            } else {
                $mailer->SMTPAuth = false;
            }

            $encryption = strtolower((string) ($this->smtpSettings['encryption'] ?? ''));
            if ($encryption === 'tls' || $encryption === 'starttls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            }

            $fromEmail = (string) ($this->smtpSettings['from_email'] ?? 'no-reply@ordering-system.local');
            $fromName = (string) ($this->smtpSettings['from_name'] ?? 'Produce & Inventory Ordering System');
            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($recipientEmail, $recipientName);

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $plainTextBody !== '' ? $plainTextBody : strip_tags($htmlBody);

            $mailer->send();

            $this->emailLogRepository->log(
                $userId,
                $recipientEmail,
                $emailType,
                $subject,
                'sent',
                null,
                $orderId
            );

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $this->emailLogRepository->log(
                $userId,
                $recipientEmail,
                $emailType,
                $subject,
                'failed',
                $error,
                $orderId
            );

            return ['sent' => false, 'error' => $error];
        }
    }
}
