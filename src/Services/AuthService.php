<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Validator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

final class AuthService
{
    public const JWT_ALGORITHM = 'HS256';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VerificationService $verificationService,
        private readonly string $jwtSecret,
        private readonly int $jwtTtl,
        private readonly string $cookieName,
        private readonly bool $appDebug = false
    ) {
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   errors?: array<string, string>,
     *   user?: array<string, mixed>,
     *   email_sent?: bool,
     *   debug_verify_url?: string
     * }
     */
    public function register(array $input): array
    {
        $errors = Validator::validateRegistration($input);
        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $errors,
            ];
        }

        $email = strtolower(trim((string) $input['email']));
        if ($this->userRepository->findByEmail($email) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'An account with this email address already exists.',
                'errors' => ['email' => 'This email address is already registered.'],
            ];
        }

        $role = strtolower(trim((string) ($input['role'] ?? 'customer')));
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));

        $userId = $this->userRepository->create([
            'role' => $role,
            'company_name' => $companyName !== '' ? $companyName : null,
            'contact_name' => trim((string) $input['contact_name']),
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'location' => $location !== '' ? $location : null,
            'password_hash' => password_hash((string) $input['password'], PASSWORD_DEFAULT),
        ]);

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new RuntimeException('Failed to load newly created user.');
        }

        $verification = $this->verificationService->createAndSendForUser($user);

        $message = $verification['email_sent']
            ? 'Account created. Please check your email to verify your address before signing in.'
            : 'Account created, but we could not send the verification email right now. Please try resending the verification link shortly or contact support.';

        $result = [
            'ok' => true,
            'status' => 201,
            'message' => $message,
            'user' => UserRepository::toPublicArray($user),
            'email_sent' => $verification['email_sent'],
        ];

        if ($this->appDebug) {
            $result['debug_verify_url'] = $verification['verify_url'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   code?: string,
     *   errors?: array<string, string>,
     *   user?: array<string, mixed>,
     *   token?: string
     * }
     */
    public function login(array $input): array
    {
        $errors = Validator::validateLogin($input);
        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please enter a valid email address and password.',
                'errors' => $errors,
            ];
        }

        $email = strtolower(trim((string) $input['email']));
        $password = (string) $input['password'];
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Invalid email address or password.',
            ];
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'This account has been deactivated. Please contact support.',
            ];
        }

        if ($user['email_verified_at'] === null) {
            return [
                'ok' => false,
                'status' => 403,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Please verify your email address before signing in.',
            ];
        }

        $publicUser = UserRepository::toPublicArray($user);
        $token = $this->issueToken($publicUser);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Signed in successfully.',
            'user' => $publicUser,
            'token' => $token,
        ];
    }

    /**
     * @return array{ok: bool, status: int, message: string, email_sent?: bool, debug_verify_url?: string}
     */
    public function resendVerification(string $email): array
    {
        if (!Validator::isValidEmail($email)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please provide a valid email address.',
            ];
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'If an unverified account exists for this email, a new verification link has been sent.',
            ];
        }

        if ($user['email_verified_at'] !== null) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'This email address is already verified. You can sign in now.',
            ];
        }

        $verification = $this->verificationService->createAndSendForUser($user);

        if (!$verification['email_sent']) {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'We could not send the verification email right now. Please try again in a moment.',
                'email_sent' => false,
            ];
        }

        $result = [
            'ok' => true,
            'status' => 200,
            'message' => 'A fresh verification email has been sent. Please check your inbox.',
            'email_sent' => true,
        ];

        if ($this->appDebug) {
            $result['debug_verify_url'] = $verification['verify_url'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $publicUser
     */
    public function issueToken(array $publicUser): string
    {
        if (strlen($this->jwtSecret) < 16) {
            throw new RuntimeException('JWT_SECRET must be configured with at least 16 characters.');
        }

        $now = time();
        $payload = [
            'sub' => (int) $publicUser['id'],
            'role' => (string) $publicUser['role'],
            'email' => (string) $publicUser['email'],
            'iat' => $now,
            'exp' => $now + $this->jwtTtl,
        ];

        return JWT::encode($payload, $this->jwtSecret, self::JWT_ALGORITHM);
    }

    /**
     * @return array{sub: int, role: string, email: string, iat: int, exp: int}|null
     */
    public function decodeToken(string $jwt): ?array
    {
        if ($jwt === '' || strlen($this->jwtSecret) < 16) {
            return null;
        }

        try {
            $decoded = (array) JWT::decode($jwt, new Key($this->jwtSecret, self::JWT_ALGORITHM));
            if (!isset($decoded['sub'], $decoded['role'], $decoded['email'])) {
                return null;
            }

            return [
                'sub' => (int) $decoded['sub'],
                'role' => (string) $decoded['role'],
                'email' => (string) $decoded['email'],
                'iat' => (int) ($decoded['iat'] ?? 0),
                'exp' => (int) ($decoded['exp'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function buildAuthCookieHeader(string $jwt, bool $isSecure): string
    {
        $parts = [
            "{$this->cookieName}=" . rawurlencode($jwt),
            'Path=/',
            "Max-Age={$this->jwtTtl}",
            'HttpOnly',
            'SameSite=Lax',
        ];

        if ($isSecure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public function buildClearCookieHeader(bool $isSecure): string
    {
        $parts = [
            "{$this->cookieName}=",
            'Path=/',
            'Max-Age=0',
            'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if ($isSecure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
