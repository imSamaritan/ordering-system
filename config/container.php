<?php

declare(strict_types=1);

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

        $renderer = new PhpRenderer(dirname(__DIR__) . '/templates', [
            'appName' => (string) $app['name'],
            'basePath' => (string) $app['base_path'],
            'currency' => (string) $app['currency'],
            'currencySymbol' => (string) $app['currency_symbol'],
        ]);
        $renderer->setLayout('layouts/base.php');

        return $renderer;
    },
];
