<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InventoryService;
use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

final class OrderLifecycleAndInventoryHelperTest extends TestCase
{
    public function testOrderStatusTransitionMatrix(): void
    {
        // Allowed forward transitions
        $this->assertTrue(OrderService::canTransition('pending', 'received'));
        $this->assertTrue(OrderService::canTransition('pending', 'in_progress'));
        $this->assertTrue(OrderService::canTransition('pending', 'delivered'));
        $this->assertTrue(OrderService::canTransition('pending', 'declined'));

        $this->assertTrue(OrderService::canTransition('received', 'in_progress'));
        $this->assertTrue(OrderService::canTransition('received', 'delivered'));
        $this->assertTrue(OrderService::canTransition('received', 'declined'));

        $this->assertTrue(OrderService::canTransition('in_progress', 'delivered'));
        $this->assertTrue(OrderService::canTransition('in_progress', 'declined'));

        // Disallowed backward, self, and terminal transitions
        $this->assertFalse(OrderService::canTransition('pending', 'pending'));
        $this->assertFalse(OrderService::canTransition('received', 'pending'));
        $this->assertFalse(OrderService::canTransition('received', 'received'));
        $this->assertFalse(OrderService::canTransition('in_progress', 'received'));
        $this->assertFalse(OrderService::canTransition('delivered', 'in_progress'));
        $this->assertFalse(OrderService::canTransition('delivered', 'declined'));
        $this->assertFalse(OrderService::canTransition('declined', 'received'));
        $this->assertFalse(OrderService::canTransition('declined', 'delivered'));
        $this->assertFalse(OrderService::canTransition('unknown', 'received'));
    }

    public function testStatusLabelsAndInventoryHelpers(): void
    {
        $this->assertSame('Pending', OrderService::formatStatusLabel('pending'));
        $this->assertSame('Received', OrderService::formatStatusLabel('received'));
        $this->assertSame('In Progress', OrderService::formatStatusLabel('in_progress'));
        $this->assertSame('Delivered', OrderService::formatStatusLabel('delivered'));
        $this->assertSame('Declined', OrderService::formatStatusLabel('declined'));

        $this->assertSame('oyster-mushrooms-fresh', InventoryService::slugify('Oyster Mushrooms (Fresh)!'));
        $this->assertSame('eggs-large-30', InventoryService::slugify('  Eggs Large (30)  '));

        $this->assertSame(0.0, InventoryService::parseNonNegativeDecimal(0));
        $this->assertSame(45.5, InventoryService::parseNonNegativeDecimal('45.50'));
        $this->assertNull(InventoryService::parseNonNegativeDecimal(-1));
        $this->assertNull(InventoryService::parseNonNegativeDecimal('abc'));
        $this->assertNull(InventoryService::parseNonNegativeDecimal(null));
    }
}
