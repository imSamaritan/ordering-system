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

final class SupplierDashboardAndCrudTest extends TestCase
{
    private App $app;
    private Connection $db;
    private AuthService $authService;
    private ?int $buyerId = null;
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
        if ($this->buyerId !== null) {
            $this->db->query('DELETE FROM users WHERE id = %i', $this->buyerId);
        }
    }

    public function testSupplierAccessControlCompaniesOrderLifecycleAndProductCrud(): void
    {
        $reqFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();
        $cookieName = $this->authService->getCookieName();

        // 1. Unauthenticated access rejected
        $unauthWeb = $this->app->handle($reqFactory->createServerRequest('GET', '/supplier'));
        $this->assertSame(302, $unauthWeb->getStatusCode());
        $this->assertStringEndsWith('/login', $unauthWeb->getHeaderLine('Location'));

        $unauthInv = $this->app->handle($reqFactory->createServerRequest('GET', '/supplier/inventory'));
        $this->assertSame(302, $unauthInv->getStatusCode());

        $unauthApi = $this->app->handle($reqFactory->createServerRequest('GET', '/api/v1/supplier/companies'));
        $this->assertSame(401, $unauthApi->getStatusCode());

        // 2. Create a verified buyer company account and verify it gets 403 on supplier routes
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $buyerEmail = "phpunit-buyer-{$suffix}@example.co.za";

        $this->db->query('INSERT INTO users', [
            'role' => 'company',
            'company_name' => "Karoo Harvest Kitchen {$suffix}",
            'contact_name' => 'Naledi Khumalo',
            'email' => $buyerEmail,
            'phone' => '+27 82 555 0909',
            'location' => 'Johannesburg',
            'password_hash' => password_hash('BuyerPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->buyerId = $this->db->getInsertId();

        $buyerToken = $this->authService->issueToken([
            'id' => $this->buyerId,
            'role' => 'company',
            'email' => $buyerEmail,
        ]);

        $buyerSupplierWeb = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/supplier')
                ->withCookieParams([$cookieName => $buyerToken])
        );
        $this->assertSame(403, $buyerSupplierWeb->getStatusCode());

        $buyerSupplierApi = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/supplier/products')
                ->withCookieParams([$cookieName => $buyerToken])
        );
        $this->assertSame(403, $buyerSupplierApi->getStatusCode());

        // 3. Authenticate as seeded supplier
        $supplier = $this->db->query('SELECT * FROM users WHERE role = %s LIMIT 1', 'supplier')->fetch();
        $this->assertNotNull($supplier);
        $supplierToken = $this->authService->issueToken([
            'id' => (int) $supplier['id'],
            'role' => 'supplier',
            'email' => (string) $supplier['email'],
        ]);

        // Supplier web views render 200
        $supDashRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/supplier')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $supDashRes->getStatusCode());
        $this->assertStringContainsString("supplierDashboardPage('companies')", (string) $supDashRes->getBody());

        $supInvRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/supplier/inventory')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $supInvRes->getStatusCode());
        $this->assertStringContainsString("supplierDashboardPage('inventory')", (string) $supInvRes->getBody());

        // 4. Product CRUD: list, create, duplicate SKU rejection, update, delete
        $listProdRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/supplier/products')
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $listProdRes->getStatusCode());
        $listProdBody = json_decode((string) $listProdRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($listProdBody['success']);
        $this->assertNotEmpty($listProdBody['data']['categories']);
        $this->assertNotEmpty($listProdBody['data']['units']);

        $categoryId = (int) $listProdBody['data']['categories'][0]['id'];
        $kgUnit = null;
        foreach ($listProdBody['data']['units'] as $u) {
            if (strtolower((string) $u['name']) === 'kg') {
                $kgUnit = $u;
                break;
            }
        }
        $this->assertNotNull($kgUnit);
        $unitId = (int) $kgUnit['id'];

        $skuA = 'TEST-' . strtoupper($suffix) . '-A';
        $createRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/supplier/products')
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'description' => "Test Shiitake Mushrooms {$suffix}",
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'sku' => $skuA,
                    'unit_price' => 89.50,
                    'stock_quantity' => 25.0,
                    'is_active' => true,
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(201, $createRes->getStatusCode());
        $createBody = json_decode((string) $createRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $productAId = (int) $createBody['data']['product']['id'];
        $this->createdProductIds[] = $productAId;
        $this->assertSame(89.5, (float) $createBody['data']['product']['unit_price']);

        // Duplicate SKU rejected (422)
        $dupSkuRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/supplier/products')
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'description' => 'Duplicate SKU Product',
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'sku' => $skuA,
                    'unit_price' => 10.00,
                    'stock_quantity' => 5,
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $dupSkuRes->getStatusCode());

        // Update ZAR price and stock via PATCH
        $updateRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/supplier/products/{$productAId}")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'unit_price' => 94.75,
                    'stock_quantity' => 30.0,
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(200, $updateRes->getStatusCode());
        $updateBody = json_decode((string) $updateRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(94.75, (float) $updateBody['data']['product']['unit_price']);
        $this->assertSame(30.0, (float) $updateBody['data']['product']['stock_quantity']);

        // Create a second unreferenced product and hard-delete it
        $createBRes = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/supplier/products')
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'description' => "Temp Delete Product {$suffix}",
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'unit_price' => 15.00,
                    'stock_quantity' => 10,
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(201, $createBRes->getStatusCode());
        $productBId = (int) json_decode((string) $createBRes->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['product']['id'];

        $delBRes = $this->app->handle(
            $reqFactory->createServerRequest('DELETE', "/api/v1/supplier/products/{$productBId}")
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $delBRes->getStatusCode());
        $delBBody = json_decode((string) $delBRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('deleted', $delBBody['data']['action']);

        // 5. Place two real orders as the buyer using Product A (stock starts at 30.0)
        $order1Res = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/orders')
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $buyerToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'items' => [['product_id' => $productAId, 'quantity' => 2.0]],
                    'notes' => 'Deliver before 8am',
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(201, $order1Res->getStatusCode());
        $order1Id = (int) json_decode((string) $order1Res->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['order']['id'];
        $this->createdOrderIds[] = $order1Id;

        $order2Res = $this->app->handle(
            $reqFactory->createServerRequest('POST', '/api/v1/orders')
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $buyerToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'items' => [['product_id' => $productAId, 'quantity' => 3.5]],
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(201, $order2Res->getStatusCode());
        $order2Id = (int) json_decode((string) $order2Res->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['order']['id'];
        $this->createdOrderIds[] = $order2Id;

        // Stock should now be 30.0 - 2.0 - 3.5 = 24.5
        $stockAfterOrders = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $productAId)->fetchSingle();
        $this->assertSame(24.5, $stockAfterOrders);

        // Deleting Product A (referenced by order_items) should safely deactivate it instead of failing FK
        $delARes = $this->app->handle(
            $reqFactory->createServerRequest('DELETE', "/api/v1/supplier/products/{$productAId}")
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $delARes->getStatusCode());
        $delABody = json_decode((string) $delARes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('deactivated', $delABody['data']['action']);
        $this->assertFalse($delABody['data']['product']['is_active']);

        // 6. Supplier Companies API returns the buyer card with accurate stats
        $companiesRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', '/api/v1/supplier/companies')
                ->withQueryParams(['search' => $suffix])
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $companiesRes->getStatusCode());
        $companiesBody = json_decode((string) $companiesRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $companiesBody['data']['companies']);
        $card = $companiesBody['data']['companies'][0];
        $this->assertSame($this->buyerId, (int) $card['id']);
        $this->assertSame(2, (int) $card['total_orders']);
        $this->assertSame(2, (int) $card['pending_orders']);

        // 7. Company Orders API
        $compOrdersRes = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/supplier/companies/{$this->buyerId}/orders")
                ->withQueryParams(['status' => 'pending'])
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $this->assertSame(200, $compOrdersRes->getStatusCode());
        $compOrdersBody = json_decode((string) $compOrdersRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $compOrdersBody['data']['orders']);

        // 8. Transition Order 1: pending -> received -> in_progress -> delivered
        foreach (['received', 'in_progress', 'delivered'] as $nextStatus) {
            $transRes = $this->app->handle(
                $reqFactory->createServerRequest('PATCH', "/api/v1/supplier/orders/{$order1Id}/status")
                    ->withHeader('Content-Type', 'application/json')
                    ->withCookieParams([$cookieName => $supplierToken])
                    ->withBody($streamFactory->createStream(json_encode([
                        'status' => $nextStatus,
                        'note' => "Moved to {$nextStatus}",
                    ], JSON_THROW_ON_ERROR)))
            );
            $this->assertSame(200, $transRes->getStatusCode());
            $transBody = json_decode((string) $transRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($nextStatus, $transBody['data']['order']['status']);
            $this->assertTrue($transBody['data']['email_sent']);
        }

        // Verify received_at, delivered_at, status_history count (1 initial + 3 transitions = 4), and email_logs
        $detail1Res = $this->app->handle(
            $reqFactory->createServerRequest('GET', "/api/v1/supplier/orders/{$order1Id}")
                ->withCookieParams([$cookieName => $supplierToken])
        );
        $detail1Body = json_decode((string) $detail1Res->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($detail1Body['data']['order']['received_at']);
        $this->assertNotNull($detail1Body['data']['order']['delivered_at']);
        $this->assertCount(4, $detail1Body['data']['order']['status_history']);

        $statusEmailCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM email_logs WHERE order_id = %i AND email_type = %s AND status = %s',
            $order1Id,
            'order_status_update',
            'sent'
        )->fetchSingle();
        $this->assertSame(3, $statusEmailCount);

        // Terminal delivered order rejects further transition (422)
        $invalidTransRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/supplier/orders/{$order1Id}/status")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'status' => 'declined',
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(422, $invalidTransRes->getStatusCode());

        // 9. Decline Order 2 -> restores 3.5 kg stock back to 28.0 kg
        $declineRes = $this->app->handle(
            $reqFactory->createServerRequest('PATCH', "/api/v1/supplier/orders/{$order2Id}/status")
                ->withHeader('Content-Type', 'application/json')
                ->withCookieParams([$cookieName => $supplierToken])
                ->withBody($streamFactory->createStream(json_encode([
                    'status' => 'declined',
                    'note' => 'Out of seasonal harvest',
                ], JSON_THROW_ON_ERROR)))
        );
        $this->assertSame(200, $declineRes->getStatusCode());
        $declineBody = json_decode((string) $declineRes->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('declined', $declineBody['data']['order']['status']);
        $this->assertTrue($declineBody['data']['email_sent']);

        $stockAfterDecline = (float) $this->db->query('SELECT stock_quantity FROM products WHERE id = %i', $productAId)->fetchSingle();
        $this->assertSame(28.0, $stockAfterDecline);
    }
}
