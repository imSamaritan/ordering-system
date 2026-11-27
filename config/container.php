<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\CustomerDashboardController;
use App\Controllers\OrderController;
use App\Controllers\ReportController;
use App\Controllers\SupplierDashboardController;
use App\Middleware\JwtCookieAuthMiddleware;
use App\Repositories\EmailLogRepository;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PasswordResetService;
use App\Services\PdfReportService;
use App\Services\VerificationService;
use App\Support\ViewRenderer;
use Dibi\Connection;
use Psr\Container\ContainerInterface;
use Slim\Views\PhpRenderer;

return [
    'settings' => static fn (): array => require __DIR__ . '/settings.php',

    Connection::class => static function (ContainerInterface $container): Connection {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');
        /** @var array<string, mixed> $db */
        $db = $settings['db'];

        return new Connection([
            'driver' => $db['driver'],
            'host' => $db['host'],
            'port' => $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
            'charset' => $db['charset'],
            'lazy' => true,
        ]);
    },

    PhpRenderer::class => static function (ContainerInterface $container): PhpRenderer {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');
        /** @var array<string, mixed> $app */
        $app = $settings['app'];

        $renderer = new ViewRenderer(dirname(__DIR__) . '/templates/', [
            'appName' => (string) $app['name'],
            'basePath' => (string) $app['base_path'],
            'currency' => (string) $app['currency'],
            'currencySymbol' => (string) $app['currency_symbol'],
        ]);
        $renderer->setLayout('layouts/base.php');

        return $renderer;
    },

    UserRepository::class => static fn (ContainerInterface $c): UserRepository => new UserRepository(
        $c->get(Connection::class)
    ),

    EmailVerificationRepository::class => static fn (ContainerInterface $c): EmailVerificationRepository => new EmailVerificationRepository(
        $c->get(Connection::class)
    ),

    PasswordResetRepository::class => static fn (ContainerInterface $c): PasswordResetRepository => new PasswordResetRepository(
        $c->get(Connection::class)
    ),

    EmailLogRepository::class => static fn (ContainerInterface $c): EmailLogRepository => new EmailLogRepository(
        $c->get(Connection::class)
    ),

    EmailService::class => static function (ContainerInterface $container): EmailService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');
        $smtp = (array) ($settings['smtp'] ?? []);
        $dryRun = (bool) ($smtp['dry_run'] ?? false);

        return new EmailService(
            $smtp,
            $container->get(EmailLogRepository::class),
            $dryRun
        );
    },

    VerificationService::class => static function (ContainerInterface $container): VerificationService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        return new VerificationService(
            $container->get(EmailVerificationRepository::class),
            $container->get(UserRepository::class),
            $container->get(EmailService::class),
            (string) ($settings['app']['url'] ?? 'http://localhost/projects/ordering-system'),
            (string) ($settings['app']['name'] ?? 'Produce & Inventory Ordering System')
        );
    },

    PasswordResetService::class => static function (ContainerInterface $container): PasswordResetService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        return new PasswordResetService(
            $container->get(Connection::class),
            $container->get(PasswordResetRepository::class),
            $container->get(UserRepository::class),
            $container->get(EmailService::class),
            (string) ($settings['app']['url'] ?? 'http://localhost/projects/ordering-system'),
            (string) ($settings['app']['name'] ?? 'Produce & Inventory Ordering System'),
            (bool) ($settings['app']['debug'] ?? false)
        );
    },

    AuthService::class => static function (ContainerInterface $container): AuthService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');
        /** @var array<string, mixed> $jwt */
        $jwt = $settings['jwt'];

        return new AuthService(
            $container->get(UserRepository::class),
            $container->get(VerificationService::class),
            (string) ($jwt['secret'] ?? ''),
            (int) ($jwt['ttl'] ?? 86400),
            (string) ($jwt['cookie_name'] ?? 'ordering_auth_token'),
            (bool) ($settings['app']['debug'] ?? false)
        );
    },

    JwtCookieAuthMiddleware::class => static function (ContainerInterface $container): JwtCookieAuthMiddleware {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        return new JwtCookieAuthMiddleware(
            $container->get(AuthService::class),
            $container->get(UserRepository::class),
            (string) ($settings['app']['base_path'] ?? '')
        );
    },

    AuthController::class => static fn (ContainerInterface $c): AuthController => new AuthController(
        $c->get(AuthService::class),
        $c->get(VerificationService::class),
        $c->get(PasswordResetService::class),
        $c->get(PhpRenderer::class)
    ),

    ProductRepository::class => static fn (ContainerInterface $c): ProductRepository => new ProductRepository(
        $c->get(Connection::class)
    ),

    OrderRepository::class => static fn (ContainerInterface $c): OrderRepository => new OrderRepository(
        $c->get(Connection::class)
    ),

    OrderService::class => static function (ContainerInterface $container): OrderService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        return new OrderService(
            $container->get(Connection::class),
            $container->get(ProductRepository::class),
            $container->get(OrderRepository::class),
            $container->get(EmailService::class),
            (string) ($settings['supplier']['email'] ?? 'supplier@freshproduce.co.za'),
            (string) ($settings['app']['currency_symbol'] ?? 'R'),
            (string) ($settings['app']['name'] ?? 'Produce & Inventory Ordering System')
        );
    },

    CatalogController::class => static fn (ContainerInterface $c): CatalogController => new CatalogController(
        $c->get(ProductRepository::class)
    ),

    OrderController::class => static fn (ContainerInterface $c): OrderController => new OrderController(
        $c->get(OrderService::class)
    ),

    PdfReportService::class => static function (ContainerInterface $container): PdfReportService {
        /** @var array<string, mixed> $settings */
        $settings = $container->get('settings');

        return new PdfReportService(
            (string) ($settings['app']['name'] ?? 'Produce & Inventory Ordering System'),
            (string) ($settings['app']['currency_symbol'] ?? 'R'),
            (string) ($settings['app']['currency'] ?? 'ZAR')
        );
    },

    CustomerDashboardController::class => static fn (ContainerInterface $c): CustomerDashboardController => new CustomerDashboardController(
        $c->get(OrderRepository::class),
        $c->get(ProductRepository::class),
        $c->get(PhpRenderer::class)
    ),

    ReportController::class => static fn (ContainerInterface $c): ReportController => new ReportController(
        $c->get(OrderRepository::class),
        $c->get(PdfReportService::class)
    ),

    InventoryService::class => static fn (ContainerInterface $c): InventoryService => new InventoryService(
        $c->get(ProductRepository::class)
    ),

    SupplierDashboardController::class => static fn (ContainerInterface $c): SupplierDashboardController => new SupplierDashboardController(
        $c->get(UserRepository::class),
        $c->get(OrderRepository::class),
        $c->get(OrderService::class),
        $c->get(InventoryService::class),
        $c->get(PhpRenderer::class)
    ),
];
