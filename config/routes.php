<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\CustomerDashboardController;
use App\Controllers\OrderController;
use App\Controllers\ReportController;
use App\Controllers\SupplierDashboardController;
use App\Middleware\JwtCookieAuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Support\ResponseHelper;
use Dibi\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

return static function (App $app): void {
    $app->get('/', function (Request $request, Response $response): Response {
        /** @var PhpRenderer $view */
        $view = $this->get(PhpRenderer::class);

        return $view->render($response, 'home.php', [
            'title' => 'Fresh Produce Storefront',
            'pageScript' => 'pages/storefront.js',
        ]);
    });

    // Auth web views
    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->get('/register', [AuthController::class, 'showRegister']);
    $app->get('/verify-email', [AuthController::class, 'showVerifyEmail']);
    $app->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
    $app->get('/reset-password', [AuthController::class, 'showResetPassword']);

    // Customer / Company Dashboard web view
    $app->get('/dashboard', [CustomerDashboardController::class, 'showDashboard'])
        ->add(new RoleMiddleware('customer', 'company'))
        ->add(JwtCookieAuthMiddleware::class);

    // Supplier Dashboard & Inventory web views
    $app->get('/supplier', [SupplierDashboardController::class, 'showDashboard'])
        ->add(new RoleMiddleware('supplier'))
        ->add(JwtCookieAuthMiddleware::class);
    $app->get('/supplier/inventory', [SupplierDashboardController::class, 'showInventory'])
        ->add(new RoleMiddleware('supplier'))
        ->add(JwtCookieAuthMiddleware::class);

    $app->group('/api/v1', function (RouteCollectorProxy $group): void {
        $group->get('/health', function (Request $request, Response $response): Response {
            /** @var array<string, mixed> $settings */
            $settings = $this->get('settings');
            /** @var Connection $db */
            $db = $this->get(Connection::class);

            $dbStatus = 'ok';
            try {
                $db->query('SELECT 1')->fetchSingle();
            } catch (Throwable) {
                $dbStatus = 'error';
            }

            return ResponseHelper::json($response, [
                'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
                'database' => $dbStatus,
                'currency' => $settings['app']['currency'] ?? 'ZAR',
                'currency_symbol' => $settings['app']['currency_symbol'] ?? 'R',
            ]);
        });

        // Public Auth API endpoints
        $group->post('/auth/register', [AuthController::class, 'register']);
        $group->get('/auth/verify-email', [AuthController::class, 'verifyEmailApi']);
        $group->post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
        $group->post('/auth/login', [AuthController::class, 'login']);
        $group->post('/auth/logout', [AuthController::class, 'logout']);
        $group->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        $group->post('/auth/reset-password', [AuthController::class, 'resetPassword']);

        // Authenticated Auth API endpoints
        $group->get('/auth/me', [AuthController::class, 'me'])
            ->add(JwtCookieAuthMiddleware::class);

        // Public Storefront Catalog API endpoints
        $group->get('/categories', [CatalogController::class, 'categories']);
        $group->get('/products', [CatalogController::class, 'products']);

        // Customer / Company Order Placement
        $group->post('/orders', [OrderController::class, 'create'])
            ->add(new RoleMiddleware('customer', 'company'))
            ->add(JwtCookieAuthMiddleware::class);

        // Customer / Company Dashboard & PDF Statements
        $group->get('/customer/orders', [CustomerDashboardController::class, 'orders'])
            ->add(new RoleMiddleware('customer', 'company'))
            ->add(JwtCookieAuthMiddleware::class);
        $group->get('/customer/orders/{id:[0-9]+}', [CustomerDashboardController::class, 'orderDetail'])
            ->add(new RoleMiddleware('customer', 'company'))
            ->add(JwtCookieAuthMiddleware::class);
        $group->get('/customer/reports/pdf', [ReportController::class, 'customerStatementPdf'])
            ->add(new RoleMiddleware('customer', 'company'))
            ->add(JwtCookieAuthMiddleware::class);

        // Supplier Portal & Inventory CRUD API endpoints
        $group->group('/supplier', function (RouteCollectorProxy $supplier): void {
            $supplier->get('/companies', [SupplierDashboardController::class, 'companies']);
            $supplier->get('/companies/{id:[0-9]+}/orders', [SupplierDashboardController::class, 'companyOrders']);
            $supplier->get('/orders/{id:[0-9]+}', [SupplierDashboardController::class, 'orderDetail']);
            $supplier->patch('/orders/{id:[0-9]+}/status', [SupplierDashboardController::class, 'updateOrderStatus']);

            $supplier->get('/products', [SupplierDashboardController::class, 'products']);
            $supplier->post('/products', [SupplierDashboardController::class, 'createProduct']);
            $supplier->patch('/products/{id:[0-9]+}', [SupplierDashboardController::class, 'updateProduct']);
            $supplier->delete('/products/{id:[0-9]+}', [SupplierDashboardController::class, 'deleteProduct']);
        })
            ->add(new RoleMiddleware('supplier'))
            ->add(JwtCookieAuthMiddleware::class);
    });
};
