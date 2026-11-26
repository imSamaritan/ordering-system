<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class OrderNumberGenerator
{
    public const PREFIX = 'ORD';

    public static function generate(?DateTimeImmutable $now = null): string
    {
        $datePart = ($now ?? new DateTimeImmutable())->format('Ymd');
        $randomPart = strtoupper(bin2hex(random_bytes(3)));

        return sprintf('%s-%s-%s', self::PREFIX, $datePart, $randomPart);
    }

    public static function isValid(string $orderNumber): bool
    {
        return preg_match('/^ORD-\d{8}-[A-F0-9]{6}$/', $orderNumber) === 1;
    }
}
