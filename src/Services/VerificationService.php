<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailVerificationRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

final class VerificationService
{
    public const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private readonly EmailVerificationRepository $verificationRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly string $appUrl,
        private readonly string $appName
    ) {
    }

    /**
     * Generates a 24-hour verification token, stores its SHA-256 hash, and sends the email.
     *
     * @param array<string, mixed> $user
     * @return array{token: string, verify_url: string, email_sent: bool}
     */
    public function createAndSendForUser(array $user): array
    {
        $userId = (int) $user['id'];
        $email = (string) $user['email'];
        $contactName = (string) $user['contact_name'];

        $this->verificationRepository->invalidateUnusedForUser($userId);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new DateTimeImmutable('+' . self::TOKEN_TTL_HOURS . ' hours'))->format('Y-m-d H:i:s');

        $this->verificationRepository->create($userId, $tokenHash, $expiresAt);

        $verifyUrl = rtrim($this->appUrl, '/') . '/verify-email?token=' . urlencode($rawToken);
        $safeName = htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $safeApp = htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8');

        $subject = "Verify your email address — {$this->appName}";
        $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1c1917;">
  <h2 style="color: #065f46;">Welcome to {$safeApp}</h2>
  <p>Hi {$safeName},</p>
  <p>Please confirm your email address to activate your account and start placing orders:</p>
  <p style="margin: 24px 0;">
    <a href="{$safeUrl}" style="background-color: #047857; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Verify Email Address</a>
  </p>
  <p style="font-size: 13px; color: #57534e;">If the button does not work, copy and paste this link into your browser:<br><a href="{$safeUrl}" style="color: #047857; word-break: break-all;">{$safeUrl}</a></p>
  <p style="font-size: 13px; color: #57534e;">This link expires in 24 hours. If you did not create an account, you can safely ignore this email.</p>
</div>
HTML;
        $plain = "Welcome to {$this->appName}\n\nHi {$contactName},\n\nVerify your email address by opening:\n{$verifyUrl}\n\nThis link expires in 24 hours.";

        $sendResult = $this->emailService->send(
            $userId,
            $email,
            $contactName,
            'email_verification',
            $subject,
            $html,
            $plain
        );

        return [
            'token' => $rawToken,
            'verify_url' => $verifyUrl,
            'email_sent' => $sendResult['sent'],
            'email_error' => $sendResult['error'],
        ];
    }

    /**
     * Verifies a raw token and activates the user's email_verified_at timestamp.
     *
     * @return array{verified: bool, message: string, user_id?: int}
     */
    public function verifyToken(string $rawToken): array
    {
        $trimmed = trim($rawToken);
        if ($trimmed === '' || strlen($trimmed) < 32) {
            return ['verified' => false, 'message' => 'Invalid verification token.'];
        }

        $tokenHash = hash('sha256', $trimmed);
        $record = $this->verificationRepository->findByTokenHash($tokenHash);

        if ($record === null) {
            return ['verified' => false, 'message' => 'Verification link is invalid or has expired.'];
        }

        if ($record['used_at'] !== null) {
            return ['verified' => false, 'message' => 'This verification link has already been used.'];
        }

        $expiresAt = $record['expires_at'] instanceof \DateTimeInterface
            ? $record['expires_at']
            : new DateTimeImmutable((string) $record['expires_at']);

        if ($expiresAt < new DateTimeImmutable()) {
            return ['verified' => false, 'message' => 'This verification link has expired. Please request a new one.'];
        }

        $userId = (int) $record['user_id'];
        $this->verificationRepository->markUsed((int) $record['id']);
        $this->userRepository->markEmailVerified($userId);

        return [
            'verified' => true,
            'message' => 'Email verified successfully. You may now sign in.',
            'user_id' => $userId,
        ];
    }
}
