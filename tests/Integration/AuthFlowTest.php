<?php

declare(strict_types=1);

namespace Tests\Integration;

use DI\ContainerBuilder;
use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class AuthFlowTest extends TestCase
{
    private App $app;
    private Connection $db;
    private string $testEmail;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();
        $_ENV['MAIL_DRY_RUN'] = 'true';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(require $root . '/config/container.php');
        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
        (require $root . '/config/middleware.php')($this->app);
        (require $root . '/config/routes.php')($this->app);

        $this->db = $container->get(Connection::class);
        $this->testEmail = 'phpunit-auth-' . bin2hex(random_bytes(5)) . '@example.co.za';
    }

    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM users WHERE email = %s', $this->testEmail);
    }

    public function testCompleteRegistrationVerificationLoginMeAndLogoutFlow(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        // 1. Register new company user
        $regPayload = json_encode([
            'role' => 'company',
            'company_name' => 'Unit Test Kitchen (Pty) Ltd',
            'contact_name' => 'Lerato Dlamini',
            'email' => $this->testEmail,
            'phone' => '+27 11 555 0199',
            'location' => 'Rosebank, Johannesburg',
            'password' => 'SecurePass#2026',
        ], JSON_THROW_ON_ERROR);

        $regReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream($regPayload));

        $regRes = $this->app->handle($regReq);
        $this->assertSame(201, $regRes->getStatusCode());
        $regBody = json_decode((string) $regRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($regBody['success']);
        $this->assertSame($this->testEmail, $regBody['data']['user']['email']);
        $this->assertNull($regBody['data']['user']['email_verified_at']);
        $this->assertArrayHasKey('debug_verify_url', $regBody['data']);

        // Confirm email_logs recorded the verification email
        $logCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM email_logs WHERE recipient_email = %s AND email_type = %s',
            $this->testEmail,
            'email_verification'
        )->fetchSingle();
        $this->assertGreaterThanOrEqual(1, $logCount);

        // 2. Duplicate registration rejected with 409
        $dupReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream($regPayload));
        $dupRes = $this->app->handle($dupReq);
        $this->assertSame(409, $dupRes->getStatusCode());

        // 3. Login blocked before email verification (403 EMAIL_NOT_VERIFIED)
        $loginPayload = json_encode([
            'email' => $this->testEmail,
            'password' => 'SecurePass#2026',
        ], JSON_THROW_ON_ERROR);

        $preLoginReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream($loginPayload));
        $preLoginRes = $this->app->handle($preLoginReq);
        $this->assertSame(403, $preLoginRes->getStatusCode());
        $preLoginBody = json_decode((string) $preLoginRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('EMAIL_NOT_VERIFIED', $preLoginBody['errors']['code'] ?? null);

        // 4. Verify email via token from debug_verify_url
        $verifyUrl = (string) $regBody['data']['debug_verify_url'];
        parse_str((string) parse_url($verifyUrl, PHP_URL_QUERY), $queryParams);
        $token = (string) ($queryParams['token'] ?? '');
        $this->assertNotEmpty($token);

        $verifyReq = $reqFactory->createServerRequest('GET', '/api/v1/auth/verify-email')
            ->withQueryParams(['token' => $token]);
        $verifyRes = $this->app->handle($verifyReq);
        $this->assertSame(200, $verifyRes->getStatusCode());
        $verifyBody = json_decode((string) $verifyRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($verifyBody['data']['verified']);

        // 5. Re-using the verification token fails (400)
        $reuseRes = $this->app->handle($verifyReq);
        $this->assertSame(400, $reuseRes->getStatusCode());

        // 6. Login succeeds and sets HttpOnly JWT cookie
        $loginReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream($loginPayload));
        $loginRes = $this->app->handle($loginReq);
        $this->assertSame(200, $loginRes->getStatusCode());

        $setCookie = $loginRes->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('HttpOnly', $setCookie);
        $this->assertStringContainsString('SameSite=Lax', $setCookie);

        $cookieName = $_ENV['JWT_COOKIE_NAME'] ?? 'ordering_auth_token';
        preg_match('/' . preg_quote($cookieName, '/') . '=([^;]+)/', $setCookie, $matches);
        $this->assertNotEmpty($matches[1] ?? '');
        $jwt = rawurldecode($matches[1]);

        // 7. /api/v1/auth/me rejects missing cookie (401) and accepts valid cookie (200)
        $unauthMeReq = $reqFactory->createServerRequest('GET', '/api/v1/auth/me');
        $unauthMeRes = $this->app->handle($unauthMeReq);
        $this->assertSame(401, $unauthMeRes->getStatusCode());

        $authMeReq = $reqFactory->createServerRequest('GET', '/api/v1/auth/me')
            ->withCookieParams([$cookieName => $jwt]);
        $authMeRes = $this->app->handle($authMeReq);
        $this->assertSame(200, $authMeRes->getStatusCode());
        $authMeBody = json_decode((string) $authMeRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($this->testEmail, $authMeBody['data']['user']['email']);
        $this->assertSame('company', $authMeBody['data']['user']['role']);

        // 8. Logout clears cookie
        $logoutReq = $reqFactory->createServerRequest('POST', '/api/v1/auth/logout');
        $logoutRes = $this->app->handle($logoutReq);
        $this->assertSame(200, $logoutRes->getStatusCode());
        $this->assertStringContainsString('Max-Age=0', $logoutRes->getHeaderLine('Set-Cookie'));
    }
}
