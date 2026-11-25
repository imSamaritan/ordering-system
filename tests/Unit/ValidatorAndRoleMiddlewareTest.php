<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\RoleMiddleware;
use App\Support\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class ValidatorAndRoleMiddlewareTest extends TestCase
{
    public function testRegistrationValidationRequiresCompanyNameForCompanyRole(): void
    {
        $errors = Validator::validateRegistration([
            'role' => 'company',
            'company_name' => '',
            'contact_name' => 'Sipho Ndlovu',
            'email' => 'sipho@bistro.co.za',
            'password' => 'StrongPass123!',
        ]);

        $this->assertArrayHasKey('company_name', $errors);
    }

    public function testRegistrationValidationRejectsSupplierSelfRegistrationAndWeakPassword(): void
    {
        $errors = Validator::validateRegistration([
            'role' => 'supplier',
            'contact_name' => 'A',
            'email' => 'not-an-email',
            'password' => '123',
        ]);

        $this->assertArrayHasKey('role', $errors);
        $this->assertArrayHasKey('contact_name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testRegistrationValidationPassesForValidCustomer(): void
    {
        $errors = Validator::validateRegistration([
            'role' => 'customer',
            'contact_name' => 'Thabo Mokoena',
            'email' => 'thabo@example.co.za',
            'password' => 'FreshProduce#2026',
            'phone' => '+27 82 123 4567',
            'location' => 'Sandton, Johannesburg',
        ]);

        $this->assertSame([], $errors);
    }

    public function testRoleMiddlewareBlocksUnauthenticatedAndWrongRole(): void
    {
        $middleware = new RoleMiddleware('supplier');
        $okHandler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $res = new Response(200);
                $res->getBody()->write('allowed');
                return $res;
            }
        };

        $factory = new ServerRequestFactory();

        // Unauthenticated
        $unauthReq = $factory->createServerRequest('GET', '/api/v1/supplier/companies');
        $unauthRes = $middleware->process($unauthReq, $okHandler);
        $this->assertSame(401, $unauthRes->getStatusCode());

        // Wrong role
        $customerReq = $factory->createServerRequest('GET', '/api/v1/supplier/companies')
            ->withAttribute('auth_user', ['id' => 10, 'role' => 'customer']);
        $customerRes = $middleware->process($customerReq, $okHandler);
        $this->assertSame(403, $customerRes->getStatusCode());

        // Allowed role
        $supplierReq = $factory->createServerRequest('GET', '/api/v1/supplier/companies')
            ->withAttribute('auth_user', ['id' => 1, 'role' => 'supplier']);
        $supplierRes = $middleware->process($supplierReq, $okHandler);
        $this->assertSame(200, $supplierRes->getStatusCode());
        $this->assertSame('allowed', (string) $supplierRes->getBody());
    }
}
