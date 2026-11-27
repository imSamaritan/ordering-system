<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Support\Validator;
use Dibi\Connection;
use Throwable;

final class PasswordResetService
{
    public const TOKEN_TTL_MINUTES = 7;
    public const GENERIC_REQUEST_MESSAGE = 'If an account exists for that email address, a password reset link has been sent.';
    public const GENERIC_INVALID_TOKEN_MESSAGE = 'This password reset link is invalid or has expired.';

    public function __construct(
        private readonly Connection $db,
        private readonly PasswordResetRepository $passwordResetRepository,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly string $appUrl,
        private readonly string $appName,
        private readonly bool $appDebug = false
    ) {
    }

    /**
     * Requests a password reset link for the given email.
     * Always returns an identical 200 OK generic response to prevent user enumeration.
     *
     * @return array{ok: bool, status: int, message: string, debug_reset_url?: string}
     */
    public function requestReset(string $email): array
    {
        $this->passwordResetRepository->deleteExpired();

        $normalized = strtolower(trim($email));
        if (!Validator::isValidEmail($normalized) || mb_strlen($normalized) > 191) {
            return $this->genericRequestResponse();
        }

        $user = $this->userRepository->findByEmail($normalized);
        if ($user === null || (int) ($user['is_active'] ?? 0) !== 1) {
            return $this->genericRequestResponse();
        }

        $userId = (int) $user['id'];
        $contactName = (string) $user['contact_name'];
        $userEmail = (string) $user['email'];

        $this->passwordResetRepository->deleteForUser($userId);

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $this->passwordResetRepository->create($userId, $hashedToken, self::TOKEN_TTL_MINUTES);

        $resetUrl = rtrim($this->appUrl, '/') . '/reset-password?token=' . urlencode($rawToken);
        $safeName = htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $safeApp = htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8');
        $ttl = self::TOKEN_TTL_MINUTES;

        $subject = "Reset your password — {$this->appName}";
        $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1c1917;">
  <h2 style="color: #065f46;">Password Reset Request — {$safeApp}</h2>
  <p>Hi {$safeName},</p>
  <p>We received a request to reset the password for your account. Click the button below to choose a new password:</p>
  <p style="margin: 24px 0;">
    <a href="{$safeUrl}" style="background-color: #047857; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Reset Password</a>
  </p>
  <p style="font-size: 13px; color: #57534e;">If the button does not work, copy and paste this link into your browser:<br><a href="{$safeUrl}" style="color: #047857; word-break: break-all;">{$safeUrl}</a></p>
  <p style="font-size: 13px; color: #57534e;">This link expires in {$ttl} minutes. If you did not request a password reset, you can safely ignore this email.</p>
</div>
HTML;
        $plain = "Password Reset Request — {$this->appName}\n\nHi {$contactName},\n\nReset your password by opening:\n{$resetUrl}\n\nThis link expires in {$ttl} minutes. If you did not request a password reset, you can safely ignore this email.";

        $this->emailService->send(
            $userId,
            $userEmail,
            $contactName,
            'password_reset',
            $subject,
            $html,
            $plain
        );

        $response = $this->genericRequestResponse();
        if ($this->appDebug) {
            $response['debug_reset_url'] = $resetUrl;
        }

        return $response;
    }

    /**
     * Checks in MySQL whether the raw reset token is currently valid (and cleans up expired tokens).
     */
    public function isTokenValid(string $rawToken): bool
    {
        $trimmed = trim($rawToken);
        $this->passwordResetRepository->deleteExpired();

        if ($trimmed === '' || strlen($trimmed) < 32) {
            return false;
        }

        $hashedToken = hash('sha256', $trimmed);

        return $this->passwordResetRepository->isTokenValid($hashedToken);
    }

    /**
     * Validates token and new password, updates the user's password inside a transaction,
     * and deletes the user's rows from password_resets.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, status: int, message: string, errors?: array<string, string>}
     */
    public function resetPassword(array $input): array
    {
        $errors = Validator::validatePasswordReset($input);
        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $errors,
            ];
        }

        $rawToken = trim((string) ($input['token'] ?? ''));
        if (strlen($rawToken) < 32) {
            $this->passwordResetRepository->deleteExpired();
            return [
                'ok' => false,
                'status' => 400,
                'message' => self::GENERIC_INVALID_TOKEN_MESSAGE,
            ];
        }

        $hashedToken = hash('sha256', $rawToken);
        $userId = $this->passwordResetRepository->findValidUserIdByHashedToken($hashedToken);
        if ($userId === null) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => self::GENERIC_INVALID_TOKEN_MESSAGE,
            ];
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null || (int) ($user['is_active'] ?? 0) !== 1) {
            $this->passwordResetRepository->deleteForUser($userId);
            return [
                'ok' => false,
                'status' => 400,
                'message' => self::GENERIC_INVALID_TOKEN_MESSAGE,
            ];
        }

        $passwordHash = password_hash((string) $input['password'], PASSWORD_DEFAULT);

        $this->db->begin();
        try {
            $this->userRepository->updatePassword($userId, $passwordHash);
            if ($user['email_verified_at'] === null) {
                $this->userRepository->markEmailVerified($userId);
            }
            $this->passwordResetRepository->deleteForUser($userId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Your password has been reset successfully. You can now sign in with your new password.',
        ];
    }

    /**
     * @return array{ok: bool, status: int, message: string}
     */
    private function genericRequestResponse(): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'message' => self::GENERIC_REQUEST_MESSAGE,
        ];
    }
}
