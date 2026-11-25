<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Produce & Inventory Ordering System',
        'env' => $_ENV['APP_ENV'] ?? 'local',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'url' => rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost/projects/ordering-system'), '/'),
        'base_path' => rtrim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/'),
        'currency' => $_ENV['APP_CURRENCY'] ?? 'ZAR',
        'currency_symbol' => $_ENV['APP_CURRENCY_SYMBOL'] ?? 'R',
    ],
    'db' => [
        'driver' => 'mysqli',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_NAME'] ?? 'ordering_system',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? '',
        'ttl' => (int) ($_ENV['JWT_TTL_SECONDS'] ?? 86400),
        'cookie_name' => $_ENV['JWT_COOKIE_NAME'] ?? 'ordering_auth_token',
    ],
    'smtp' => [
        'host' => $_ENV['SMTP_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['SMTP_PORT'] ?? 1025),
        'user' => $_ENV['SMTP_USER'] ?? '',
        'pass' => $_ENV['SMTP_PASS'] ?? '',
        'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? '',
        'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@ordering-system.local',
        'from_name' => $_ENV['SMTP_FROM_NAME'] ?? 'Produce & Inventory Ordering System',
    ],
    'supplier' => [
        'email' => $_ENV['SUPPLIER_EMAIL'] ?? 'supplier@freshproduce.co.za',
    ],
];
