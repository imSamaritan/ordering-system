<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuthService;
use DI\ContainerBuilder;
use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerDashboardAndPdfTest extends TestCase
{
    private App $app;
    private Connection $db;
    private AuthService $authService;
    private ?int $customerAId = null;
    private ?int $customerBId = null;
    /** @var list<int> */
    private array $createdOrderIds = [];

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
        $this->authService = $container->get(AuthService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdOrderIds as $orderId) {
            $this->db->query('DELETE FROM orders WHERE id = %i', $orderId);
        }
        if ($this->customerAId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->customerAId);
        }
        if ($this->customerBId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->customerBId);
        }
    }

    public function testDashboardRoutesFilteringTotalsIsolationAndPdfDownload(): void
    {
        $reqFactory = new ServerRequestFactory();
        $cookieName = $this->authService->getCookieName();

        // 1. Unauthenticated /dashboard redirects to /login (302), API returns 401
        $unauthWeb = $this->app->handle($reqFactory->createServerRequest('GET', '/dashboard'));
        $this->assertSame(302, $unauthWeb->getStatusCode());
        $this->assertStringEndsWith('/login', $unauthWeb->getHeaderLine('Location'));

        $unauthApi = $this->app->handle($reqFactory->createServerRequest('GET', '/api/v1/customer/orders'));
        $this->assertSame(401, $unauthApi->getStatusCode());

        $unauthPdf = $this->app->handle($reqFactory->createServerRequest('GET', '/api/v1/customer/reports/pdf'));
        $this->assertSame(401, $unauthPdf->getStatusCode());

        // 2. Supplier role rejected (403)
        $supplier = $this->db->query('SELECT * FROM users WHERE role = %s LIMIT 1', 'supplier')->fetch();
        $this->assertNotNull($supplier);
        $supplierToken = $this->authService->issueToken([
            'id' => (int) $supplier['id'],
            'role' => 'supplier',
            'email' => (string) $supplier['email'],
        ]);

        $supplierDash = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/dashboard')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(403, $supplierDash->getStatusCode());

        $supplierApi = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(403, $supplierApi->getStatusCode());

        // 3. Create two verified customers (Customer A and Customer B)
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $emailA = "phpunit-dash-a-{$suffix}@example.co.za";
        $emailB = "phpunit-dash-b-{$suffix}@example.co.za";

        $this->db->query('INSERT INTO users', [
            'role' => 'company',
            'company_name' => 'Savannah Kitchen Co',
            'contact_name' => 'Thabo Mokoena',
            'email' => $emailA,
            'phone' => '+27 82 555 0111',
            'location' => 'Pretoria',
            'password_hash' => password_hash('DashPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerAId = $this->db->getInsertId();

        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Other Customer',
            'email' => $emailB,
            'phone' => '+27 82 555 0222',
            'location' => 'Cape Town',
            'password_hash' => password_hash('DashPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerBId = $this->db->getInsertId();

        $tokenA = $this->authService->issueToken([
            'id' => $this->customerAId,
            'role' => 'company',
            'email' => $emailA,
        ]);

        // Authenticated /dashboard renders HTML (200)
        $dashRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/dashboard')
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $dashRes->getStatusCode());
        $this->assertStringContainsString('customerDashboardPage', (string) $dashRes->getBody());

        // Pick a real product ID for FK integrity
        $product = $this->db->query('SELECT id, description FROM products LIMIT 1')->fetch();
        $this->assertNotNull($product);
        $productId = (int) $product['id'];

        // Seed 3 orders for Customer A + 1 order for Customer B
        $orderDeliveredId = $this->insertTestOrder(
            $this->customerAId,
            "ORD-20260115-{$suffix}1",
            'delivered',
            125.50,
            '2026-01-15 09:00:00',
            $productId
        );
        $orderDeclinedId = $this->insertTestOrder(
            $this->customerAId,
            "ORD-20260210-{$suffix}2",
            'declined',
            60.00,
            '2026-02-10 11:00:00',
            $productId
        );
        $orderPendingId = $this->insertTestOrder(
            $this->customerAId,
            "ORD-20260305-{$suffix}3",
            'pending',
            80.25,
            '2026-03-05 14:30:00',
            $productId
        );
        $otherCustomerOrderId = $this->insertTestOrder(
            $this->customerBId,
            "ORD-20260215-{$suffix}9",
            'delivered',
            999.00,
            '2026-02-15 12:00:00',
            $productId
        );

        // 4. Fetch all orders for Customer A -> only 3 returned (Customer B excluded)
        $allRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $allRes->getStatusCode());
        $allBody = json_decode((string) $allRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($allBody['success']);
        $this->assertCount(3, $allBody['data']['orders']);
        // Default all-status total_spend excludes declined (60.00) -> 125.50 + 80.25 = 205.75
        $this->assertSame(205.75, (float) $allBody['data']['summary']['total_spend']);
        $this->assertSame(1, $allBody['data']['summary']['delivered_count']);
        $this->assertSame(125.5, (float) $allBody['data']['summary']['delivered_spend']);
        $this->assertSame(1, $allBody['data']['summary']['declined_count']);
        $this->assertSame(60.0, (float) $allBody['data']['summary']['declined_total']);

        // Filtering explicitly by status=declined includes declined total in total_spend
        $declinedOnlyRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withQueryParams(['status' => 'declined'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $declinedOnlyRes->getStatusCode());
        $declinedOnlyBody = json_decode((string) $declinedOnlyRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $declinedOnlyBody['data']['orders']);
        $this->assertSame(60.0, (float) $declinedOnlyBody['data']['summary']['total_spend']);

        // 5. Date-range filter (Jan + Feb only -> excludes March pending order; declined excluded from total_spend)
        $rangeRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withQueryParams(['date_from' => '2026-01-01', 'date_to' => '2026-02-28'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $rangeRes->getStatusCode());
        $rangeBody = json_decode((string) $rangeRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $rangeBody['data']['orders']);
        $this->assertSame(125.5, (float) $rangeBody['data']['summary']['total_spend']);

        // 6. Order number partial filter
        $numRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withQueryParams(['order_number' => "{$suffix}2"])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $numBody = json_decode((string) $numRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $numBody['data']['orders']);
        $this->assertSame($orderDeclinedId, (int) $numBody['data']['orders'][0]['id']);

        // 7. Invalid date range rejected (422)
        $badDateRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withQueryParams(['date_from' => '2026-05-01', 'date_to' => '2026-04-01'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(422, $badDateRes->getStatusCode());

        // 8. Order detail returns items + status_history for Customer A
        $detailRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/customer/orders/{$orderDeliveredId}")
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $detailRes->getStatusCode());
        $detailBody = json_decode((string) $detailRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($orderDeliveredId, (int) $detailBody['data']['order']['id']);
        $this->assertCount(1, $detailBody['data']['order']['items']);
        $this->assertCount(1, $detailBody['data']['order']['status_history']);

        // 9. Tenant isolation: Customer A cannot inspect Customer B's order (404)
        $isolatedRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/customer/orders/{$otherCustomerOrderId}")
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(404, $isolatedRes->getStatusCode());

        // 10. PDF Statement download (default Complete/Delivered & Declined)
        $pdfRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/reports/pdf')
                ->withQueryParams(['date_from' => '2026-01-01', 'date_to' => '2026-12-31'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $pdfRes->getStatusCode());
        $this->assertSame('application/pdf', $pdfRes->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="order-statement-', $pdfRes->getHeaderLine('Content-Disposition'));
        $pdfBody = (string) $pdfRes->getBody();
        $this->assertStringStartsWith('%PDF-', $pdfBody);
        $this->assertGreaterThan(1000, strlen($pdfBody));

        // 11. PDF Statement for explicit selected order_ids
        $selectedPdfRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/reports/pdf')
                ->withQueryParams(['order_ids' => "{$orderDeliveredId},{$orderPendingId}"])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $selectedPdfRes->getStatusCode());
        $this->assertStringStartsWith('%PDF-', (string) $selectedPdfRes->getBody());
    }

    private function insertTestOrder(
        int $userId,
        string $orderNumber,
        string $status,
        float $totalAmount,
        string $orderedAt,
        int $productId
    ): int {
        $this->db->query('INSERT INTO orders', [
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'status' => $status,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'notes' => 'Test order',
            'ordered_at' => $orderedAt,
            'received_at' => $status === 'delivered' ? $orderedAt : null,
            'delivered_at' => $status === 'delivered' ? $orderedAt : null,
            'created_at' => $orderedAt,
            'updated_at' => $orderedAt,
        ]);

        $orderId = $this->db->getInsertId();
        $this->createdOrderIds[] = $orderId;

        $this->db->query('INSERT INTO order_items', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name_snapshot' => 'Test Produce Item',
            'unit_name_snapshot' => 'kg',
            'unit_price_snapshot' => number_format($totalAmount, 2, '.', ''),
            'quantity' => '1.00',
            'line_total' => number_format($totalAmount, 2, '.', ''),
        ]);

        $this->db->query('INSERT INTO order_status_history', [
            'order_id' => $orderId,
            'old_status' => null,
            'new_status' => $status,
            'changed_by_user_id' => $userId,
            'note' => 'Seeded for integration test.',
            'changed_at' => $orderedAt,
        ]);

        return $orderId;
    }
}
