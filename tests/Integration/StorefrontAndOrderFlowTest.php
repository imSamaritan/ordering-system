<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuthService;
use App\Support\OrderNumberGenerator;
use DI\ContainerBuilder;
use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class StorefrontAndOrderFlowTest extends TestCase
{
    private App $app;
    private Connection $db;
    private AuthService $authService;
    private string $customerEmail;
    private ?int $customerUserId = null;
    private ?int $createdOrderId = null;
    /** @var array<int, float> */
    private array $originalStocks = [];

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
        $this->customerEmail = 'phpunit-order-' . bin2hex(random_bytes(5)) . '@example.co.za';
    }

    protected function tearDown(): void
    {
        if ($this->createdOrderId !== null) {
            $this->db->query('DELETE FROM orders WHERE id = %i', $this->createdOrderId);
        }
        foreach ($this->originalStocks as $productId => $stock) {
            $this->db->query(
                'UPDATE products SET stock_quantity = %f WHERE id = %i',
                $stock,
                $productId
            );
        }
        if ($this->customerUserId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->customerUserId);
        }
    }

    public function testCatalogCategoriesAndProductsEndpoints(): void
    {
        $reqFactory = new ServerRequestFactory();

        // 1. Categories endpoint
        $catRes = $this->app->handle($reqFactory->createServerRequest('GET', '/api/v1/categories'));
        $this->assertSame(200, $catRes->getStatusCode());
        $catBody = json_decode((string) $catRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($catBody['success']);
        $this->assertCount(5, $catBody['data']['categories']);

        $totalCount = array_sum(array_column($catBody['data']['categories'], 'product_count'));
        $this->assertSame(98, $totalCount);

        // 2. All active products
        $allRes = $this->app->handle($reqFactory->createServerRequest('GET', '/api/v1/products'));
        $this->assertSame(200, $allRes->getStatusCode());
        $allBody = json_decode((string) $allRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(98, $allBody['data']['count']);

        // 3. Category filter (mushrooms -> 1 kg item)
        $mushReq = $reqFactory->createServerRequest('GET', '/api/v1/products')
            ->withQueryParams(['category' => 'mushrooms']);
        $mushRes = $this->app->handle($mushReq);
        $mushBody = json_decode((string) $mushRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $mushBody['data']['count']);
        $this->assertTrue($mushBody['data']['products'][0]['allow_decimal']);
        $this->assertSame(0.1, $mushBody['data']['products'][0]['step_size']);

        // 4. Search filter
        $eggsReq = $reqFactory->createServerRequest('GET', '/api/v1/products')
            ->withQueryParams(['q' => 'Eggs Large']);
        $eggsRes = $this->app->handle($eggsReq);
        $eggsBody = json_decode((string) $eggsRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $eggsBody['data']['count']);
    }

    public function testOrderPlacementAuthRoleValidationAndDecimalCheckout(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();
        $cookieName = $this->authService->getCookieName();

        // Pick one decimal (kg) product and one integer (Unit) product
        $kgProduct = $this->db->query(
            'SELECT p.id, p.unit_price, p.stock_quantity, p.description
             FROM products p INNER JOIN units u ON u.id = p.unit_id
             WHERE p.is_active = 1 AND u.allow_decimal = 1 LIMIT 1'
        )->fetch();
        $unitProduct = $this->db->query(
            'SELECT p.id, p.unit_price, p.stock_quantity, p.description
             FROM products p INNER JOIN units u ON u.id = p.unit_id
             WHERE p.is_active = 1 AND u.allow_decimal = 0 LIMIT 1'
        )->fetch();

        $this->assertNotNull($kgProduct);
        $this->assertNotNull($unitProduct);

        $kgId = (int) $kgProduct['id'];
        $unitId = (int) $unitProduct['id'];
        $this->originalStocks[$kgId] = (float) $kgProduct['stock_quantity'];
        $this->originalStocks[$unitId] = (float) $unitProduct['stock_quantity'];

        // 1. Unauthenticated order rejected (401)
        $unauthReq = $reqFactory->createServerRequest('POST', '/api/v1/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'items' => [['product_id' => $kgId, 'quantity' => 0.3]],
            ], JSON_THROW_ON_ERROR)));
        $unauthRes = $this->app->handle($unauthReq);
        $this->assertSame(401, $unauthRes->getStatusCode());

        // 2. Supplier role rejected from customer checkout (403)
        $supplier = $this->db->query('SELECT * FROM users WHERE role = %s LIMIT 1', 'supplier')->fetch();
        $this->assertNotNull($supplier);
        $supplierToken = $this->authService->issueToken([
            'id' => (int) $supplier['id'],
            'role' => 'supplier',
            'email' => (string) $supplier['email'],
        ]);
        $supplierReq = $reqFactory->createServerRequest('POST', '/api/v1/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withCookieParams([$cookieName => $supplierToken])
            ->withBody($streamFactory->createStream(json_encode([
                'items' => [['product_id' => $kgId, 'quantity' => 0.3]],
            ], JSON_THROW_ON_ERROR)));
        $supplierRes = $this->app->handle($supplierReq);
        $this->assertSame(403, $supplierRes->getStatusCode());

        // Create verified customer user
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Checkout Tester',
            'email' => $this->customerEmail,
            'phone' => '+27 82 555 0101',
            'location' => 'Sandton',
            'password_hash' => password_hash('OrderPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->customerUserId = $this->db->getInsertId();

        $customerToken = $this->authService->issueToken([
            'id' => $this->customerUserId,
            'role' => 'customer',
            'email' => $this->customerEmail,
        ]);

        // 3. Fractional quantity on whole-unit product rejected (422)
        $badStepReq = $reqFactory->createServerRequest('POST', '/api/v1/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withCookieParams([$cookieName => $customerToken])
            ->withBody($streamFactory->createStream(json_encode([
                'items' => [['product_id' => $unitId, 'quantity' => 0.5]],
            ], JSON_THROW_ON_ERROR)));
        $badStepRes = $this->app->handle($badStepReq);
        $this->assertSame(422, $badStepRes->getStatusCode());

        // 4. Valid mixed order (0.3 kg + 2 Unit) succeeds (201)
        $validPayload = json_encode([
            'items' => [
                ['product_id' => $kgId, 'quantity' => 0.3],
                ['product_id' => $unitId, 'quantity' => 2],
            ],
            'notes' => 'Leave with kitchen receiving.',
        ], JSON_THROW_ON_ERROR);

        $orderReq = $reqFactory->createServerRequest('POST', '/api/v1/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withCookieParams([$cookieName => $customerToken])
            ->withBody($streamFactory->createStream($validPayload));
        $orderRes = $this->app->handle($orderReq);
        $this->assertSame(201, $orderRes->getStatusCode());

        $orderBody = json_decode((string) $orderRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($orderBody['success']);
        $order = $orderBody['data']['order'];
        $this->createdOrderId = (int) $order['id'];

        $this->assertTrue(OrderNumberGenerator::isValid((string) $order['order_number']));
        $this->assertSame('pending', $order['status']);
        $this->assertCount(2, $order['items']);

        $expectedTotal = (round((float) $kgProduct['unit_price'] * 3 * 10) + round((float) $unitProduct['unit_price'] * 20 * 10)) / 100;
        $this->assertSame($expectedTotal, (float) $order['total_amount']);

        // Confirm stock decremented accurately
        $newKgStock = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $kgId)->fetchSingle();
        $this->assertEqualsWithDelta($this->originalStocks[$kgId] - 0.3, $newKgStock, 0.001);

        // Confirm status history and email logs recorded
        $historyCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM order_status_history WHERE order_id = %i AND new_status = %s',
            $this->createdOrderId,
            'pending'
        )->fetchSingle();
        $this->assertSame(1, $historyCount);

        $emailCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM email_logs WHERE order_id = %i',
            $this->createdOrderId
        )->fetchSingle();
        $this->assertSame(2, $emailCount);
    }
}
