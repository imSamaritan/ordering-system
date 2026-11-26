<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
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
    ];

    public function __construct(
        private readonly OrderRepository $orderRepository,
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
        $deliveredCount = 0;
        $declinedCount = 0;
        $pendingCount = 0;
        $receivedCount = 0;
        $inProgressCount = 0;

        foreach ($orders as $order) {
            $amountCents = (int) round(((float) $order['total_amount']) * 100);
            $totalSpendCents += $amountCents;

            switch ($order['status']) {
                case 'delivered':
                    $deliveredCount++;
                    $deliveredSpendCents += $amountCents;
                    break;
                case 'declined':
                    $declinedCount++;
                    $declinedTotalCents += $amountCents;
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

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }
}
