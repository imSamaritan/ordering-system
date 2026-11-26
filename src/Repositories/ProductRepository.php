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
