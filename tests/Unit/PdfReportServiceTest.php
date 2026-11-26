<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PdfReportService;
use PHPUnit\Framework\TestCase;

final class PdfReportServiceTest extends TestCase
{
    public function testRenderHtmlAndGeneratePdfForDeliveredAndDeclinedOrders(): void
    {
        $service = new PdfReportService('Fresh Produce Ordering', 'R', 'ZAR');

        $user = [
            'company_name' => 'Green Valley Bistro',
            'contact_name' => 'Naledi Khumalo',
            'email' => 'naledi@greenvalley.co.za',
            'phone' => '+27 11 555 0199',
            'location' => 'Johannesburg',
        ];

        $orders = [
            [
                'id' => 101,
                'order_number' => 'ORD-20260115-AAA111',
                'status' => 'delivered',
                'total_amount' => 145.50,
                'notes' => 'Deliver to kitchen door',
                'ordered_at' => '2026-01-15 08:30:00',
                'items' => [
                    [
                        'product_name_snapshot' => 'Mushroom',
                        'unit_name_snapshot' => 'kg',
                        'unit_price_snapshot' => 85.00,
                        'quantity' => 0.3,
                        'line_total' => 25.50,
                    ],
                    [
                        'product_name_snapshot' => 'Eggs Large (30) - Free Range',
                        'unit_name_snapshot' => 'Unit',
                        'unit_price_snapshot' => 120.00,
                        'quantity' => 1.0,
                        'line_total' => 120.00,
                    ],
                ],
            ],
            [
                'id' => 102,
                'order_number' => 'ORD-20260210-BBB222',
                'status' => 'declined',
                'total_amount' => 45.00,
                'notes' => 'Out of seasonal stock',
                'ordered_at' => '2026-02-10 10:15:00',
                'items' => [
                    [
                        'product_name_snapshot' => 'Avocado',
                        'unit_name_snapshot' => 'Unit',
                        'unit_price_snapshot' => 15.00,
                        'quantity' => 3.0,
                        'line_total' => 45.00,
                    ],
                ],
            ],
        ];

        $html = $service->renderCustomerStatementHtml($user, $orders, [
            'date_from' => '2026-01-01',
            'date_to' => '2026-02-28',
        ]);

        $this->assertStringContainsString('Green Valley Bistro (Naledi Khumalo)', $html);
        $this->assertStringContainsString('ORD-20260115-AAA111', $html);
        $this->assertStringContainsString('ORD-20260210-BBB222', $html);
        $this->assertStringContainsString('0.3 kg', $html);
        $this->assertStringContainsString('R145.50', $html);
        $this->assertStringContainsString('R45.00', $html);
        $this->assertStringContainsString('R190.50', $html);
        $this->assertStringContainsString('2026-01-01 to 2026-02-28', $html);

        $pdf = $service->generateCustomerStatementPdf($user, $orders);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function testGeneratePdfHandlesEmptyOrderListGracefully(): void
    {
        $service = new PdfReportService();

        $user = [
            'company_name' => null,
            'contact_name' => 'Empty Statement User',
            'email' => 'empty@example.co.za',
            'phone' => null,
            'location' => null,
        ];

        $html = $service->renderCustomerStatementHtml($user, []);
        $this->assertStringContainsString('No orders matched the selected statement filters.', $html);

        $pdf = $service->generateCustomerStatementPdf($user, []);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }
}
