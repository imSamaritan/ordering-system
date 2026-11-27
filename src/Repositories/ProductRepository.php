<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class ProductRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<array{id: int, name: string, slug: string, sort_order: int, product_count: int}>
     */
    public function findActiveCategories(): array
    {
        $rows = $this->db->query(
            'SELECT c.id, c.name, c.slug, c.sort_order, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             GROUP BY c.id, c.name, c.slug, c.sort_order
             ORDER BY c.sort_order ASC, c.name ASC'
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $data = $row->toArray();
            $result[] = [
                'id' => (int) $data['id'],
                'name' => (string) $data['name'],
                'slug' => (string) $data['slug'],
                'sort_order' => (int) $data['sort_order'],
                'product_count' => (int) $data['product_count'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveProducts(?string $categorySlug = null, ?string $search = null): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        $normalizedCategory = $categorySlug !== null ? strtolower(trim($categorySlug)) : '';
        if ($normalizedCategory !== '' && $normalizedCategory !== 'all') {
            $where[] = 'c.slug = %s';
            $params[] = $normalizedCategory;
        }

        $normalizedSearch = $search !== null ? trim($search) : '';
        if ($normalizedSearch !== '') {
            $like = '%' . $normalizedSearch . '%';
            $where[] = '(p.description LIKE %s OR p.sku LIKE %s OR c.name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT
                    p.id,
                    p.category_id,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    p.unit_id,
                    u.name AS unit_name,
                    u.allow_decimal,
                    u.step_size,
                    p.sku,
                    p.slug,
                    p.description,
                    p.image_path,
                    p.unit_price,
                    p.stock_quantity,
                    p.is_active
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN units u ON u.id = p.unit_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.sort_order ASC, p.description ASC';

        $rows = $this->db->query($sql, ...$params)->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $products[] = self::normalizeProductRow($row->toArray());
        }

        return $products;
    }

    /**
     * Loads products by IDs without row-level locking (read-only).
     *
     * @param list<int> $productIds
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $productIds): array
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($uniqueIds === []) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT
                p.id,
                p.category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                p.unit_id,
                u.name AS unit_name,
                u.allow_decimal,
                u.step_size,
                p.sku,
                p.slug,
                p.description,
                p.image_path,
                p.unit_price,
                p.stock_quantity,
                p.is_active
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN units u ON u.id = p.unit_id
             WHERE p.id IN %in',
            $uniqueIds
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $normalized = self::normalizeProductRow($row->toArray());
            $map[(int) $normalized['id']] = $normalized;
        }

        return $map;
    }

    /**
     * Loads products by IDs with row-level lock for order placement inside a transaction.
     *
     * @param list<int> $productIds
     * @return array<int, array<string, mixed>>
     */
    public function findByIdsForUpdate(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT
                p.id,
                p.category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                p.unit_id,
                u.name AS unit_name,
                u.allow_decimal,
                u.step_size,
                p.sku,
                p.slug,
                p.description,
                p.image_path,
                p.unit_price,
                p.stock_quantity,
                p.is_active
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN units u ON u.id = p.unit_id
             WHERE p.id IN %in
             FOR UPDATE',
            $productIds
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $normalized = self::normalizeProductRow($row->toArray());
            $map[(int) $normalized['id']] = $normalized;
        }

        return $map;
    }

    public function decrementStock(int $productId, float $quantity): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE products
             SET stock_quantity = ROUND(stock_quantity - %f, 2),
                 updated_at = %s
             WHERE id = %i',
            $quantity,
            $now,
            $productId
        );
    }

    public function incrementStock(int $productId, float $quantity): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE products
             SET stock_quantity = ROUND(stock_quantity + %f, 2),
                 updated_at = %s
             WHERE id = %i',
            $quantity,
            $now,
            $productId
        );
    }

    /**
     * @return list<array{id: int, name: string, allow_decimal: bool, step_size: float}>
     */
    public function findAllUnits(): array
    {
        $rows = $this->db->query(
            'SELECT id, name, allow_decimal, step_size FROM units ORDER BY id ASC'
        )->fetchAll();

        $units = [];
        foreach ($rows as $row) {
            $data = $row->toArray();
            $units[] = [
                'id' => (int) $data['id'],
                'name' => (string) $data['name'],
                'allow_decimal' => (bool) ((int) ($data['allow_decimal'] ?? 0)),
                'step_size' => (float) ($data['step_size'] ?? 1.0),
            ];
        }

        return $units;
    }

    /**
     * Lists all products (active and inactive) for supplier inventory management.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllProductsForSupplier(
        ?string $categorySlug = null,
        ?string $search = null,
        ?string $activeFilter = null
    ): array {
        $where = ['1 = 1'];
        $params = [];

        $normalizedCategory = $categorySlug !== null ? strtolower(trim($categorySlug)) : '';
        if ($normalizedCategory !== '' && $normalizedCategory !== 'all') {
            $where[] = 'c.slug = %s';
            $params[] = $normalizedCategory;
        }

        $normalizedStatus = $activeFilter !== null ? strtolower(trim($activeFilter)) : 'all';
        if ($normalizedStatus === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($normalizedStatus === 'inactive') {
            $where[] = 'p.is_active = 0';
        }

        $normalizedSearch = $search !== null ? trim($search) : '';
        if ($normalizedSearch !== '') {
            $like = '%' . $normalizedSearch . '%';
            $where[] = '(p.description LIKE %s OR p.sku LIKE %s OR c.name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT
                    p.id,
                    p.category_id,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    p.unit_id,
                    u.name AS unit_name,
                    u.allow_decimal,
                    u.step_size,
                    p.sku,
                    p.slug,
                    p.description,
                    p.image_path,
                    p.unit_price,
                    p.stock_quantity,
                    p.is_active
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN units u ON u.id = p.unit_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.sort_order ASC, p.description ASC';

        $rows = $this->db->query($sql, ...$params)->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $products[] = self::normalizeProductRow($row->toArray());
        }

        return $products;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->query(
            'SELECT
                p.id,
                p.category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                p.unit_id,
                u.name AS unit_name,
                u.allow_decimal,
                u.step_size,
                p.sku,
                p.slug,
                p.description,
                p.image_path,
                p.unit_price,
                p.stock_quantity,
                p.is_active
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN units u ON u.id = p.unit_id
             WHERE p.id = %i
             LIMIT 1',
            $id
        )->fetch();

        return $row ? self::normalizeProductRow($row->toArray()) : null;
    }

    public function categoryExists(int $categoryId): bool
    {
        $id = $this->db->query('SELECT id FROM categories WHERE id = %i LIMIT 1', $categoryId)->fetchSingle();
        return $id !== false && $id !== null;
    }

    public function unitExists(int $unitId): bool
    {
        $id = $this->db->query('SELECT id FROM units WHERE id = %i LIMIT 1', $unitId)->fetchSingle();
        return $id !== false && $id !== null;
    }

    public function existsBySku(string $sku, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $id = $this->db->query(
                'SELECT id FROM products WHERE sku = %s AND id <> %i LIMIT 1',
                $sku,
                $excludeId
            )->fetchSingle();
        } else {
            $id = $this->db->query(
                'SELECT id FROM products WHERE sku = %s LIMIT 1',
                $sku
            )->fetchSingle();
        }

        return $id !== false && $id !== null;
    }

    public function existsBySlug(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $id = $this->db->query(
                'SELECT id FROM products WHERE slug = %s AND id <> %i LIMIT 1',
                $slug,
                $excludeId
            )->fetchSingle();
        } else {
            $id = $this->db->query(
                'SELECT id FROM products WHERE slug = %s LIMIT 1',
                $slug
            )->fetchSingle();
        }

        return $id !== false && $id !== null;
    }

    /**
     * @param array{
     *   category_id: int,
     *   unit_id: int,
     *   sku: string,
     *   slug: string,
     *   description: string,
     *   image_path: ?string,
     *   unit_price: float,
     *   stock_quantity: float,
     *   is_active: bool
     * } $data
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO products', [
            'category_id' => $data['category_id'],
            'unit_id' => $data['unit_id'],
            'sku' => $data['sku'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'image_path' => $data['image_path'],
            'unit_price' => number_format($data['unit_price'], 2, '.', ''),
            'stock_quantity' => number_format($data['stock_quantity'], 2, '.', ''),
            'is_active' => $data['is_active'] ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->getInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $payload = [];
        if (array_key_exists('category_id', $fields)) {
            $payload['category_id'] = (int) $fields['category_id'];
        }
        if (array_key_exists('unit_id', $fields)) {
            $payload['unit_id'] = (int) $fields['unit_id'];
        }
        if (array_key_exists('sku', $fields)) {
            $payload['sku'] = (string) $fields['sku'];
        }
        if (array_key_exists('slug', $fields)) {
            $payload['slug'] = (string) $fields['slug'];
        }
        if (array_key_exists('description', $fields)) {
            $payload['description'] = (string) $fields['description'];
        }
        if (array_key_exists('image_path', $fields)) {
            $payload['image_path'] = $fields['image_path'] !== null ? (string) $fields['image_path'] : null;
        }
        if (array_key_exists('unit_price', $fields)) {
            $payload['unit_price'] = number_format((float) $fields['unit_price'], 2, '.', '');
        }
        if (array_key_exists('stock_quantity', $fields)) {
            $payload['stock_quantity'] = number_format((float) $fields['stock_quantity'], 2, '.', '');
        }
        if (array_key_exists('is_active', $fields)) {
            $payload['is_active'] = ((bool) $fields['is_active']) ? 1 : 0;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        $this->db->query('UPDATE products SET', $payload, 'WHERE id = %i', $id);
    }

    public function hasOrderReferences(int $id): bool
    {
        $rowId = $this->db->query(
            'SELECT id FROM order_items WHERE product_id = %i LIMIT 1',
            $id
        )->fetchSingle();

        return $rowId !== false && $rowId !== null;
    }

    public function deleteById(int $id): void
    {
        $this->db->query('DELETE FROM products WHERE id = %i', $id);
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->db->query(
            'UPDATE products SET is_active = %i, updated_at = %s WHERE id = %i',
            $isActive ? 1 : 0,
            date('Y-m-d H:i:s'),
            $id
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeProductRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'category_id' => (int) $row['category_id'],
            'category_name' => isset($row['category_name']) ? (string) $row['category_name'] : '',
            'category_slug' => isset($row['category_slug']) ? (string) $row['category_slug'] : '',
            'unit_id' => (int) $row['unit_id'],
            'unit_name' => isset($row['unit_name']) ? (string) $row['unit_name'] : 'Unit',
            'allow_decimal' => (bool) ((int) ($row['allow_decimal'] ?? 0)),
            'step_size' => (float) ($row['step_size'] ?? 1.0),
            'sku' => (string) $row['sku'],
            'slug' => (string) $row['slug'],
            'description' => (string) $row['description'],
            'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
            'unit_price' => round((float) $row['unit_price'], 2),
            'stock_quantity' => round((float) $row['stock_quantity'], 2),
            'is_active' => (bool) ((int) ($row['is_active'] ?? 0)),
        ];
    }
}
