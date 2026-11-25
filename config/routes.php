<?php

declare(strict_types=1);

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
    });
};
