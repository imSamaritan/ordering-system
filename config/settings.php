<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }

    return $default;
};

return [
    'app' => [
        'name' => (string) $env('APP_NAME', 'Fresh Produce'),
        'env' => (string) $env('APP_ENV', 'local'),
        'debug' => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'url' => rtrim((string) $env('APP_URL', 'http://localhost/projects/ordering-system'), '/'),
        'base_path' => rtrim((string) $env('APP_BASE_PATH', ''), '/'),
        'currency' => (string) $env('APP_CURRENCY', 'ZAR'),
        'currency_symbol' => (string) $env('APP_CURRENCY_SYMBOL', 'R'),
    ],
    'db' => [
        'driver' => 'mysqli',
        'host' => (string) $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', 3306),
        'database' => (string) $env('DB_NAME', 'ordering_system'),
        'username' => (string) $env('DB_USER', 'root'),
        'password' => (string) ($env('DB_PASS', '') ?? ''),
        'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
    ],
    'jwt' => [
        'secret' => (string) $env('JWT_SECRET', ''),
        'ttl' => (int) $env('JWT_TTL_SECONDS', 86400),
        'cookie_name' => (string) $env('JWT_COOKIE_NAME', 'ordering_auth_token'),
    ],
    'smtp' => [
        'host' => trim((string) $env('SMTP_HOST', '127.0.0.1')),
        'port' => (int) $env('SMTP_PORT', 1025),
        'user' => trim((string) ($env('SMTP_USER', '') ?? '')),
        'pass' => (string) ($env('SMTP_PASS', '') ?? ''),
        'encryption' => strtolower(trim((string) ($env('SMTP_ENCRYPTION', '') ?? ''))),
        'from_email' => trim((string) $env('SMTP_FROM_EMAIL', 'no-reply@ordering-system.local')),
        'from_name' => trim((string) $env('SMTP_FROM_NAME', 'Produce & Inventory Ordering System')),
        'reply_to' => trim((string) ($env('SMTP_REPLY_TO', '') ?? '')),
        'timeout' => (int) $env('SMTP_TIMEOUT', 15),
        'verify_peer' => filter_var($env('SMTP_VERIFY_PEER', 'true'), FILTER_VALIDATE_BOOLEAN),
        'dry_run' => filter_var($env('MAIL_DRY_RUN', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],
    'supplier' => [
        'email' => trim((string) $env('SUPPLIER_EMAIL', 'no-reply@asamaritan-order-management-sys.co.za')),
    ],
];
