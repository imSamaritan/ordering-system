<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfReportService
{
    public function __construct(
        private readonly string $appName = 'Produce & Inventory Ordering System',
        private readonly string $currencySymbol = 'R',
        private readonly string $currency = 'ZAR'
    ) {
    }

    /**
     * Renders a customer order statement PDF (binary string) using Dompdf.
     *
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $orders Orders with nested 'items'
     * @param array<string, mixed> $meta
     */
    public function generateCustomerStatementPdf(array $user, array $orders, array $meta = []): string
    {
        $html = $this->renderCustomerStatementHtml($user, $orders, $meta);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Builds self-contained HTML for the customer PDF statement.
     *
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $orders
     * @param array<string, mixed> $meta
     */
    public function renderCustomerStatementHtml(array $user, array $orders, array $meta = []): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $fmtMoney = fn (float $amount): string => $this->currencySymbol . number_format($amount, 2, '.', ',');

        $title = (string) ($meta['title'] ?? 'Customer Order Statement (Complete / Delivered & Declined)');
        $generatedAt = (string) ($meta['generated_at'] ?? date('Y-m-d H:i'));
        $dateFrom = (string) ($meta['date_from'] ?? '');
        $dateTo = (string) ($meta['date_to'] ?? '');
        $orderNumberFilter = (string) ($meta['order_number'] ?? '');
        $scopeLabel = (string) ($meta['scope_label'] ?? 'Complete / Delivered & Declined');

        $deliveredCount = 0;
        $deliveredSpend = 0.0;
        $declinedCount = 0;
        $declinedTotal = 0.0;
        $otherCount = 0;
        $otherTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            $amount = (float) ($order['total_amount'] ?? 0);
            $grandTotal += $amount;

            if ($status === 'delivered') {
                $deliveredCount++;
                $deliveredSpend += $amount;
            } elseif ($status === 'declined') {
                $declinedCount++;
                $declinedTotal += $amount;
            } else {
                $otherCount++;
                $otherTotal += $amount;
            }
        }

        $periodText = 'All Dates';
        if ($dateFrom !== '' && $dateTo !== '') {
            $periodText = "{$dateFrom} to {$dateTo}";
        } elseif ($dateFrom !== '') {
            $periodText = "From {$dateFrom}";
        } elseif ($dateTo !== '') {
            $periodText = "Up to {$dateTo}";
        }

        $accountName = trim((string) ($user['company_name'] ?? ''));
        $contactName = (string) ($user['contact_name'] ?? 'Customer');
        $displayAccount = $accountName !== '' ? "{$accountName} ({$contactName})" : $contactName;
        $email = (string) ($user['email'] ?? '');
        $phone = (string) ($user['phone'] ?? '—');
        $location = (string) ($user['location'] ?? '—');

        $ordersHtml = '';
        if ($orders === []) {
            $ordersHtml = '<div class="empty-box">No orders matched the selected statement filters.</div>';
        } else {
            foreach ($orders as $order) {
                $status = (string) ($order['status'] ?? 'pending');
                $badgeClass = match ($status) {
                    'delivered' => 'badge-delivered',
                    'declined' => 'badge-declined',
                    'in_progress' => 'badge-progress',
                    'received' => 'badge-received',
                    default => 'badge-pending',
                };
                $statusLabel = match ($status) {
                    'delivered' => 'Delivered (Complete)',
                    'declined' => 'Declined',
                    'in_progress' => 'In Progress',
                    'received' => 'Received',
                    default => 'Pending',
                };

                $rowsHtml = '';
                /** @var list<array<string, mixed>> $items */
                $items = is_array($order['items'] ?? null) ? $order['items'] : [];
                foreach ($items as $item) {
                    $qty = (float) ($item['quantity'] ?? 0);
                    $unitName = (string) ($item['unit_name_snapshot'] ?? 'Unit');
                    $qtyDisplay = strtolower($unitName) === 'kg'
                        ? number_format($qty, 1, '.', '') . ' kg'
                        : number_format($qty, 0, '.', '') . ' ' . $unitName;

                    $rowsHtml .= '<tr>'
                        . '<td>' . $e((string) ($item['product_name_snapshot'] ?? '')) . '</td>'
                        . '<td class="num">' . $e($qtyDisplay) . '</td>'
                        . '<td class="num">' . $e($fmtMoney((float) ($item['unit_price_snapshot'] ?? 0))) . '</td>'
                        . '<td class="num">' . $e($fmtMoney((float) ($item['line_total'] ?? 0))) . '</td>'
                        . '</tr>';
                }

                $notesHtml = '';
                if (!empty($order['notes'])) {
                    $notesHtml = '<div class="order-notes"><strong>Notes:</strong> ' . $e((string) $order['notes']) . '</div>';
                }

                $ordersHtml .= '<div class="order-card">'
                    . '<table class="order-header-table"><tr>'
                    . '<td><span class="order-num">' . $e((string) ($order['order_number'] ?? '')) . '</span>'
                    . '<div class="order-date">Ordered: ' . $e((string) ($order['ordered_at'] ?? '')) . '</div></td>'
                    . '<td class="right"><span class="badge ' . $badgeClass . '">' . $e($statusLabel) . '</span>'
                    . '<div class="order-total">' . $e($fmtMoney((float) ($order['total_amount'] ?? 0))) . '</div></td>'
                    . '</tr></table>'
                    . '<table class="items-table">'
                    . '<thead><tr><th>Item Description</th><th class="num">Quantity</th><th class="num">Unit Price</th><th class="num">Line Total</th></tr></thead>'
                    . '<tbody>' . $rowsHtml . '</tbody>'
                    . '</table>'
                    . $notesHtml
                    . '</div>';
            }
        }

        $orderFilterRow = $orderNumberFilter !== ''
            ? '<tr><td class="label">Order # Filter:</td><td>' . $e($orderNumberFilter) . '</td></tr>'
            : '';

        $otherSummaryCol = $otherCount > 0
            ? '<td class="summary-cell"><div class="summary-label">Other Active (' . $otherCount . ')</div><div class="summary-val">' . $e($fmtMoney($otherTotal)) . '</div></td>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$e($title)}</title>
<style>
    @page { margin: 18mm 15mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1c1917; line-height: 1.4; }
    .header { border-bottom: 2px solid #065f46; padding-bottom: 10px; margin-bottom: 14px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .brand { font-size: 16px; font-weight: bold; color: #065f46; }
    .doc-title { font-size: 12px; font-weight: bold; color: #292524; margin-top: 2px; }
    .meta-right { text-align: right; font-size: 9px; color: #57534e; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .info-box { width: 50%; vertical-align: top; background: #fafaf9; border: 1px solid #e7e5e4; padding: 8px 10px; }
    .info-box h3 { margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #065f46; }
    .kv-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
    .kv-table td { padding: 1.5px 0; }
    .kv-table td.label { color: #57534e; width: 34%; font-weight: bold; }
    .summary-table { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px -6px; }
    .summary-cell { background: #f5f5f4; border: 1px solid #d6d3d1; padding: 8px 10px; text-align: left; }
    .summary-cell.highlight { background: #ecfdf5; border-color: #a7f3d0; }
    .summary-label { font-size: 8.5px; text-transform: uppercase; color: #57534e; font-weight: bold; }
    .summary-val { font-size: 13px; font-weight: bold; color: #1c1917; margin-top: 2px; }
    .summary-cell.highlight .summary-val { color: #065f46; }
    .order-card { border: 1px solid #d6d3d1; margin-bottom: 12px; page-break-inside: avoid; }
    .order-header-table { width: 100%; border-collapse: collapse; background: #f5f5f4; border-bottom: 1px solid #d6d3d1; }
    .order-header-table td { padding: 7px 10px; vertical-align: middle; }
    .order-num { font-size: 11px; font-weight: bold; color: #1c1917; }
    .order-date { font-size: 8.5px; color: #57534e; margin-top: 1px; }
    .order-total { font-size: 11px; font-weight: bold; color: #065f46; margin-top: 3px; }
    .right { text-align: right; }
    .badge { display: inline-block; padding: 2px 7px; font-size: 8px; font-weight: bold; border-radius: 3px; text-transform: uppercase; }
    .badge-delivered { background: #d1fae5; color: #065f46; }
    .badge-declined { background: #fee2e2; color: #991b1b; }
    .badge-progress { background: #dbeafe; color: #1e40af; }
    .badge-received { background: #fef3c7; color: #92400e; }
    .badge-pending { background: #e7e5e4; color: #44403c; }
    .items-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    .items-table th { text-align: left; background: #fafaf9; color: #57534e; padding: 5px 10px; border-bottom: 1px solid #e7e5e4; font-weight: bold; }
    .items-table td { padding: 5px 10px; border-bottom: 1px solid #f5f5f4; }
    .items-table th.num, .items-table td.num { text-align: right; }
    .order-notes { padding: 6px 10px; font-size: 8.5px; color: #57534e; background: #fafaf9; border-top: 1px solid #e7e5e4; }
    .empty-box { padding: 24px; text-align: center; border: 1px dashed #d6d3d1; color: #78716c; font-size: 10.5px; }
    .footer { margin-top: 16px; border-top: 1px solid #e7e5e4; padding-top: 6px; font-size: 8px; color: #78716c; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="brand">{$e($this->appName)}</div>
                    <div class="doc-title">{$e($title)}</div>
                </td>
                <td class="meta-right">
                    <div><strong>Generated:</strong> {$e($generatedAt)}</div>
                    <div><strong>Currency:</strong> {$e($this->currency)} ({$e($this->currencySymbol)})</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-box">
                <h3>Account Details</h3>
                <table class="kv-table">
                    <tr><td class="label">Account:</td><td>{$e($displayAccount)}</td></tr>
                    <tr><td class="label">Email:</td><td>{$e($email)}</td></tr>
                    <tr><td class="label">Phone:</td><td>{$e($phone)}</td></tr>
                    <tr><td class="label">Location:</td><td>{$e($location)}</td></tr>
                </table>
            </td>
            <td class="info-box">
                <h3>Statement Scope</h3>
                <table class="kv-table">
                    <tr><td class="label">Status Scope:</td><td>{$e($scopeLabel)}</td></tr>
                    <tr><td class="label">Date Period:</td><td>{$e($periodText)}</td></tr>
                    {$orderFilterRow}
                    <tr><td class="label">Total Invoices:</td><td>{$e((string) count($orders))}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-cell highlight">
                <div class="summary-label">Delivered Spend ({$deliveredCount})</div>
                <div class="summary-val">{$e($fmtMoney($deliveredSpend))}</div>
            </td>
            <td class="summary-cell">
                <div class="summary-label">Declined Total ({$declinedCount})</div>
                <div class="summary-val">{$e($fmtMoney($declinedTotal))}</div>
            </td>
            {$otherSummaryCol}
            <td class="summary-cell">
                <div class="summary-label">Statement Total ({$e((string) count($orders))})</div>
                <div class="summary-val">{$e($fmtMoney($grandTotal))}</div>
            </td>
        </tr>
    </table>

    {$ordersHtml}

    <div class="footer">
        {$e($this->appName)} &middot; Generated on {$e($generatedAt)} &middot; All amounts in {$e($this->currency)} ({$e($this->currencySymbol)}).
    </div>
</body>
</html>
HTML;
    }
}
