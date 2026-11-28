<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Services\PdfReportService;
use App\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReportController
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
        private readonly PdfReportService $pdfReportService
    ) {
    }

    public function customerStatementPdf(Request $request, Response $response): Response
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
        $statusParam = isset($query['status']) ? strtolower(trim((string) $query['status'])) : '';
        $orderIdsRaw = isset($query['order_ids']) ? trim((string) $query['order_ids']) : '';

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

        $orderIds = [];
        if ($orderIdsRaw !== '') {
            foreach (explode(',', $orderIdsRaw) as $part) {
                $trimmed = trim($part);
                if ($trimmed === '') {
                    continue;
                }
                if (!ctype_digit($trimmed) || (int) $trimmed <= 0) {
                    $errors['order_ids'] = 'order_ids must be a comma-separated list of positive integers.';
                    break;
                }
                $orderIds[] = (int) $trimmed;
            }
            $orderIds = array_values(array_unique($orderIds));
        }

        $statuses = [];
        $scopeLabel = 'Complete / Delivered & Declined';
        $title = 'Customer Order Statement (Complete / Delivered & Declined)';

        if ($orderIds !== []) {
            $scopeLabel = 'Selected Invoices (' . count($orderIds) . ')';
            $title = 'Customer Selected Invoices Statement';
            if ($statusParam !== '' && $statusParam !== 'all') {
                if ($statusParam === 'complete_and_declined' || $statusParam === 'delivered_and_declined') {
                    $statuses = ['delivered', 'declined'];
                } elseif (in_array($statusParam, self::VALID_STATUSES, true)) {
                    $statuses = [$statusParam];
                } else {
                    $errors['status'] = 'Invalid order status filter.';
                }
            }
        } elseif ($statusParam === '' || $statusParam === 'complete_and_declined' || $statusParam === 'delivered_and_declined') {
            // Default PDF statement scope per blueprint: Complete (Delivered) & Declined
            $statuses = ['delivered', 'declined'];
            $scopeLabel = 'Complete / Delivered & Declined';
        } elseif ($statusParam === 'all') {
            $statuses = [];
            $scopeLabel = 'All Order Statuses';
            $title = 'Customer Order Statement (All Orders)';
        } elseif (in_array($statusParam, self::VALID_STATUSES, true)) {
            $statuses = [$statusParam];
            $scopeLabel = ucfirst(str_replace('_', ' ', $statusParam));
            $title = "Customer Order Statement ({$scopeLabel})";
        } else {
            $errors['status'] = 'Invalid order status filter.';
        }

        if ($errors !== []) {
            return ResponseHelper::error($response, 'Invalid report filter parameters.', 422, $errors);
        }

        $orders = $this->orderRepository->findCustomerOrders(
            (int) $authUser['id'],
            [
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
                'order_number' => $orderNumber !== '' ? $orderNumber : null,
                'statuses' => $statuses,
                'order_ids' => $orderIds,
            ],
            true
        );

        $pdfBinary = $this->pdfReportService->generateCustomerStatementPdf($authUser, $orders, [
            'title' => $title,
            'scope_label' => $scopeLabel,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'order_number' => $orderNumber,
            'generated_at' => date('Y-m-d H:i'),
        ]);

        $filename = 'order-statement-' . date('Ymd-His') . '.pdf';
        $response->getBody()->write($pdfBinary);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($pdfBinary))
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withStatus(200);
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
