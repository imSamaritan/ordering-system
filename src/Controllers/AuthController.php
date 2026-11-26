<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Services\VerificationService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly VerificationService $verificationService,
        private readonly PasswordResetService $passwordResetService,
        private readonly PhpRenderer $view
    ) {
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/login.php', [
            'title' => 'Sign In',
            'pageScript' => 'pages/auth.js',
        ]);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/register.php', [
            'title' => 'Create Account',
            'pageScript' => 'pages/auth.js',
        ]);
    }

    public function showVerifyEmail(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $token = (string) ($query['token'] ?? '');
        $result = $token !== ''
            ? $this->verificationService->verifyToken($token)
            : ['verified' => false, 'message' => 'Missing verification token.'];

        return $this->view->render($response, 'auth/verify-email.php', [
            'title' => 'Verify Email',
            'pageScript' => 'pages/auth.js',
            'verified' => $result['verified'],
            'verificationMessage' => $result['message'],
        ]);
    }

    public function showForgotPassword(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/forgot-password.php', [
            'title' => 'Forgot Password',
            'pageScript' => 'pages/auth.js',
        ]);
    }

    public function showResetPassword(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $token = trim((string) ($query['token'] ?? ''));
        $tokenValid = $this->passwordResetService->isTokenValid($token);

        return $this->view->render($response, 'auth/reset-password.php', [
            'title' => 'Reset Password',
            'pageScript' => 'pages/auth.js',
            'token' => $token,
            'tokenValid' => $tokenValid,
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $this->parsedBodyArray($request);
        $result = $this->authService->register($body);

        if (!$result['ok']) {
            return ResponseHelper::error(
                $response,
                $result['message'],
                $result['status'],
                $result['errors'] ?? []
            );
        }

        $data = [
            'message' => $result['message'],
            'user' => $result['user'] ?? null,
            'email_sent' => $result['email_sent'] ?? false,
        ];
        if (isset($result['debug_verify_url'])) {
            $data['debug_verify_url'] = $result['debug_verify_url'];
        }

        return ResponseHelper::json($response, $data, $result['status']);
    }

    public function verifyEmailApi(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $token = (string) ($query['token'] ?? '');
        $result = $this->verificationService->verifyToken($token);

        if (!$result['verified']) {
            return ResponseHelper::error($response, $result['message'], 400);
        }

        return ResponseHelper::json($response, [
            'verified' => true,
            'message' => $result['message'],
        ]);
    }

    public function resendVerification(Request $request, Response $response): Response
    {
        $body = $this->parsedBodyArray($request);
        $email = (string) ($body['email'] ?? '');
        $result = $this->authService->resendVerification($email);

        if (!$result['ok']) {
            return ResponseHelper::error($response, $result['message'], $result['status']);
        }

        $data = [
            'message' => $result['message'],
        ];
        if (isset($result['email_sent'])) {
            $data['email_sent'] = $result['email_sent'];
        }
        if (isset($result['debug_verify_url'])) {
            $data['debug_verify_url'] = $result['debug_verify_url'];
        }

        return ResponseHelper::json($response, $data, $result['status']);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $this->parsedBodyArray($request);
        $result = $this->authService->login($body);

        if (!$result['ok']) {
            $errors = $result['errors'] ?? [];
            if (isset($result['code'])) {
                $errors['code'] = $result['code'];
            }
            return ResponseHelper::error($response, $result['message'], $result['status'], $errors);
        }

        $isSecure = $this->isHttpsRequest($request);
        $cookieHeader = $this->authService->buildAuthCookieHeader((string) $result['token'], $isSecure);

        $response = $response->withAddedHeader('Set-Cookie', $cookieHeader);

        return ResponseHelper::json($response, [
            'message' => $result['message'],
            'user' => $result['user'],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $isSecure = $this->isHttpsRequest($request);
        $cookieHeader = $this->authService->buildClearCookieHeader($isSecure);
        $response = $response->withAddedHeader('Set-Cookie', $cookieHeader);

        return ResponseHelper::json($response, [
            'message' => 'Signed out successfully.',
        ]);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $body = $this->parsedBodyArray($request);
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';
        $result = $this->passwordResetService->requestReset($email);

        $data = [
            'message' => $result['message'],
        ];
        if (isset($result['debug_reset_url'])) {
            $data['debug_reset_url'] = $result['debug_reset_url'];
        }

        return ResponseHelper::json($response, $data, $result['status']);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $body = $this->parsedBodyArray($request);
        $result = $this->passwordResetService->resetPassword($body);

        if (!$result['ok']) {
            return ResponseHelper::error(
                $response,
                $result['message'],
                $result['status'],
                $result['errors'] ?? []
            );
        }

        return ResponseHelper::json($response, [
            'message' => $result['message'],
        ], $result['status']);
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var array<string, mixed>|null $user */
        $user = $request->getAttribute('auth_user');
        if ($user === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        return ResponseHelper::json($response, [
            'user' => $user,
        ]);
    }

    private function isHttpsRequest(Request $request): bool
    {
        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        $forwardedProto = strtolower(trim($request->getHeaderLine('X-Forwarded-Proto')));
        return $forwardedProto === 'https';
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedBodyArray(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
