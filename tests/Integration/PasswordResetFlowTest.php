<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\PasswordResetService;
use DI\ContainerBuilder;
use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class PasswordResetFlowTest extends TestCase
{
    private App $app;
    private Connection $db;
    private PasswordResetService $passwordResetService;
    private string $activeEmail;
    private string $inactiveEmail;
    private int $activeUserId;
    private int $inactiveUserId;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();
        $_ENV['MAIL_DRY_RUN'] = 'true';
        $_ENV['APP_DEBUG'] = 'true';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(require $root . '/config/container.php');
        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
        (require $root . '/config/middleware.php')($this->app);
        (require $root . '/config/routes.php')($this->app);

        $this->db = $container->get(Connection::class);
        $this->passwordResetService = $container->get(PasswordResetService::class);

        $suffix = bin2hex(random_bytes(5));
        $this->activeEmail = "phpunit-reset-active-{$suffix}@example.co.za";
        $this->inactiveEmail = "phpunit-reset-inactive-{$suffix}@example.co.za";

        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Reset Test Active',
            'email' => $this->activeEmail,
            'phone' => null,
            'location' => null,
            'password_hash' => password_hash('InitialPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->activeUserId = $this->db->getInsertId();

        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Reset Test Inactive',
            'email' => $this->inactiveEmail,
            'phone' => null,
            'location' => null,
            'password_hash' => password_hash('InitialPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->inactiveUserId = $this->db->getInsertId();
    }

    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM users WHERE email IN (%s, %s)', $this->activeEmail, $this->inactiveEmail);
    }

    public function testAntiEnumerationFakesSuccessOnMisleadingUnknownAndInactiveInputs(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        $inputs = [
            '',
            'not-an-email',
            "admin'--@evil",
            'nonexistent-' . bin2hex(random_bytes(4)) . '@example.co.za',
            $this->inactiveEmail,
        ];

        $rawBodies = [];
        foreach ($inputs as $email) {
            $payload = json_encode(['email' => $email], JSON_THROW_ON_ERROR);
            $req = $reqFactory->createServerRequest('POST', '/api/v1/auth/forgot-password')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($streamFactory->createStream($payload));

            $res = $this->app->handle($req);
            $this->assertSame(200, $res->getStatusCode(), "Expected 200 OK for input: {$email}");

            $raw = (string) $res->getBody();
            $rawBodies[] = $raw;

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue($decoded['success']);
            $this->assertSame(PasswordResetService::GENERIC_REQUEST_MESSAGE, $decoded['data']['message']);
            $this->assertArrayNotHasKey('debug_reset_url', $decoded['data']);
            $this->assertArrayNotHasKey('email_sent', $decoded['data']);
        }

        // All faked responses must be byte-for-byte identical
        $this->assertCount(1, array_unique($rawBodies));

        // Zero rows written for inactive user
        $resetRows = (int) $this->db->query(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = %i',
            $this->inactiveUserId
        )->fetchSingle();
        $this->assertSame(0, $resetRows);

        $emailLogs = (int) $this->db->query(
            'SELECT COUNT(*) FROM email_logs WHERE recipient_email = %s AND email_type = %s',
            $this->inactiveEmail,
            'password_reset'
        )->fetchSingle();
        $this->assertSame(0, $emailLogs);
    }

    public function testEndToEndResetFlowExpiryCleanupReplacementAndViews(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        // 1. Web views render 200
        $forgotViewRes = $this->app->handle($reqFactory->createServerRequest('GET', '/forgot-password'));
        $this->assertSame(200, $forgotViewRes->getStatusCode());

        $invalidResetViewRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/reset-password')->withQueryParams(['token' => 'invalid-token'])
        );
        $this->assertSame(200, $invalidResetViewRes->getStatusCode());
        $this->assertStringContainsString('Invalid or Expired', (string) $invalidResetViewRes->getBody());

        // 2. Active user requests password reset
        $firstToken = $this->requestResetAndExtractToken($this->activeEmail);

        // Verify stored token is hashed, TTL >= 7 minutes in MySQL, and email_logs recorded it
        $row = $this->db->query(
            'SELECT hashed_token, TIMESTAMPDIFF(MINUTE, created_at, expiry_date) AS ttl_minutes
             FROM password_resets
             WHERE user_id = %i',
            $this->activeUserId
        )->fetch();
        $this->assertNotNull($row);
        $this->assertNotSame($firstToken, (string) $row->hashed_token);
        $this->assertSame(hash('sha256', $firstToken), (string) $row->hashed_token);
        $this->assertGreaterThanOrEqual(7, (int) $row->ttl_minutes);

        $logCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM email_logs WHERE recipient_email = %s AND email_type = %s AND status = %s',
            $this->activeEmail,
            'password_reset',
            'sent'
        )->fetchSingle();
        $this->assertSame(1, $logCount);

        // 3. Requesting a second reset replaces the first token (only 1 row remains)
        $secondToken = $this->requestResetAndExtractToken($this->activeEmail);
        $this->assertNotSame($firstToken, $secondToken);

        $rowCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = %i',
            $this->activeUserId
        )->fetchSingle();
        $this->assertSame(1, $rowCount);
        $this->assertFalse($this->passwordResetService->isTokenValid($firstToken));
        $this->assertTrue($this->passwordResetService->isTokenValid($secondToken));

        // Old token rejected with 400
        $oldTokenRes = $this->postResetPassword($firstToken, 'NewStrongPass#2026', 'NewStrongPass#2026');
        $this->assertSame(400, $oldTokenRes['status']);
        $this->assertSame(PasswordResetService::GENERIC_INVALID_TOKEN_MESSAGE, $oldTokenRes['body']['message']);

        // 4. Backdate token to expired -> isTokenValid returns false, reset returns 400, and row is deleted
        $this->db->query(
            'UPDATE password_resets SET expiry_date = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id = %i',
            $this->activeUserId
        );
        $this->assertFalse($this->passwordResetService->isTokenValid($secondToken));

        $afterExpiredCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = %i',
            $this->activeUserId
        )->fetchSingle();
        $this->assertSame(0, $afterExpiredCount);

        // Also test direct POST with an expired token deletes the row and returns 400
        $thirdToken = $this->requestResetAndExtractToken($this->activeEmail);
        $this->db->query(
            'UPDATE password_resets SET expiry_date = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id = %i',
            $this->activeUserId
        );
        $expiredPostRes = $this->postResetPassword($thirdToken, 'NewStrongPass#2026', 'NewStrongPass#2026');
        $this->assertSame(400, $expiredPostRes['status']);
        $this->assertSame(PasswordResetService::GENERIC_INVALID_TOKEN_MESSAGE, $expiredPostRes['body']['message']);
        $this->assertSame(
            0,
            (int) $this->db->query('SELECT COUNT(*) FROM password_resets WHERE user_id = %i', $this->activeUserId)->fetchSingle()
        );

        // 5. Issue fresh valid token -> GET /reset-password renders form -> weak password returns 422 without deleting token
        $validToken = $this->requestResetAndExtractToken($this->activeEmail);
        $validResetViewRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/reset-password')->withQueryParams(['token' => $validToken])
        );
        $this->assertSame(200, $validResetViewRes->getStatusCode());
        $this->assertStringContainsString('Set New Password', (string) $validResetViewRes->getBody());

        $weakRes = $this->postResetPassword($validToken, '123', '123');
        $this->assertSame(422, $weakRes['status']);
        $this->assertTrue($this->passwordResetService->isTokenValid($validToken));

        // 6. Valid reset updates password, deletes row from password_resets, rejects old password, accepts new, rejects reuse
        $successRes = $this->postResetPassword($validToken, 'UpdatedPass#2026', 'UpdatedPass#2026');
        $this->assertSame(200, $successRes['status']);
        $this->assertTrue($successRes['body']['success']);

        $remainingRows = (int) $this->db->query(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = %i',
            $this->activeUserId
        )->fetchSingle();
        $this->assertSame(0, $remainingRows);

        // Old password fails with 401
        $oldLoginReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'email' => $this->activeEmail,
                'password' => 'InitialPass#2026',
            ], JSON_THROW_ON_ERROR)));
        $this->assertSame(401, $this->app->handle($oldLoginReq)->getStatusCode());

        // New password succeeds with 200
        $newLoginReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'email' => $this->activeEmail,
                'password' => 'UpdatedPass#2026',
            ], JSON_THROW_ON_ERROR)));
        $this->assertSame(200, $this->app->handle($newLoginReq)->getStatusCode());

        // Token reuse fails with 400
        $reuseRes = $this->postResetPassword($validToken, 'AnotherPass#2026', 'AnotherPass#2026');
        $this->assertSame(400, $reuseRes['status']);
        $this->assertSame(PasswordResetService::GENERIC_INVALID_TOKEN_MESSAGE, $reuseRes['body']['message']);
    }

    private function requestResetAndExtractToken(string $email): string
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        $req = $reqFactory->createServerRequest('POST', '/api/v1/auth/forgot-password')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode(['email' => $email], JSON_THROW_ON_ERROR)));

        $res = $this->app->handle($req);
        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('debug_reset_url', $body['data']);

        parse_str((string) parse_url((string) $body['data']['debug_reset_url'], PHP_URL_QUERY), $queryParams);
        $token = (string) ($queryParams['token'] ?? '');
        $this->assertNotEmpty($token);

        return $token;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function postResetPassword(string $token, string $password, string $confirmPassword): array
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        $req = $reqFactory->createServerRequest('POST', '/api/v1/auth/reset-password')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'token' => $token,
                'password' => $password,
                'confirm_password' => $confirmPassword,
            ], JSON_THROW_ON_ERROR)));

        $res = $this->app->handle($req);

        return [
            'status' => $res->getStatusCode(),
            'body' => json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}
