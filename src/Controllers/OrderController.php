<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OrderService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function create(Request $request, Response $response): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $body = $this->getJsonBody($request);
        $result = $this->orderService->placeOrder($authUser, $body);

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
            'order' => $result['order'] ?? null,
            'emails' => $result['emails'] ?? null,
        ], $result['status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
