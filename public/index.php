<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require dirname(__DIR__) . '/config/container.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

/** @var array<string, mixed> $settings */
$settings = $container->get('settings');
$basePath = (string) ($settings['app']['base_path'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
if ($basePath !== '' && str_starts_with($requestUri, $basePath)) {
    $app->setBasePath($basePath);
}

(require dirname(__DIR__) . '/config/middleware.php')($app);
(require dirname(__DIR__) . '/config/routes.php')($app);

$app->run();
