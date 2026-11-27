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

final class CustomerReorderTest extends TestCase
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

    public function testReorderPreviewAuthIsolationAndLiveStockPriceValidation(): void
    {
        $reqFactory = new ServerRequestFactory();
        $cookieName = $this->authService->getCookieName();

        // 1. Unauthenticated request returns 401
        $unauth = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders/1/reorder')
        );
        $this->assertSame(401, $unauth->getStatusCode());

        // 2. Supplier role rejected (403)
        $supplier = $this->db->query('SELECT * FROM users WHERE role = %s LIMIT 1', 'supplier')->fetch();
        $this->assertNotNull($supplier);
        $supplierToken = $this->authService->issueToken([
            'id' => (int) $supplier['id'],
            'role' => 'supplier',
            'email' => (string) $supplier['email'],
        ]);

        $supplierRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/customer/orders/1/reorder')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(403, $supplierRes->getStatusCode());

        // 3. Create two verified customers (A and B)
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $emailA = "phpunit-reorder-a-{$suffix}@example.co.za";
        $emailB = "phpunit-reorder-b-{$suffix}@example.co.za";

        $this->db->query('INSERT INTO users', [
            'role' => 'company',
            'company_name' => 'Harvest Bistro',
            'contact_name' => 'Lerato Dlamini',
            'email' => $emailA,
            'phone' => '+27 82 555 0311',
            'location' => 'Johannesburg',
            'password_hash' => password_hash('ReorderPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerAId = $this->db->getInsertId();

        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Other Buyer',
            'email' => $emailB,
            'phone' => '+27 82 555 0322',
            'location' => 'Durban',
            'password_hash' => password_hash('ReorderPass#2026', PASSWORD_DEFAULT),
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

        // Lookup category & units (kg decimal + Unit whole)
        $categoryId = (int) $this->db->query('SELECT id FROM categories LIMIT 1')->fetchSingle();
        $kgUnitId = (int) $this->db->query('SELECT id FROM units WHERE allow_decimal = 1 LIMIT 1')->fetchSingle();
        $wholeUnitId = (int) $this->db->query('SELECT id FROM units WHERE allow_decimal = 0 LIMIT 1')->fetchSingle();
        $this->assertGreaterThan(0, $categoryId);
        $this->assertGreaterThan(0, $kgUnitId);
        $this->assertGreaterThan(0, $wholeUnitId);

        // 4. Create 5 test products covering every reorder state
        $prodAvailableSamePrice = $this->insertTestProduct(
            $categoryId,
            $kgUnitId,
            "SKU-AVAIL-{$suffix}",
            "reorder-avail-{$suffix}",
            'Test Avocados',
            45.00,
            20.00,
            1,
            'assets/images/products/test-avocados.png'
        );
        $prodAvailablePriceChanged = $this->insertTestProduct(
            $categoryId,
            $wholeUnitId,
            "SKU-PRICE-{$suffix}",
            "reorder-price-{$suffix}",
            'Test Free Range Eggs',
            92.50,
            15.00,
            1,
            'assets/images/products/test-eggs.png'
        );
        $prodAdjustedKg = $this->insertTestProduct(
            $categoryId,
            $kgUnitId,
            "SKU-ADJKG-{$suffix}",
            "reorder-adjkg-{$suffix}",
            'Test Baby Spinach',
            30.00,
            1.40,
            1,
            null
        );
        $prodAdjustedUnit = $this->insertTestProduct(
            $categoryId,
            $wholeUnitId,
            "SKU-ADJU-{$suffix}",
            "reorder-adju-{$suffix}",
            'Test Lettuce Head',
            18.00,
            2.70,
            1,
            null
        );
        $prodInactive = $this->insertTestProduct(
            $categoryId,
            $kgUnitId,
            "SKU-INACT-{$suffix}",
            "reorder-inact-{$suffix}",
            'Test Seasonal Fig',
            60.00,
            10.00,
            0,
            null
        );
        $prodOutOfStock = $this->insertTestProduct(
            $categoryId,
            $wholeUnitId,
            "SKU-OOS-{$suffix}",
            "reorder-oos-{$suffix}",
            'Test Basil Punnet',
            15.00,
            0.00,
            1,
            null
        );

        // 5. Create Customer A's mixed order
        $orderAId = $this->insertOrderWithItems(
            $this->customerAId,
            "ORD-REORDER-A-{$suffix}",
            'delivered',
            [
                [
                    'product_id' => $prodAvailableSamePrice,
                    'name' => 'Test Avocados',
                    'unit' => 'kg',
                    'price' => 45.00,
                    'qty' => 2.50,
                ],
                [
                    'product_id' => $prodAvailablePriceChanged,
                    'name' => 'Test Free Range Eggs',
                    'unit' => 'Unit',
                    'price' => 80.00, // increased by +12.50 to 92.50
                    'qty' => 2.00,
                ],
                [
                    'product_id' => $prodAdjustedKg,
                    'name' => 'Test Baby Spinach',
                    'unit' => 'kg',
                    'price' => 30.00,
                    'qty' => 3.00, // only 1.4 kg in stock
                ],
                [
                    'product_id' => $prodAdjustedUnit,
                    'name' => 'Test Lettuce Head',
                    'unit' => 'Unit',
                    'price' => 18.00,
                    'qty' => 5.00, // 2.70 in stock -> floors to 2 whole units
                ],
                [
                    'product_id' => $prodInactive,
                    'name' => 'Test Seasonal Fig',
                    'unit' => 'kg',
                    'price' => 60.00,
                    'qty' => 1.00,
                ],
                [
                    'product_id' => $prodOutOfStock,
                    'name' => 'Test Basil Punnet',
                    'unit' => 'Unit',
                    'price' => 15.00,
                    'qty' => 3.00,
                ],
            ]
        );

        // Create Customer B's order to test cross-user isolation
        $orderBId = $this->insertOrderWithItems(
            $this->customerBId,
            "ORD-REORDER-B-{$suffix}",
            'delivered',
            [
                [
                    'product_id' => $prodAvailableSamePrice,
                    'name' => 'Test Avocados',
                    'unit' => 'kg',
                    'price' => 45.00,
                    'qty' => 1.00,
                ],
            ]
        );

        // Create an order where all items are unavailable
        $orderAllUnavailableId = $this->insertOrderWithItems(
            $this->customerAId,
            "ORD-REORDER-NONE-{$suffix}",
            'delivered',
            [
                [
                    'product_id' => $prodOutOfStock,
                    'name' => 'Test Basil Punnet',
                    'unit' => 'Unit',
                    'price' => 15.00,
                    'qty' => 1.00,
                ],
            ]
        );

        // 6. Cross-user isolation: Customer A cannot preview Customer B's order (404)
        $isolatedRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/customer/orders/{$orderBId}/reorder")
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(404, $isolatedRes->getStatusCode());

        // 7. Preview Customer A's mixed order
        $previewRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/customer/orders/{$orderAId}/reorder")
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $previewRes->getStatusCode());
        $body = json_decode((string) $previewRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($body['success']);

        $data = $body['data'];
        $this->assertSame($orderAId, $data['order_id']);
        $this->assertCount(2, $data['available']);
        $this->assertCount(2, $data['adjusted']);
        $this->assertCount(2, $data['unavailable']);

        $summary = $data['summary'];
        $this->assertSame(6, $summary['total_items']);
        $this->assertSame(2, $summary['available_count']);
        $this->assertSame(2, $summary['adjusted_count']);
        $this->assertSame(2, $summary['unavailable_count']);
        $this->assertSame(1, $summary['price_changed_count']);
        $this->assertTrue($summary['can_reorder']);

        // Verify available items details
        $avail0 = $data['available'][0];
        $this->assertSame($prodAvailableSamePrice, $avail0['product_id']);
        $this->assertSame(2.5, (float) $avail0['suggested_quantity']);
        $this->assertFalse($avail0['price_changed']);
        $this->assertSame(0.0, (float) $avail0['price_delta']);
        $this->assertTrue($avail0['allow_decimal']);
        $this->assertSame('assets/images/products/test-avocados.png', $avail0['image_path']);

        $avail1 = $data['available'][1];
        $this->assertSame($prodAvailablePriceChanged, $avail1['product_id']);
        $this->assertSame(2.0, (float) $avail1['suggested_quantity']);
        $this->assertTrue($avail1['price_changed']);
        $this->assertSame(12.5, (float) $avail1['price_delta']);
        $this->assertSame(92.5, (float) $avail1['unit_price']);
        $this->assertSame(80.0, (float) $avail1['previous_unit_price']);

        // Verify adjusted items (decimal 0.1 step vs whole unit step)
        $adj0 = $data['adjusted'][0];
        $this->assertSame($prodAdjustedKg, $adj0['product_id']);
        $this->assertSame(1.4, (float) $adj0['suggested_quantity']);
        $this->assertNotEmpty($adj0['reason']);

        $adj1 = $data['adjusted'][1];
        $this->assertSame($prodAdjustedUnit, $adj1['product_id']);
        $this->assertSame(2.0, (float) $adj1['suggested_quantity']);
        $this->assertNotEmpty($adj1['reason']);

        // Verify unavailable reasons
        $this->assertSame($prodInactive, $data['unavailable'][0]['product_id']);
        $this->assertStringContainsString('inactive', strtolower($data['unavailable'][0]['reason']));
        $this->assertSame($prodOutOfStock, $data['unavailable'][1]['product_id']);
        $this->assertStringContainsString('out of stock', strtolower($data['unavailable'][1]['reason']));

        // 8. Preview order with all unavailable items -> can_reorder === false
        $noneRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/customer/orders/{$orderAllUnavailableId}/reorder")
                ->withCookieParams([$cookieName => $tokenA])
        );
        $this->assertSame(200, $noneRes->getStatusCode());
        $noneBody = json_decode((string) $noneRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($noneBody['data']['summary']['can_reorder']);
        $this->assertCount(0, $noneBody['data']['available']);
        $this->assertCount(0, $noneBody['data']['adjusted']);
        $this->assertCount(1, $noneBody['data']['unavailable']);
    }

    private function insertTestProduct(
        int $categoryId,
        int $unitId,
        string $sku,
        string $slug,
        string $description,
        float $unitPrice,
        float $stockQuantity,
        int $isActive,
        ?string $imagePath
    ): int {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO products', [
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'sku' => $sku,
            'slug' => $slug,
            'description' => $description,
            'image_path' => $imagePath,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'stock_quantity' => number_format($stockQuantity, 2, '.', ''),
            'is_active' => $isActive,
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
            'notes' => 'Reorder integration test',
            'ordered_at' => $now,
            'received_at' => $now,
            'delivered_at' => $now,
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
