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
use Slim\Psr7\Factory\StreamFactory;

final class CustomerOrderCancelAndNotesTest extends TestCase
{
    private App $app;
    private Connection $db;
    private AuthService $authService;
    private ?int $customerAId = null;
    private ?int $customerBId = null;
    /** @var list<int> */
    private array $createdOrderIds = [];
    /** @var list<int> */
    private array $createdProductIds = [];

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
            $this->db->query('DELETE FROM email_logs WHERE order_id = %i', $orderId);
            $this->db->query('DELETE FROM orders WHERE id = %i', $orderId);
        }
        foreach ($this->createdProductIds as $productId) {
            $this->db->query('DELETE FROM products WHERE id = %i', $productId);
        }
        if ($this->customerAId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->customerAId);
        }
        if ($this->customerBId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->customerBId);
        }
    }

    public function testCancelPendingOrderAndEditPendingNotesEndToEnd(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();
        $cookieName = $this->authService->getCookieName();

        // 1. Unauthenticated requests return 401
        $unauthCancel = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/customer/orders/1/cancel')
        );
        $this->assertSame(401, $unauthCancel->getStatusCode());

        $unauthNotes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', '/api/v1/customer/orders/1/notes')
        );
        $this->assertSame(401, $unauthNotes->getStatusCode());

        // 2. Supplier role rejected with 403
        $supplier = $this->db->query('SELECT * FROM users WHERE role = %s LIMIT 1', 'supplier')->fetch();
        $this->assertNotNull($supplier);
        $supplierToken = $this->authService->issueToken([
            'id' => (int) $supplier['id'],
            'role' => 'supplier',
            'email' => (string) $supplier['email'],
        ]);

        $supplierCancel = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/customer/orders/1/cancel')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(403, $supplierCancel->getStatusCode());

        $supplierNotes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', '/api/v1/customer/orders/1/notes')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(403, $supplierNotes->getStatusCode());

        // 3. Create two verified buyer accounts (A and B)
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $emailA = "phpunit-cancel-a-{$suffix}@example.co.za";
        $emailB = "phpunit-cancel-b-{$suffix}@example.co.za";

        $this->db->query('INSERT INTO users', [
            'role' => 'company',
            'company_name' => 'Green Leaf Kitchen',
            'contact_name' => 'Thabo Mokoena',
            'email' => $emailA,
            'phone' => '+27 82 555 0411',
            'location' => 'Pretoria',
            'password_hash' => password_hash('CancelPass#2026', PASSWORD_DEFAULT),
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
            'phone' => '+27 82 555 0422',
            'location' => 'Cape Town',
            'password_hash' => password_hash('CancelPass#2026', PASSWORD_DEFAULT),
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

        // 4. Create test products (kg decimal + Unit whole)
        $categoryId = (int) $this->db->query('SELECT id FROM categories LIMIT 1')->fetchSingle();
        $kgUnitId = (int) $this->db->query('SELECT id FROM units WHERE allow_decimal = 1 LIMIT 1')->fetchSingle();
        $wholeUnitId = (int) $this->db->query('SELECT id FROM units WHERE allow_decimal = 0 LIMIT 1')->fetchSingle();

        $kgProdId = $this->insertTestProduct(
            $categoryId,
            $kgUnitId,
            "SKU-CNL-KG-{$suffix}",
            "cancel-kg-{$suffix}",
            'Test Tomatoes',
            25.00,
            10.00
        );
        $unitProdId = $this->insertTestProduct(
            $categoryId,
            $wholeUnitId,
            "SKU-CNL-UN-{$suffix}",
            "cancel-un-{$suffix}",
            'Test Eggs Tray',
            75.00,
            5.00
        );

        // 5. Create orders:
        // - Customer A pending order (stock already deducted to 10.00 and 5.00 above)
        // - Customer A received order (non-pending)
        // - Customer B pending order (cross-user isolation)
        $pendingOrderAId = $this->insertOrderWithItems(
            $this->customerAId,
            "ORD-CNL-PEND-{$suffix}",
            'pending',
            'Initial gate note',
            [
                ['product_id' => $kgProdId, 'name' => 'Test Tomatoes', 'unit' => 'kg', 'price' => 25.00, 'qty' => 2.50],
                ['product_id' => $unitProdId, 'name' => 'Test Eggs Tray', 'unit' => 'Unit', 'price' => 75.00, 'qty' => 3.00],
            ]
        );

        $receivedOrderAId = $this->insertOrderWithItems(
            $this->customerAId,
            "ORD-CNL-RECV-{$suffix}",
            'received',
            'Locked note',
            [
                ['product_id' => $kgProdId, 'name' => 'Test Tomatoes', 'unit' => 'kg', 'price' => 25.00, 'qty' => 1.00],
            ]
        );

        $pendingOrderBId = $this->insertOrderWithItems(
            $this->customerBId,
            "ORD-CNL-B-{$suffix}",
            'pending',
            'Customer B note',
            [
                ['product_id' => $kgProdId, 'name' => 'Test Tomatoes', 'unit' => 'kg', 'price' => 25.00, 'qty' => 1.00],
            ]
        );

        // 6. Cross-user isolation -> 404
        $crossNotesRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/customer/orders/{$pendingOrderBId}/notes")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['notes' => 'Hacked'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(404, $crossNotesRes->getStatusCode());

        $crossCancelRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', "/api/v1/customer/orders/{$pendingOrderBId}/cancel")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['reason' => 'Not mine'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(404, $crossCancelRes->getStatusCode());

        // 7. Edit notes on pending order succeeds
        $editNotesRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/customer/orders/{$pendingOrderAId}/notes")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['notes' => 'Deliver to back kitchen gate #4'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(200, $editNotesRes->getStatusCode());
        $editNotesBody = json_decode((string) $editNotesRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($editNotesBody['success']);
        $this->assertSame('Deliver to back kitchen gate #4', $editNotesBody['data']['order']['notes']);

        // Notes > 1000 chars rejected (422)
        $tooLongNotesRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/customer/orders/{$pendingOrderAId}/notes")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['notes' => str_repeat('a', 1001)], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $tooLongNotesRes->getStatusCode());

        // Editing notes on received order rejected (422)
        $lockedNotesRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/customer/orders/{$receivedOrderAId}/notes")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['notes' => 'Too late'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $lockedNotesRes->getStatusCode());

        // 8. Cancelling a non-pending (received) order is rejected (422)
        $cancelReceivedRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', "/api/v1/customer/orders/{$receivedOrderAId}/cancel")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['reason' => 'Changed mind'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $cancelReceivedRes->getStatusCode());

        // 9. Cancel Customer A's pending order -> 200, stock restored, history & emails logged
        $cancelRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', "/api/v1/customer/orders/{$pendingOrderAId}/cancel")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['reason' => 'Placed duplicate order by mistake'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(200, $cancelRes->getStatusCode());
        $cancelBody = json_decode((string) $cancelRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($cancelBody['success']);
        $this->assertSame('cancelled', $cancelBody['data']['order']['status']);
        $this->assertNotEmpty($cancelBody['data']['order']['cancelled_at']);
        $this->assertTrue($cancelBody['data']['emails']['customer_sent']);
        $this->assertTrue($cancelBody['data']['emails']['supplier_sent']);

        // Verify reserved stock restored atomically (10.00 + 2.50 = 12.50, 5.00 + 3.00 = 8.00)
        $kgStockAfter = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $kgProdId)->fetchSingle();
        $unitStockAfter = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $unitProdId)->fetchSingle();
        $this->assertEqualsWithDelta(12.50, $kgStockAfter, 0.001);
        $this->assertEqualsWithDelta(8.00, $unitStockAfter, 0.001);

        // Verify order_status_history logged
        $historyRow = $this->db->query(
            'SELECT * FROM order_status_history WHERE order_id = %i AND new_status = %s LIMIT 1',
            $pendingOrderAId,
            'cancelled'
        )->fetch();
        $this->assertNotNull($historyRow);
        $this->assertSame('pending', (string) $historyRow['old_status']);
        $this->assertSame($this->customerAId, (int) $historyRow['changed_by_user_id']);
        $this->assertSame('Placed duplicate order by mistake', (string) $historyRow['note']);

        // Verify both customer and supplier emails logged
        $emailTypes = $this->db->query(
            'SELECT email_type FROM email_logs WHERE order_id = %i',
            $pendingOrderAId
        )->fetchPairs(null, 'email_type');
        $this->assertContains('order_cancelled', $emailTypes);
        $this->assertContains('supplier_order_cancelled', $emailTypes);

        // 10. Double-cancel is rejected (422) and does not double-restore stock
        $doubleCancelRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', "/api/v1/customer/orders/{$pendingOrderAId}/cancel")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $tokenA])
                ->withBody($streamFactory->createStream(json_encode(['reason' => 'Again'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $doubleCancelRes->getStatusCode());
        $kgStockDouble = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $kgProdId)->fetchSingle();
        $this->assertEqualsWithDelta(12.50, $kgStockDouble, 0.001);

        // 11. Supplier cannot transition a cancelled order
        $supplierTransitionRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/supplier/orders/{$pendingOrderAId}/status")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode(['status' => 'received'], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $supplierTransitionRes->getStatusCode());

        // 12. Customer & Supplier list endpoints filter by status=cancelled & PDF report supports it
        $customerListRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders')
                ->withQueryParams(['status' => 'cancelled'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $customerListRes->getStatusCode());
        $customerListBody = json_decode((string) $customerListRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $customerListBody['data']['orders']);
        $this->assertSame($pendingOrderAId, $customerListBody['data']['orders'][0]['id']);
        $this->assertSame(1, $customerListBody['data']['summary']['cancelled_count']);
        $this->assertGreaterThan(0, $customerListBody['data']['summary']['cancelled_total']);

        $supplierListRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/supplier/companies/{$this->customerAId}/orders")
                ->withQueryParams(['status' => 'cancelled'])
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $supplierListRes->getStatusCode());
        $supplierListBody = json_decode((string) $supplierListRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $supplierListBody['data']['orders']);
        $this->assertSame($pendingOrderAId, $supplierListBody['data']['orders'][0]['id']);

        $pdfRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/reports/pdf')
                ->withQueryParams(['status' => 'cancelled'])
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $pdfRes->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdfRes->getHeaderLine('Content-Type'));
    }

    private function insertTestProduct(
        int $categoryId,
        int $unitId,
        string $sku,
        string $slug,
        string $description,
        float $unitPrice,
        float $stockQuantity
    ): int {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO products', [
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'sku' => $sku,
            'slug' => $slug,
            'description' => $description,
            'image_path' => null,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'stock_quantity' => number_format($stockQuantity, 2, '.', ''),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = $this->db->getInsertId();
        $this->createdProductIds[] = $id;
        return $id;
    }

    /**
     * @param list<array{product_id: int, name: string, unit: string, price: float, qty: float}> $items
     */
    private function insertOrderWithItems(
        int $userId,
        string $orderNumber,
        string $status,
        ?string $notes,
        array $items
    ): int {
        $now = date('Y-m-d H:i:s');
        $total = 0.0;
        foreach ($items as $item) {
            $total += round($item['price'] * $item['qty'], 2);
        }

        $this->db->query('INSERT INTO orders', [
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'status' => $status,
            'total_amount' => number_format($total, 2, '.', ''),
            'notes' => $notes,
            'ordered_at' => $now,
            'received_at' => $status === 'received' ? $now : null,
            'delivered_at' => null,
            'cancelled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = $this->db->getInsertId();
        $this->createdOrderIds[] = $orderId;

        foreach ($items as $item) {
            $lineTotal = round($item['price'] * $item['qty'], 2);
            $this->db->query('INSERT INTO order_items', [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['name'],
                'unit_name_snapshot' => $item['unit'],
                'unit_price_snapshot' => number_format($item['price'], 2, '.', ''),
                'quantity' => number_format($item['qty'], 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        }

        return $orderId;
    }
}
