<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OrderService;
use App\Support\OrderNumberGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrderNumberAndQuantityTest extends TestCase
{
    public function testOrderNumberGeneratorFormatAndValidation(): void
    {
        $fixedDate = new DateTimeImmutable('2026-11-26 09:30:00');
        $orderNumber = OrderNumberGenerator::generate($fixedDate);

        $this->assertStringStartsWith('ORD-20261126-', $orderNumber);
        $this->assertTrue(OrderNumberGenerator::isValid($orderNumber));
        $this->assertFalse(OrderNumberGenerator::isValid('INVALID-123'));
        $this->assertFalse(OrderNumberGenerator::isValid('ORD-2026112-ABCDEF'));
    }

    public function testDecimalQuantityParsingInTenths(): void
    {
        $this->assertSame(1, OrderService::parseQuantityTenths(0.1, true));
        $this->assertSame(3, OrderService::parseQuantityTenths(0.3, true));
        $this->assertSame(15, OrderService::parseQuantityTenths('1.5', true));
        $this->assertSame(20, OrderService::parseQuantityTenths(2, true));

        // Reject zero, negative, non-numeric, and finer than 0.1 steps
        $this->assertNull(OrderService::parseQuantityTenths(0, true));
        $this->assertNull(OrderService::parseQuantityTenths(-0.5, true));
        $this->assertNull(OrderService::parseQuantityTenths(0.15, true));
        $this->assertNull(OrderService::parseQuantityTenths('abc', true));
    }

    public function testWholeUnitQuantityParsing(): void
    {
        $this->assertSame(10, OrderService::parseQuantityTenths(1, false));
        $this->assertSame(30, OrderService::parseQuantityTenths('3', false));
        $this->assertSame(20, OrderService::parseQuantityTenths(2.0, false));

        // Reject fractional quantities for Unit / Punnet items
        $this->assertNull(OrderService::parseQuantityTenths(0.1, false));
        $this->assertNull(OrderService::parseQuantityTenths(1.5, false));
        $this->assertNull(OrderService::parseQuantityTenths(0, false));
    }
}
