<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class SupplierDashboardController
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
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly OrderService $orderService,
        private readonly InventoryService $inventoryService,
        private readonly PhpRenderer $view
    ) {
    }

    public function showDashboard(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'supplier/dashboard.php', [
            'title' => 'Supplier Portal',
            'pageScript' => 'pages/supplierDashboard.js',
            'initialTab' => 'companies',
        ]);
    }

    public function showInventory(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'supplier/dashboard.php', [
            'title' => 'Supplier Inventory Management',
            'pageScript' => 'pages/supplierDashboard.js',
            'initialTab' => 'inventory',
        ]);
    }

    public function companies(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $search = isset($query['search']) ? trim((string) $query['search']) : null;

        $companies = $this->userRepository->findBuyerAccountsWithOrderStats($search);

        $totalOrders = 0;
        $pendingOrders = 0;
        $inProgressOrders = 0;
        $deliveredOrders = 0;
        $totalRevenueCents = 0;

        foreach ($companies as $company) {
            $totalOrders += (int) $company['total_orders'];
            $pendingOrders += (int) $company['pending_orders'];
            $inProgressOrders += (int) $company['received_orders'] + (int) $company['in_progress_orders'];
            $deliveredOrders += (int) $company['delivered_orders'];
            $totalRevenueCents += (int) round(((float) $company['total_spend']) * 100);
        }

        return ResponseHelper::json($response, [
            'companies' => $companies,
            'summary' => [
                'total_companies' => count($companies),
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'in_progress_orders' => $inProgressOrders,
                'delivered_orders' => $deliveredOrders,
                'total_revenue' => round($totalRevenueCents / 100, 2),
            ],
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function companyOrders(Request $request, Response $response, array $args): Response
    {
        $companyId = (int) ($args['id'] ?? 0);
        if ($companyId <= 0) {
            return ResponseHelper::error($response, 'Invalid company ID.', 400);
        }

        $company = $this->userRepository->findBuyerWithOrderStats($companyId);
        if ($company === null) {
            return ResponseHelper::error($response, 'Company account not found.', 404);
        }

        $query = $request->getQueryParams();
        $statusParam = isset($query['status']) ? strtolower(trim((string) $query['status'])) : 'all';
        $orderNumber = isset($query['order_number']) ? trim((string) $query['order_number']) : '';
        $dateFrom = isset($query['date_from']) ? trim((string) $query['date_from']) : '';
        $dateTo = isset($query['date_to']) ? trim((string) $query['date_to']) : '';

        $errors = [];
        $statuses = [];
        if ($statusParam !== '' && $statusParam !== 'all') {
            if (in_array($statusParam, self::VALID_STATUSES, true)) {
                $statuses = [$statusParam];
            } else {
                $errors['status'] = 'Invalid order status filter.';
            }
        }
        if ($dateFrom !== '' && !$this->isValidDate($dateFrom)) {
            $errors['date_from'] = 'Start date must be in YYYY-MM-DD format.';
        }
        if ($dateTo !== '' && !$this->isValidDate($dateTo)) {
            $errors['date_to'] = 'End date must be in YYYY-MM-DD format.';
        }
        if ($dateFrom !== '' && $dateTo !== '' && $errors === [] && $dateFrom > $dateTo) {
            $errors['date_range'] = 'Start date cannot be later than end date.';
        }

        if ($errors !== []) {
            return ResponseHelper::error($response, 'Invalid filter parameters.', 422, $errors);
        }

        $orders = $this->orderRepository->findOrdersForSupplier($companyId, [
            'statuses' => $statuses,
            'order_number' => $orderNumber !== '' ? $orderNumber : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
        ]);

        return ResponseHelper::json($response, [
            'company' => $company,
            'orders' => $orders,
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function orderDetail(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            return ResponseHelper::error($response, 'Invalid order ID.', 400);
        }

        $order = $this->orderRepository->findOrderDetailForSupplier($orderId);
        if ($order === null) {
            return ResponseHelper::error($response, 'Order not found.', 404);
        }

        return ResponseHelper::json($response, [
            'order' => $order,
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function updateOrderStatus(Request $request, Response $response, array $args): Response
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
        $newStatus = isset($body['status']) ? (string) $body['status'] : '';
        $note = isset($body['note']) ? (string) $body['note'] : null;

        $result = $this->orderService->transitionOrderStatus(
            $orderId,
            $newStatus,
            (int) $authUser['id'],
            $note
        );

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
            'email_sent' => $result['email_sent'] ?? false,
        ], $result['status']);
    }

    public function products(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $category = isset($query['category']) ? (string) $query['category'] : null;
        $search = isset($query['search']) ? (string) $query['search'] : null;
        $status = isset($query['status']) ? (string) $query['status'] : null;

        $data = $this->inventoryService->listInventory($category, $search, $status);

        return ResponseHelper::json($response, $data);
    }

    public function createProduct(Request $request, Response $response): Response
    {
        $body = $this->parseJsonBody($request);
        $result = $this->inventoryService->createProduct($body);

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
            'product' => $result['product'],
        ], $result['status']);
    }

    /**
     * @param array<string, string> $args
     */
    public function updateProduct(Request $request, Response $response, array $args): Response
    {
        $productId = (int) ($args['id'] ?? 0);
        if ($productId <= 0) {
            return ResponseHelper::error($response, 'Invalid product ID.', 400);
        }

        $body = $this->parseJsonBody($request);
        $result = $this->inventoryService->updateProduct($productId, $body);

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
            'product' => $result['product'],
        ], $result['status']);
    }

    /**
     * @param array<string, string> $args
     */
    public function deleteProduct(Request $request, Response $response, array $args): Response
    {
        $productId = (int) ($args['id'] ?? 0);
        if ($productId <= 0) {
            return ResponseHelper::error($response, 'Invalid product ID.', 400);
        }

        $result = $this->inventoryService->deleteProduct($productId);

        if (!$result['ok']) {
            return ResponseHelper::error($response, $result['message'], $result['status']);
        }

        $payload = [
            'message' => $result['message'],
            'action' => $result['action'] ?? 'deleted',
        ];
        if (isset($result['product'])) {
            $payload['product'] = $result['product'];
        }
        if (isset($result['deleted_id'])) {
            $payload['deleted_id'] = $result['deleted_id'];
        }

        return ResponseHelper::json($response, $payload, $result['status']);
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
