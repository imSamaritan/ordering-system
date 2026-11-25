<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class RoleMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $allowedRoles;

    public function __construct(string ...$allowedRoles)
    {
        $this->allowedRoles = array_values($allowedRoles);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        /** @var array<string, mixed>|null $user */
        $user = $request->getAttribute('auth_user');
        if ($user === null) {
            return ResponseHelper::error(new SlimResponse(), 'Authentication required.', 401);
        }

        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, $this->allowedRoles, true)) {
            $response = new SlimResponse();
            $path = $request->getUri()->getPath();
            $accept = $request->getHeaderLine('Accept');

            if (str_contains($path, '/api/') || str_contains($accept, 'application/json')) {
                return ResponseHelper::error($response, 'You do not have permission to access this resource.', 403);
            }

            $response->getBody()->write('403 Forbidden: You do not have permission to access this page.');
            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}
