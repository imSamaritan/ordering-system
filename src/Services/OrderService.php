<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Support\OrderNumberGenerator;
use Dibi\Connection;
use RuntimeException;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EmailService $emailService,
        private readonly string $supplierEmail,
        private readonly string $currencySymbol = 'R',
        private readonly string $appName = 'Produce & Inventory Ordering System'
    ) {
    }

    /**
     * Validates a requested quantity against a product's unit rule using integer-tenth math.
     * Returns quantity in tenths (e.g. 0.3 kg => 3, 2 Unit => 20) or null if invalid.
     */
    public static function parseQuantityTenths(mixed $rawQuantity, bool $allowDecimal): ?int
    {
        if (!is_int($rawQuantity) && !is_float($rawQuantity) && !(is_string($rawQuantity) && is_numeric($rawQuantity))) {
            return null;
        }

        $quantity = (float) $rawQuantity;
        if (!is_finite($quantity) || $quantity <= 0) {
            return null;
        }

        $tenthsFloat = $quantity * 10.0;
        $tenths = (int) round($tenthsFloat);

        // Must be an exact tenth (within floating point tolerance)
        if ($tenths <= 0 || abs($tenthsFloat - $tenths) > 0.0001) {
            return null;
        }

        // Whole-unit products (Unit / Punnet) must be whole integers (multiples of 10 tenths)
        if (!$allowDecimal && ($tenths % 10 !== 0)) {
            return null;
        }

        return $tenths;
    }

    /**
     * @param array<string, mixed> $authUser
     * @param array<string, mixed> $input
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   errors?: array<string, string>,
     *   order?: array<string, mixed>,
     *   emails?: array{customer_sent: bool, supplier_sent: bool}
     * }
     */
    public function placeOrder(array $authUser, array $input): array
    {
        $rawItems = $input['items'] ?? null;
        if (!is_array($rawItems) || $rawItems === []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Your cart is empty. Add at least one item before placing an order.',
                'errors' => ['items' => 'At least one order item is required.'],
            ];
        }

        if (count($rawItems) > 100) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'An order cannot exceed 100 line items.',
                'errors' => ['items' => 'Too many items in a single order.'],
            ];
        }

        $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';
        if (mb_strlen($notes) > 1000) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Order notes must not exceed 1000 characters.',
                'errors' => ['notes' => 'Notes must not exceed 1000 characters.'],
            ];
        }

        // Pre-validate item structure and aggregate duplicate product_ids
        $requestedQuantities = [];
        foreach ($rawItems as $index => $item) {
            if (!is_array($item)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Invalid item entry in order payload.',
                    'errors' => ["items.{$index}" => 'Each item must be an object with product_id and quantity.'],
                ];
            }

            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            if ($productId === false || $productId <= 0) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Invalid product ID in order.',
                    'errors' => ["items.{$index}.product_id" => 'A valid product_id is required.'],
                ];
            }

            $rawQty = $item['quantity'] ?? null;
            // Basic tenth check here; strict unit rule is checked once product metadata is loaded
            $tenths = self::parseQuantityTenths($rawQty, true);
            if ($tenths === null || $tenths > 100000) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Invalid quantity supplied for an item.',
                    'errors' => ["items.{$index}.quantity" => 'Quantity must be a positive number in 0.1 increments.'],
                ];
            }

            $requestedQuantities[$productId] = ($requestedQuantities[$productId] ?? 0) + $tenths;
        }

        $productIds = array_keys($requestedQuantities);
        $userId = (int) $authUser['id'];
        $orderedAt = date('Y-m-d H:i:s');

        $this->db->begin();
        try {
            $products = $this->productRepository->findByIdsForUpdate($productIds);

            $orderItems = [];
            $totalCents = 0;

            foreach ($requestedQuantities as $productId => $tenths) {
                $product = $products[$productId] ?? null;
                if ($product === null || !$product['is_active']) {
                    $this->db->rollback();
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'One or more products in your cart are unavailable.',
                        'errors' => ["product_{$productId}" => 'Product is unavailable or inactive.'],
                    ];
                }

                $allowDecimal = (bool) $product['allow_decimal'];
                if (!$allowDecimal && ($tenths % 10 !== 0)) {
                    $this->db->rollback();
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => sprintf(
                            '%s is sold by %s and must be ordered in whole numbers.',
                            (string) $product['description'],
                            (string) $product['unit_name']
                        ),
                        'errors' => ["product_{$productId}" => 'Whole integer quantity required for this unit.'],
                    ];
                }

                $availableTenths = (int) round(((float) $product['stock_quantity']) * 10);
                if ($tenths > $availableTenths) {
                    $this->db->rollback();
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => sprintf(
                            'Insufficient stock for %s (available: %.1f %s).',
                            (string) $product['description'],
                            (float) $product['stock_quantity'],
                            (string) $product['unit_name']
                        ),
                        'errors' => ["product_{$productId}" => 'Requested quantity exceeds available stock.'],
                    ];
                }

                $unitPrice = (float) $product['unit_price'];
                $quantity = $tenths / 10.0;
                $lineCents = (int) round($unitPrice * $tenths * 10);
                $lineTotal = $lineCents / 100.0;
                $totalCents += $lineCents;

                $orderItems[] = [
                    'product_id' => $productId,
                    'product_name_snapshot' => (string) $product['description'],
                    'unit_name_snapshot' => (string) $product['unit_name'],
                    'unit_price_snapshot' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $orderNumber = $this->generateUniqueOrderNumber();
            $totalAmount = $totalCents / 100.0;

            $orderId = $this->orderRepository->createOrderWithItems([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'notes' => $notes !== '' ? $notes : null,
                'ordered_at' => $orderedAt,
            ], $orderItems, $userId);

            foreach ($orderItems as $item) {
                $this->productRepository->decrementStock($item['product_id'], $item['quantity']);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        $order = $this->orderRepository->findByIdWithItems($orderId);
        if ($order === null) {
            throw new RuntimeException('Failed to load newly created order.');
        }

        $emails = $this->sendOrderEmails($authUser, $order);

        return [
            'ok' => true,
            'status' => 201,
            'message' => sprintf('Order %s placed successfully.', (string) $order['order_number']),
            'order' => $order,
            'emails' => $emails,
        ];
    }

    private function generateUniqueOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = OrderNumberGenerator::generate();
            if (!$this->orderRepository->existsByOrderNumber($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique order number.');
    }

    /**
     * @param array<string, mixed> $authUser
     * @param array<string, mixed> $order
     * @return array{customer_sent: bool, supplier_sent: bool}
     */
    private function sendOrderEmails(array $authUser, array $order): array
    {
        $orderNumber = (string) $order['order_number'];
        $totalFormatted = $this->currencySymbol . number_format((float) $order['total_amount'], 2);
        $customerName = (string) ($authUser['contact_name'] ?? 'Customer');
        $companyName = (string) ($authUser['company_name'] ?? '');
        $customerEmail = (string) ($authUser['email'] ?? '');
        $userId = (int) $authUser['id'];
        $orderId = (int) $order['id'];

        $rowsHtml = '';
        /** @var list<array<string, mixed>> $items */
        $items = $order['items'] ?? [];
        foreach ($items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.');
            $lineTotal = $this->currencySymbol . number_format((float) $item['line_total'], 2);
            $rowsHtml .= sprintf(
                '<tr><td style="padding:6px 8px;border-bottom:1px solid #e7e5e4;">%s</td><td style="padding:6px 8px;border-bottom:1px solid #e7e5e4;">%s %s</td><td style="padding:6px 8px;border-bottom:1px solid #e7e5e4;text-align:right;">%s</td></tr>',
                htmlspecialchars((string) $item['product_name_snapshot'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($qty, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $item['unit_name_snapshot'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($lineTotal, ENT_QUOTES, 'UTF-8')
            );
        }

        $safeOrderNumber = htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8');
        $safeCustomerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8');

        $customerHtml = <<<HTML
<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1c1917;">
  <h2 style="color:#065f46;">Order Confirmation — {$safeOrderNumber}</h2>
  <p>Hi {$safeCustomerName},</p>
  <p>Thank you for your order with <strong>{$safeAppName}</strong>. Your order is now <strong>pending</strong> supplier review.</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
    <thead>
      <tr style="background:#f5f5f4;text-align:left;">
        <th style="padding:8px;">Product</th>
        <th style="padding:8px;">Qty</th>
        <th style="padding:8px;text-align:right;">Line Total</th>
      </tr>
    </thead>
    <tbody>{$rowsHtml}</tbody>
  </table>
  <p style="font-size:16px;font-weight:700;text-align:right;">Total: {$totalFormatted}</p>
</div>
HTML;

        $customerRes = $this->emailService->send(
            $userId,
            $customerEmail,
            $customerName,
            'order_confirmation',
            "Order Confirmed: {$orderNumber}",
            $customerHtml,
            "Order {$orderNumber} confirmed. Total: {$totalFormatted}.",
            $orderId
        );

        $buyerLabel = $companyName !== '' ? "{$companyName} ({$customerName})" : $customerName;
        $safeBuyerLabel = htmlspecialchars($buyerLabel, ENT_QUOTES, 'UTF-8');

        $supplierHtml = <<<HTML
<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1c1917;">
  <h2 style="color:#065f46;">New Order Received — {$safeOrderNumber}</h2>
  <p>A new order has been placed by <strong>{$safeBuyerLabel}</strong> ({$customerEmail}).</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
    <thead>
      <tr style="background:#f5f5f4;text-align:left;">
        <th style="padding:8px;">Product</th>
        <th style="padding:8px;">Qty</th>
        <th style="padding:8px;text-align:right;">Line Total</th>
      </tr>
    </thead>
    <tbody>{$rowsHtml}</tbody>
  </table>
  <p style="font-size:16px;font-weight:700;text-align:right;">Order Total: {$totalFormatted}</p>
</div>
HTML;

        $supplierRes = $this->emailService->send(
            $userId,
            $this->supplierEmail,
            'Supplier Team',
            'supplier_order_alert',
            "New Order {$orderNumber} — {$totalFormatted}",
            $supplierHtml,
            "New order {$orderNumber} from {$buyerLabel}. Total: {$totalFormatted}.",
            $orderId
        );

        return [
            'customer_sent' => $customerRes['sent'],
            'supplier_sent' => $supplierRes['sent'],
        ];
    }
}
