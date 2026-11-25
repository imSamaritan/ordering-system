<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ResponseHelper;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class ResponseHelperTest extends TestCase
{
    public function testJsonResponseEncodesSuccessPayload(): void
    {
        $response = ResponseHelper::json(new Response(), ['status' => 'ok', 'currency' => 'ZAR']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($decoded['success']);
        $this->assertSame('ok', $decoded['data']['status']);
        $this->assertSame('ZAR', $decoded['data']['currency']);
    }

    public function testErrorResponseEncodesFailurePayload(): void
    {
        $response = ResponseHelper::error(new Response(), 'Invalid input', 422, ['email' => 'Required']);

        $this->assertSame(422, $response->getStatusCode());
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($decoded['success']);
        $this->assertSame('Invalid input', $decoded['message']);
        $this->assertSame('Required', $decoded['errors']['email']);
    }
}
