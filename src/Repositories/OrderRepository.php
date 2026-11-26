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
     * Finds orders belonging to a specific customer/company with optional filters.
     *
     * @param array{
     *   date_from?: ?string,
     *   date_to?: ?string,
     *   order_number?: ?string,
     *   statuses?: list<string>,
     *   order_ids?: list<int>
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function findCustomerOrders(int $userId, array $filters = [], bool $includeItems = false): array
    {
        $conditions = [];
        $args = [$userId];

        if (!empty($filters['date_from'])) {
            $conditions[] = 'o.ordered_at >= %s';
            $args[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'o.ordered_at <= %s';
            $args[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['order_number'])) {
            $conditions[] = 'o.order_number LIKE %~like~';
            $args[] = $filters['order_number'];
        }

        if (!empty($filters['statuses'])) {
            $conditions[] = 'o.status IN %in';
            $args[] = $filters['statuses'];
        }

        if (!empty($filters['order_ids'])) {
            $conditions[] = 'o.id IN %in';
            $args[] = $filters['order_ids'];
        }

        $sql = 'SELECT o.*, COUNT(oi.id) AS item_count
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.user_id = %i';

        if ($conditions !== []) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY o.id ORDER BY o.ordered_at DESC, o.id DESC';

        $rows = $this->db->query($sql, ...$args)->fetchAll();

        $orders = [];
        $orderIds = [];
        foreach ($rows as $row) {
            $data = $row->toArray();
            $order = $this->normalizeOrderRow($data);
            $order['item_count'] = (int) ($data['item_count'] ?? 0);
            if ($includeItems) {
                $order['items'] = [];
            }
            $orders[] = $order;
            $orderIds[] = $order['id'];
        }

        if ($includeItems && $orderIds !== []) {
            $itemRows = $this->db->query(
                'SELECT * FROM order_items WHERE order_id IN %in ORDER BY order_id ASC, id ASC',
                $orderIds
            )->fetchAll();

            /** @var array<int, list<array<string, mixed>>> $itemsByOrder */
            $itemsByOrder = [];
            foreach ($itemRows as $itemRow) {
                $itemData = $itemRow->toArray();
                $oid = (int) $itemData['order_id'];
                $itemsByOrder[$oid][] = $this->normalizeOrderItemRow($itemData);
            }

            foreach ($orders as &$order) {
                $order['items'] = $itemsByOrder[(int) $order['id']] ?? [];
            }
            unset($order);
        }

        return $orders;
    }

    /**
     * Returns a single customer order with its items and status history, strictly scoped to $userId.
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerOrderDetail(int $userId, int $orderId): ?array
    {
        $orderRow = $this->db->query(
            'SELECT * FROM orders WHERE id = %i AND user_id = %i LIMIT 1',
            $orderId,
            $userId
        )->fetch();

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
            $items[] = $this->normalizeOrderItemRow($itemRow->toArray());
        }
        $order['items'] = $items;
        $order['item_count'] = count($items);

        $historyRows = $this->db->query(
            'SELECT h.id, h.old_status, h.new_status, h.note, h.changed_at, u.contact_name AS changed_by_name, u.role AS changed_by_role
             FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.order_id = %i
             ORDER BY h.changed_at ASC, h.id ASC',
            $orderId
        )->fetchAll();

        $history = [];
        foreach ($historyRows as $historyRow) {
            $h = $historyRow->toArray();
            $history[] = [
                'id' => (int) $h['id'],
                'old_status' => $h['old_status'] !== null ? (string) $h['old_status'] : null,
                'new_status' => (string) $h['new_status'],
                'note' => $h['note'] !== null ? (string) $h['note'] : null,
                'changed_at' => $this->formatDateTime($h['changed_at'] ?? null),
                'changed_by_name' => $h['changed_by_name'] !== null ? (string) $h['changed_by_name'] : null,
                'changed_by_role' => $h['changed_by_role'] !== null ? (string) $h['changed_by_role'] : null,
            ];
        }
        $order['status_history'] = $history;

        return $order;
    }

    /**
     * Lists orders for the supplier portal, optionally scoped to a specific buyer account.
     *
     * @param array{
     *   date_from?: ?string,
     *   date_to?: ?string,
     *   order_number?: ?string,
     *   statuses?: list<string>
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function findOrdersForSupplier(?int $userId = null, array $filters = []): array
    {
        $conditions = [];
        $args = [];

        if ($userId !== null) {
            $conditions[] = 'o.user_id = %i';
            $args[] = $userId;
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'o.ordered_at >= %s';
            $args[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'o.ordered_at <= %s';
            $args[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['order_number'])) {
            $conditions[] = 'o.order_number LIKE %~like~';
            $args[] = $filters['order_number'];
        }

        if (!empty($filters['statuses'])) {
            $conditions[] = 'o.status IN %in';
            $args[] = $filters['statuses'];
        }

        $sql = 'SELECT
                    o.*,
                    u.company_name AS customer_company_name,
                    u.contact_name AS customer_contact_name,
                    u.email AS customer_email,
                    u.phone AS customer_phone,
                    u.location AS customer_location,
                    COUNT(oi.id) AS item_count
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                LEFT JOIN order_items oi ON oi.order_id = o.id';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY o.id, u.company_name, u.contact_name, u.email, u.phone, u.location
                  ORDER BY o.ordered_at DESC, o.id DESC';

        $rows = $this->db->query($sql, ...$args)->fetchAll();

        $orders = [];
        foreach ($rows as $row) {
            $data = $row->toArray();
            $order = $this->normalizeOrderRow($data);
            $order['item_count'] = (int) ($data['item_count'] ?? 0);
            $order['customer'] = [
                'id' => (int) $data['user_id'],
                'company_name' => $data['customer_company_name'] !== null ? (string) $data['customer_company_name'] : null,
                'contact_name' => (string) $data['customer_contact_name'],
                'email' => (string) $data['customer_email'],
                'phone' => $data['customer_phone'] !== null ? (string) $data['customer_phone'] : null,
                'location' => $data['customer_location'] !== null ? (string) $data['customer_location'] : null,
            ];
            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * Returns full order details (customer, line items, status history) for supplier review.
     *
     * @return array<string, mixed>|null
     */
    public function findOrderDetailForSupplier(int $orderId): ?array
    {
        $orderRow = $this->db->query(
            'SELECT
                o.*,
                u.company_name AS customer_company_name,
                u.contact_name AS customer_contact_name,
                u.email AS customer_email,
                u.phone AS customer_phone,
                u.location AS customer_location
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             WHERE o.id = %i
             LIMIT 1',
            $orderId
        )->fetch();

        if (!$orderRow) {
            return null;
        }

        $data = $orderRow->toArray();
        $order = $this->normalizeOrderRow($data);
        $order['customer'] = [
            'id' => (int) $data['user_id'],
            'company_name' => $data['customer_company_name'] !== null ? (string) $data['customer_company_name'] : null,
            'contact_name' => (string) $data['customer_contact_name'],
            'email' => (string) $data['customer_email'],
            'phone' => $data['customer_phone'] !== null ? (string) $data['customer_phone'] : null,
            'location' => $data['customer_location'] !== null ? (string) $data['customer_location'] : null,
        ];

        $items = $this->findItemsByOrderId($orderId);
        $order['items'] = $items;
        $order['item_count'] = count($items);

        $historyRows = $this->db->query(
            'SELECT h.id, h.old_status, h.new_status, h.note, h.changed_at, u.contact_name AS changed_by_name, u.role AS changed_by_role
             FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.order_id = %i
             ORDER BY h.changed_at ASC, h.id ASC',
            $orderId
        )->fetchAll();

        $history = [];
        foreach ($historyRows as $historyRow) {
            $h = $historyRow->toArray();
            $history[] = [
                'id' => (int) $h['id'],
                'old_status' => $h['old_status'] !== null ? (string) $h['old_status'] : null,
                'new_status' => (string) $h['new_status'],
                'note' => $h['note'] !== null ? (string) $h['note'] : null,
                'changed_at' => $this->formatDateTime($h['changed_at'] ?? null),
                'changed_by_name' => $h['changed_by_name'] !== null ? (string) $h['changed_by_name'] : null,
                'changed_by_role' => $h['changed_by_role'] !== null ? (string) $h['changed_by_role'] : null,
            ];
        }
        $order['status_history'] = $history;

        return $order;
    }

    /**
     * Locks an order row for status update inside an active transaction.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForUpdate(int $orderId): ?array
    {
        $row = $this->db->query('SELECT * FROM orders WHERE id = %i FOR UPDATE', $orderId)->fetch();
        return $row ? $this->normalizeOrderRow($row->toArray()) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findItemsByOrderId(int $orderId): array
    {
        $itemRows = $this->db->query(
            'SELECT * FROM order_items WHERE order_id = %i ORDER BY id ASC',
            $orderId
        )->fetchAll();

        $items = [];
        foreach ($itemRows as $itemRow) {
            $items[] = $this->normalizeOrderItemRow($itemRow->toArray());
        }

        return $items;
    }

    public function updateStatusAndLogHistory(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        int $changedByUserId,
        ?string $note,
        ?string $receivedAt,
        ?string $deliveredAt,
        string $now
    ): void {
        $this->db->query('UPDATE orders SET', [
            'status' => $newStatus,
            'received_at' => $receivedAt,
            'delivered_at' => $deliveredAt,
            'updated_at' => $now,
        ], 'WHERE id = %i', $orderId);

        $this->db->query('INSERT INTO order_status_history', [
            'order_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $changedByUserId,
            'note' => $note,
            'changed_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeOrderItemRow(array $data): array
    {
        return [
            'id' => (int) $data['id'],
            'product_id' => (int) $data['product_id'],
            'product_name_snapshot' => (string) $data['product_name_snapshot'],
            'unit_name_snapshot' => (string) $data['unit_name_snapshot'],
            'unit_price_snapshot' => round((float) $data['unit_price_snapshot'], 2),
            'quantity' => round((float) $data['quantity'], 2),
            'line_total' => round((float) $data['line_total'], 2),
        ];
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
