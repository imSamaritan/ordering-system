<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM users WHERE id = %i', $id)->fetch();
        return $row ? $row->toArray() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $row = $this->db->query('SELECT * FROM users WHERE email = %s', strtolower(trim($email)))->fetch();
        return $row ? $row->toArray() : null;
    }

    /**
     * @param array{
     *   role: string,
     *   company_name: ?string,
     *   contact_name: string,
     *   email: string,
     *   phone: ?string,
     *   location: ?string,
     *   password_hash: string
     * } $data
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO users', [
            'role' => $data['role'],
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'],
            'location' => $data['location'],
            'password_hash' => $data['password_hash'],
            'email_verified_at' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->getInsertId();
    }

    public function markEmailVerified(int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE users SET email_verified_at = %s, updated_at = %s WHERE id = %i AND email_verified_at IS NULL',
            $now,
            $now,
            $userId
        );
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->db->query(
            'UPDATE users SET password_hash = %s, updated_at = NOW() WHERE id = %i',
            $passwordHash,
            $userId
        );
    }

    /**
     * Lists all customer & company accounts with aggregated order metrics for the supplier portal.
     *
     * @return list<array<string, mixed>>
     */
    public function findBuyerAccountsWithOrderStats(?string $search = null): array
    {
        $where = ["u.role IN ('customer', 'company')"];
        $params = [];

        $normalizedSearch = $search !== null ? trim($search) : '';
        if ($normalizedSearch !== '') {
            $like = '%' . $normalizedSearch . '%';
            $where[] = '(u.company_name LIKE %s OR u.contact_name LIKE %s OR u.email LIKE %s OR u.location LIKE %s OR u.phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT
                    u.id,
                    u.role,
                    u.company_name,
                    u.contact_name,
                    u.email,
                    u.phone,
                    u.location,
                    u.email_verified_at,
                    u.is_active,
                    COUNT(o.id) AS total_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'pending\' THEN 1 ELSE 0 END), 0) AS pending_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'received\' THEN 1 ELSE 0 END), 0) AS received_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'in_progress\' THEN 1 ELSE 0 END), 0) AS in_progress_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'delivered\' THEN 1 ELSE 0 END), 0) AS delivered_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'declined\' THEN 1 ELSE 0 END), 0) AS declined_orders,
                    COALESCE(SUM(CASE WHEN o.status = \'cancelled\' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
                    COALESCE(SUM(CASE WHEN o.status NOT IN (\'declined\', \'cancelled\') THEN o.total_amount ELSE 0 END), 0) AS total_spend,
                    MAX(o.ordered_at) AS last_ordered_at
                FROM users u
                LEFT JOIN orders o ON o.user_id = u.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY u.id, u.role, u.company_name, u.contact_name, u.email, u.phone, u.location, u.email_verified_at, u.is_active
                ORDER BY total_orders DESC, COALESCE(u.company_name, u.contact_name) ASC';

        $rows = $this->db->query($sql, ...$params)->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::normalizeBuyerStatsRow($row->toArray());
        }

        return $result;
    }

    /**
     * Finds a single customer/company account with aggregated order metrics.
     *
     * @return array<string, mixed>|null
     */
    public function findBuyerWithOrderStats(int $userId): ?array
    {
        $row = $this->db->query(
            'SELECT
                u.id,
                u.role,
                u.company_name,
                u.contact_name,
                u.email,
                u.phone,
                u.location,
                u.email_verified_at,
                u.is_active,
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(CASE WHEN o.status = \'pending\' THEN 1 ELSE 0 END), 0) AS pending_orders,
                COALESCE(SUM(CASE WHEN o.status = \'received\' THEN 1 ELSE 0 END), 0) AS received_orders,
                COALESCE(SUM(CASE WHEN o.status = \'in_progress\' THEN 1 ELSE 0 END), 0) AS in_progress_orders,
                COALESCE(SUM(CASE WHEN o.status = \'delivered\' THEN 1 ELSE 0 END), 0) AS delivered_orders,
                COALESCE(SUM(CASE WHEN o.status = \'declined\' THEN 1 ELSE 0 END), 0) AS declined_orders,
                COALESCE(SUM(CASE WHEN o.status = \'cancelled\' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
                COALESCE(SUM(CASE WHEN o.status NOT IN (\'declined\', \'cancelled\') THEN o.total_amount ELSE 0 END), 0) AS total_spend,
                MAX(o.ordered_at) AS last_ordered_at
             FROM users u
             LEFT JOIN orders o ON o.user_id = u.id
             WHERE u.id = %i AND u.role IN (\'customer\', \'company\')
             GROUP BY u.id, u.role, u.company_name, u.contact_name, u.email, u.phone, u.location, u.email_verified_at, u.is_active
             LIMIT 1',
            $userId
        )->fetch();

        return $row ? self::normalizeBuyerStatsRow($row->toArray()) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeBuyerStatsRow(array $row): array
    {
        $public = self::toPublicArray($row);
        $lastOrdered = $row['last_ordered_at'] ?? null;
        if ($lastOrdered instanceof \DateTimeInterface) {
            $lastOrdered = $lastOrdered->format('Y-m-d H:i:s');
        }

        return array_merge($public, [
            'display_name' => ($public['company_name'] !== null && $public['company_name'] !== '')
                ? $public['company_name']
                : $public['contact_name'],
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'pending_orders' => (int) ($row['pending_orders'] ?? 0),
            'received_orders' => (int) ($row['received_orders'] ?? 0),
            'in_progress_orders' => (int) ($row['in_progress_orders'] ?? 0),
            'delivered_orders' => (int) ($row['delivered_orders'] ?? 0),
            'declined_orders' => (int) ($row['declined_orders'] ?? 0),
            'cancelled_orders' => (int) ($row['cancelled_orders'] ?? 0),
            'total_spend' => round((float) ($row['total_spend'] ?? 0), 2),
            'last_ordered_at' => $lastOrdered !== null ? (string) $lastOrdered : null,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function toPublicArray(array $user): array
    {
        $verifiedAt = $user['email_verified_at'] ?? null;
        if ($verifiedAt instanceof \DateTimeInterface) {
            $verifiedAt = $verifiedAt->format('Y-m-d H:i:s');
        }

        return [
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'company_name' => $user['company_name'] !== null ? (string) $user['company_name'] : null,
            'contact_name' => (string) $user['contact_name'],
            'email' => (string) $user['email'],
            'phone' => $user['phone'] !== null ? (string) $user['phone'] : null,
            'location' => $user['location'] !== null ? (string) $user['location'] : null,
            'email_verified_at' => $verifiedAt !== null ? (string) $verifiedAt : null,
            'is_active' => (bool) ((int) ($user['is_active'] ?? 0)),
        ];
    }
}
