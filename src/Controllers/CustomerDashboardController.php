<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\OrderService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class CustomerDashboardController
{
    private const VALID_STATUSES = [
        'pending',
        'received',
        'in_progress',
        'delivered',
        'declined',
        'cancelled',
    ];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly OrderService $orderService,
        private readonly PhpRenderer $view
    ) {
    }

    public function showDashboard(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'customer/dashboard.php', [
            'title' => 'Customer Dashboard',
            'pageScript' => 'pages/customerDashboard.js',
        ]);
    }

    public function orders(Request $request, Response $response): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $query = $request->getQueryParams();
        $dateFrom = isset($query['date_from']) ? trim((string) $query['date_from']) : '';
        $dateTo = isset($query['date_to']) ? trim((string) $query['date_to']) : '';
        $orderNumber = isset($query['order_number']) ? trim((string) $query['order_number']) : '';
        $statusParam = isset($query['status']) ? strtolower(trim((string) $query['status'])) : 'all';

        $errors = [];
        if ($dateFrom !== '' && !$this->isValidDate($dateFrom)) {
            $errors['date_from'] = 'Start date must be in YYYY-MM-DD format.';
        }
        if ($dateTo !== '' && !$this->isValidDate($dateTo)) {
            $errors['date_to'] = 'End date must be in YYYY-MM-DD format.';
        }
        if ($dateFrom !== '' && $dateTo !== '' && $errors === [] && $dateFrom > $dateTo) {
            $errors['date_range'] = 'Start date cannot be later than end date.';
        }

        $statuses = [];
        if ($statusParam !== '' && $statusParam !== 'all') {
            if ($statusParam === 'complete_and_declined' || $statusParam === 'delivered_and_declined') {
                $statuses = ['delivered', 'declined'];
            } elseif (in_array($statusParam, self::VALID_STATUSES, true)) {
                $statuses = [$statusParam];
            } else {
                $errors['status'] = 'Invalid order status filter.';
            }
        }

        if ($errors !== []) {
            return ResponseHelper::error($response, 'Invalid filter parameters.', 422, $errors);
        }

        $orders = $this->orderRepository->findCustomerOrders((int) $authUser['id'], [
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'order_number' => $orderNumber !== '' ? $orderNumber : null,
            'statuses' => $statuses,
        ]);

        $totalSpendCents = 0;
        $deliveredSpendCents = 0;
        $declinedTotalCents = 0;
        $cancelledTotalCents = 0;
        $deliveredCount = 0;
        $declinedCount = 0;
        $cancelledCount = 0;
        $pendingCount = 0;
        $receivedCount = 0;
        $inProgressCount = 0;

        $includeExcludedInTotal = in_array(
            $statusParam,
            ['declined', 'cancelled', 'complete_and_declined', 'delivered_and_declined'],
            true
        );

        foreach ($orders as $order) {
            $amountCents = (int) round(((float) $order['total_amount']) * 100);
            $isExcludedStatus = in_array($order['status'], ['declined', 'cancelled'], true);

            if ($includeExcludedInTotal || !$isExcludedStatus) {
                $totalSpendCents += $amountCents;
            }

            switch ($order['status']) {
                case 'delivered':
                    $deliveredCount++;
                    $deliveredSpendCents += $amountCents;
                    break;
                case 'declined':
                    $declinedCount++;
                    $declinedTotalCents += $amountCents;
                    break;
                case 'cancelled':
                    $cancelledCount++;
                    $cancelledTotalCents += $amountCents;
                    break;
                case 'received':
                    $receivedCount++;
                    break;
                case 'in_progress':
                    $inProgressCount++;
                    break;
                default:
                    $pendingCount++;
                    break;
            }
        }

        return ResponseHelper::json($response, [
            'orders' => $orders,
            'summary' => [
                'count' => count($orders),
                'total_spend' => round($totalSpendCents / 100, 2),
                'delivered_count' => $deliveredCount,
                'delivered_spend' => round($deliveredSpendCents / 100, 2),
                'declined_count' => $declinedCount,
                'declined_total' => round($declinedTotalCents / 100, 2),
                'cancelled_count' => $cancelledCount,
                'cancelled_total' => round($cancelledTotalCents / 100, 2),
                'pending_count' => $pendingCount,
                'received_count' => $receivedCount,
                'in_progress_count' => $inProgressCount,
            ],
            'filters' => [
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
                'order_number' => $orderNumber !== '' ? $orderNumber : null,
                'status' => $statusParam !== '' ? $statusParam : 'all',
            ],
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function orderDetail(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            return ResponseHelper::error($response, 'Invalid order ID.', 400);
        }

        $order = $this->orderRepository->findCustomerOrderDetail((int) $authUser['id'], $orderId);
        if ($order === null) {
            return ResponseHelper::error($response, 'Order not found.', 404);
        }

        return ResponseHelper::json($response, [
            'order' => $order,
        ]);
    }

    /**
     * Validates historical line items from a past order against live catalog availability,
     * stock levels, and current pricing so the customer can reorder in one click.
     *
     * @param array<string, string> $args
     */
    public function reorderPreview(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            return ResponseHelper::error($response, 'Invalid order ID.', 400);
        }

        $order = $this->orderRepository->findCustomerOrderDetail((int) $authUser['id'], $orderId);
        if ($order === null) {
            return ResponseHelper::error($response, 'Order not found.', 404);
        }

        /** @var list<array<string, mixed>> $orderItems */
        $orderItems = is_array($order['items'] ?? null) ? $order['items'] : [];
        $productIds = [];
        foreach ($orderItems as $item) {
            $productIds[] = (int) $item['product_id'];
        }

        $liveProducts = $this->productRepository->findByIds($productIds);

        $available = [];
        $adjusted = [];
        $unavailable = [];
        $priceChangedCount = 0;

        foreach ($orderItems as $item) {
            $productId = (int) $item['product_id'];
            $orderedQty = round((float) $item['quantity'], 2);
            $previousPrice = round((float) $item['unit_price_snapshot'], 2);
            $product = $liveProducts[$productId] ?? null;

            if ($product === null) {
                $unavailable[] = [
                    'product_id' => $productId,
                    'description' => (string) $item['product_name_snapshot'],
                    'unit_name' => (string) $item['unit_name_snapshot'],
                    'ordered_quantity' => $orderedQty,
                    'previous_unit_price' => $previousPrice,
                    'reason' => 'Product is no longer available in the catalog.',
                ];
                continue;
            }

            if (!(bool) $product['is_active']) {
                $unavailable[] = [
                    'product_id' => $productId,
                    'description' => (string) $product['description'],
                    'unit_name' => (string) $product['unit_name'],
                    'ordered_quantity' => $orderedQty,
                    'previous_unit_price' => $previousPrice,
                    'reason' => 'Product is currently inactive.',
                ];
                continue;
            }

            $allowDecimal = (bool) $product['allow_decimal'];
            $stepSize = (float) ($product['step_size'] ?? ($allowDecimal ? 0.1 : 1.0));
            $stepTenths = $allowDecimal ? 1 : 10;

            $requestedTenths = (int) round($orderedQty * 10);
            if (!$allowDecimal) {
                $requestedTenths = max(10, (int) round($requestedTenths / 10) * 10);
            } elseif ($requestedTenths < 1) {
                $requestedTenths = 1;
            }

            $stockQty = round((float) $product['stock_quantity'], 2);
            $stockTenths = (int) floor(round($stockQty * 10, 4));
            $maxOrderableTenths = $allowDecimal
                ? $stockTenths
                : intdiv(max(0, $stockTenths), 10) * 10;

            if ($maxOrderableTenths < $stepTenths) {
                $unavailable[] = [
                    'product_id' => $productId,
                    'description' => (string) $product['description'],
                    'unit_name' => (string) $product['unit_name'],
                    'ordered_quantity' => $orderedQty,
                    'previous_unit_price' => $previousPrice,
                    'reason' => 'Currently out of stock.',
                ];
                continue;
            }

            $currentPrice = round((float) $product['unit_price'], 2);
            $priceDelta = round($currentPrice - $previousPrice, 2);
            $priceChanged = abs($priceDelta) >= 0.01;
            if ($priceChanged) {
                $priceChangedCount++;
            }

            $entry = [
                'product_id' => $productId,
                'sku' => (string) $product['sku'],
                'description' => (string) $product['description'],
                'unit_name' => (string) $product['unit_name'],
                'allow_decimal' => $allowDecimal,
                'step_size' => $stepSize,
                'image_path' => $product['image_path'],
                'unit_price' => $currentPrice,
                'previous_unit_price' => $previousPrice,
                'price_changed' => $priceChanged,
                'price_delta' => $priceDelta,
                'stock_quantity' => $stockQty,
                'ordered_quantity' => $orderedQty,
            ];

            if ($requestedTenths <= $maxOrderableTenths) {
                $entry['suggested_quantity'] = round($requestedTenths / 10, 2);
                $available[] = $entry;
            } else {
                $suggestedQty = round($maxOrderableTenths / 10, 2);
                $formattedSuggested = $allowDecimal
                    ? number_format($suggestedQty, 1, '.', '')
                    : (string) (int) round($suggestedQty);
                $formattedOrdered = $allowDecimal
                    ? number_format($orderedQty, 1, '.', '')
                    : (string) (int) round($orderedQty);

                $entry['suggested_quantity'] = $suggestedQty;
                $entry['reason'] = sprintf(
                    'Only %s %s in stock (ordered %s).',
                    $formattedSuggested,
                    (string) $product['unit_name'],
                    $formattedOrdered
                );
                $adjusted[] = $entry;
            }
        }

        return ResponseHelper::json($response, [
            'order_id' => (int) $order['id'],
            'order_number' => (string) $order['order_number'],
            'available' => $available,
            'adjusted' => $adjusted,
            'unavailable' => $unavailable,
            'summary' => [
                'total_items' => count($orderItems),
                'available_count' => count($available),
                'adjusted_count' => count($adjusted),
                'unavailable_count' => count($unavailable),
                'price_changed_count' => $priceChangedCount,
                'can_reorder' => (count($available) + count($adjusted)) > 0,
            ],
        ]);
    }

    /**
     * Cancels a customer's own pending order and restores reserved stock.
     *
     * @param array<string, string> $args
     */
    public function cancelOrder(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            return ResponseHelper::error($response, 'Invalid order ID.', 400);
        }

        $body = $this->parseJsonBody($request);
        $reason = isset($body['reason']) ? (string) $body['reason'] : null;

        $result = $this->orderService->cancelOrderByCustomer($orderId, $authUser, $reason);
        if (!$result['ok']) {
            return ResponseHelper::error(
                $response,
                $result['message'],
                $result['status'],
                $result['errors'] ?? []
            );
        }

        return ResponseHelper::json($response, [
            'message' => $result['message'],
            'order' => $result['order'],
            'emails' => $result['emails'] ?? ['customer_sent' => false, 'supplier_sent' => false],
        ], $result['status']);
    }

    /**
     * Updates delivery notes for a customer's own pending order.
     *
     * @param array<string, string> $args
     */
    public function updateOrderNotes(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed>|null $authUser */
        $authUser = $request->getAttribute('auth_user');
        if ($authUser === null) {
            return ResponseHelper::error($response, 'Authentication required.', 401);
        }

        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            return ResponseHelper::error($response, 'Invalid order ID.', 400);
        }

        $body = $this->parseJsonBody($request);
        $notes = array_key_exists('notes', $body) && $body['notes'] !== null
            ? (string) $body['notes']
            : null;

        $result = $this->orderService->updatePendingOrderNotes($orderId, $authUser, $notes);
        if (!$result['ok']) {
            return ResponseHelper::error(
                $response,
                $result['message'],
                $result['status'],
                $result['errors'] ?? []
            );
        }

        return ResponseHelper::json($response, [
            'message' => $result['message'],
            'order' => $result['order'],
        ], $result['status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }
}
