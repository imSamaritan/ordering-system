<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeInterface;
use Dibi\Connection;

final class OrderRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function existsByOrderNumber(string $orderNumber): bool
    {
        $id = $this->db->query(
            'SELECT id FROM orders WHERE order_number = %s LIMIT 1',
            $orderNumber
        )->fetchSingle();

        return $id !== false && $id !== null;
    }

    /**
     * Inserts the order header, immutable order_items snapshots, and initial status history entry.
     * Caller should manage the surrounding transaction.
     *
     * @param array{
     *   order_number: string,
     *   user_id: int,
     *   status: string,
     *   total_amount: float,
     *   notes: ?string,
     *   ordered_at: string
     * } $orderData
     * @param list<array{
     *   product_id: int,
     *   product_name_snapshot: string,
     *   unit_name_snapshot: string,
     *   unit_price_snapshot: float,
     *   quantity: float,
     *   line_total: float
     * }> $items
     */
    public function createOrderWithItems(array $orderData, array $items, int $changedByUserId): int
    {
        $now = $orderData['ordered_at'];

        $this->db->query('INSERT INTO orders', [
            'order_number' => $orderData['order_number'],
            'user_id' => $orderData['user_id'],
            'status' => $orderData['status'],
            'total_amount' => number_format($orderData['total_amount'], 2, '.', ''),
            'notes' => $orderData['notes'],
            'ordered_at' => $now,
            'received_at' => null,
            'delivered_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = $this->db->getInsertId();

        foreach ($items as $item) {
            $this->db->query('INSERT INTO order_items', [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product_name_snapshot'],
                'unit_name_snapshot' => $item['unit_name_snapshot'],
                'unit_price_snapshot' => number_format($item['unit_price_snapshot'], 2, '.', ''),
                'quantity' => number_format($item['quantity'], 2, '.', ''),
                'line_total' => number_format($item['line_total'], 2, '.', ''),
            ]);
        }

        $this->db->query('INSERT INTO order_status_history', [
            'order_id' => $orderId,
            'old_status' => null,
            'new_status' => $orderData['status'],
            'changed_by_user_id' => $changedByUserId,
            'note' => 'Order placed by customer.',
            'changed_at' => $now,
        ]);

        return $orderId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdWithItems(int $orderId): ?array
    {
        $orderRow = $this->db->query('SELECT * FROM orders WHERE id = %i', $orderId)->fetch();
        if (!$orderRow) {
            return null;
        }

        $order = $this->normalizeOrderRow($orderRow->toArray());

        $itemRows = $this->db->query(
            'SELECT * FROM order_items WHERE order_id = %i ORDER BY id ASC',
            $orderId
        )->fetchAll();

        $items = [];
        foreach ($itemRows as $itemRow) {
            $data = $itemRow->toArray();
            $items[] = [
                'id' => (int) $data['id'],
                'product_id' => (int) $data['product_id'],
                'product_name_snapshot' => (string) $data['product_name_snapshot'],
                'unit_name_snapshot' => (string) $data['unit_name_snapshot'],
                'unit_price_snapshot' => round((float) $data['unit_price_snapshot'], 2),
                'quantity' => round((float) $data['quantity'], 2),
                'line_total' => round((float) $data['line_total'], 2),
            ];
        }

        $order['items'] = $items;

        return $order;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeOrderRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_number' => (string) $row['order_number'],
            'user_id' => (int) $row['user_id'],
            'status' => (string) $row['status'],
            'total_amount' => round((float) $row['total_amount'], 2),
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'ordered_at' => $this->formatDateTime($row['ordered_at'] ?? null),
            'received_at' => $this->formatDateTime($row['received_at'] ?? null),
            'delivered_at' => $this->formatDateTime($row['delivered_at'] ?? null),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
