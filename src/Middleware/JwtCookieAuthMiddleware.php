<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class JwtCookieAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
        private readonly string $basePath = ''
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $cookies = $request->getCookieParams();
        $token = (string) ($cookies[$this->authService->getCookieName()] ?? '');

        $claims = $this->authService->decodeToken($token);
        if ($claims === null) {
            return $this->unauthorizedResponse($request);
        }

        $user = $this->userRepository->findById($claims['sub']);
        if ($user === null || (int) ($user['is_active'] ?? 0) !== 1 || $user['email_verified_at'] === null) {
            return $this->unauthorizedResponse($request);
        }

        $request = $request->withAttribute('auth_user', UserRepository::toPublicArray($user));

        return $handler->handle($request);
    }

    private function unauthorizedResponse(Request $request): Response
    {
        $response = new SlimResponse();
        $path = $request->getUri()->getPath();
        $accept = $request->getHeaderLine('Accept');

        if (str_contains($path, '/api/') || str_contains($accept, 'application/json')) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $loginUrl = rtrim($this->basePath, '/') . '/login';
        return $response
            ->withHeader('Location', $loginUrl)
            ->withStatus(302);
    }
}
