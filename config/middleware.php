<?php

declare(strict_types=1);

use Slim\App;

return static function (App $app): void {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    /** @var array<string, mixed> $settings */
    $settings = $app->getContainer()?->get('settings') ?? [];
    $displayErrorDetails = (bool) ($settings['app']['debug'] ?? false);

    $app->addErrorMiddleware($displayErrorDetails, true, true);
};
