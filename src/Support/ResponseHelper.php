<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;

final class ResponseHelper
{
    /**
     * @param array<string, mixed> $data
     */
    public static function json(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode([
            'success' => $status >= 200 && $status < 300,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload === false ? '{}' : $payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function error(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload === false ? '{}' : $payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
