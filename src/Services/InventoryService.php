<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepository;

final class InventoryService
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    /**
     * @return array{
     *   products: list<array<string, mixed>>,
     *   categories: list<array<string, mixed>>,
     *   units: list<array<string, mixed>>,
     *   summary: array{total: int, active: int, inactive: int, low_stock: int}
     * }
     */
    public function listInventory(
        ?string $categorySlug = null,
        ?string $search = null,
        ?string $activeFilter = null
    ): array {
        $products = $this->productRepository->findAllProductsForSupplier($categorySlug, $search, $activeFilter);
        $categories = $this->productRepository->findActiveCategories();
        $units = $this->productRepository->findAllUnits();

        $activeCount = 0;
        $inactiveCount = 0;
        $lowStockCount = 0;
        foreach ($products as $product) {
            if ($product['is_active']) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
            if ((float) $product['stock_quantity'] <= 10.0) {
                $lowStockCount++;
            }
        }

        return [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'summary' => [
                'total' => count($products),
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'low_stock' => $lowStockCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   errors?: array<string, string>,
     *   product?: array<string, mixed>
     * }
     */
    public function createProduct(array $input): array
    {
        $errors = [];

        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '' || mb_strlen($description) < 2) {
            $errors['description'] = 'Product description must be at least 2 characters.';
        } elseif (mb_strlen($description) > 191) {
            $errors['description'] = 'Product description must not exceed 191 characters.';
        }

        $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId <= 0 || !$this->productRepository->categoryExists($categoryId)) {
            $errors['category_id'] = 'A valid category is required.';
        }

        $unitId = filter_var($input['unit_id'] ?? null, FILTER_VALIDATE_INT);
        if ($unitId === false || $unitId <= 0 || !$this->productRepository->unitExists($unitId)) {
            $errors['unit_id'] = 'A valid unit is required.';
        }

        $unitPrice = self::parseNonNegativeDecimal($input['unit_price'] ?? null);
        if ($unitPrice === null || $unitPrice > 999999.99) {
            $errors['unit_price'] = 'Unit price must be a valid non-negative ZAR amount.';
        }

        $stockQuantity = self::parseNonNegativeDecimal($input['stock_quantity'] ?? 100);
        if ($stockQuantity === null || $stockQuantity > 999999.99) {
            $errors['stock_quantity'] = 'Stock quantity must be a valid non-negative number.';
        }

        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        if ($sku !== '' && mb_strlen($sku) > 64) {
            $errors['sku'] = 'SKU must not exceed 64 characters.';
        } elseif ($sku !== '' && $this->productRepository->existsBySku($sku)) {
            $errors['sku'] = 'A product with this SKU already exists.';
        }

        $imagePath = isset($input['image_path']) ? trim((string) $input['image_path']) : '';
        if ($imagePath !== '' && mb_strlen($imagePath) > 255) {
            $errors['image_path'] = 'Image path must not exceed 255 characters.';
        }

        $isActive = array_key_exists('is_active', $input)
            ? filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true
            : true;

        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please fix the highlighted product fields.',
                'errors' => $errors,
            ];
        }

        if ($sku === '') {
            $sku = $this->generateUniqueSku($description);
        }

        $slugBase = isset($input['slug']) && trim((string) $input['slug']) !== ''
            ? self::slugify((string) $input['slug'])
            : self::slugify($description);
        $slug = $this->generateUniqueSlug($slugBase !== '' ? $slugBase : 'product');

        $productId = $this->productRepository->create([
            'category_id' => (int) $categoryId,
            'unit_id' => (int) $unitId,
            'sku' => $sku,
            'slug' => $slug,
            'description' => $description,
            'image_path' => $imagePath !== '' ? $imagePath : null,
            'unit_price' => (float) $unitPrice,
            'stock_quantity' => (float) $stockQuantity,
            'is_active' => $isActive,
        ]);

        $product = $this->productRepository->findById($productId);

        return [
            'ok' => true,
            'status' => 201,
            'message' => sprintf('Product "%s" added to inventory.', $description),
            'product' => $product ?? ['id' => $productId],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   errors?: array<string, string>,
     *   product?: array<string, mixed>
     * }
     */
    public function updateProduct(int $productId, array $input): array
    {
        $existing = $this->productRepository->findById($productId);
        if ($existing === null) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Product not found.',
            ];
        }

        $errors = [];
        $updates = [];

        if (array_key_exists('description', $input)) {
            $description = trim((string) $input['description']);
            if ($description === '' || mb_strlen($description) < 2) {
                $errors['description'] = 'Product description must be at least 2 characters.';
            } elseif (mb_strlen($description) > 191) {
                $errors['description'] = 'Product description must not exceed 191 characters.';
            } else {
                $updates['description'] = $description;
            }
        }

        if (array_key_exists('category_id', $input)) {
            $categoryId = filter_var($input['category_id'], FILTER_VALIDATE_INT);
            if ($categoryId === false || $categoryId <= 0 || !$this->productRepository->categoryExists($categoryId)) {
                $errors['category_id'] = 'A valid category is required.';
            } else {
                $updates['category_id'] = $categoryId;
            }
        }

        if (array_key_exists('unit_id', $input)) {
            $unitId = filter_var($input['unit_id'], FILTER_VALIDATE_INT);
            if ($unitId === false || $unitId <= 0 || !$this->productRepository->unitExists($unitId)) {
                $errors['unit_id'] = 'A valid unit is required.';
            } else {
                $updates['unit_id'] = $unitId;
            }
        }

        if (array_key_exists('unit_price', $input)) {
            $unitPrice = self::parseNonNegativeDecimal($input['unit_price']);
            if ($unitPrice === null || $unitPrice > 999999.99) {
                $errors['unit_price'] = 'Unit price must be a valid non-negative ZAR amount.';
            } else {
                $updates['unit_price'] = $unitPrice;
            }
        }

        if (array_key_exists('stock_quantity', $input)) {
            $stockQuantity = self::parseNonNegativeDecimal($input['stock_quantity']);
            if ($stockQuantity === null || $stockQuantity > 999999.99) {
                $errors['stock_quantity'] = 'Stock quantity must be a valid non-negative number.';
            } else {
                $updates['stock_quantity'] = $stockQuantity;
            }
        }

        if (array_key_exists('sku', $input)) {
            $sku = strtoupper(trim((string) $input['sku']));
            if ($sku === '' || mb_strlen($sku) > 64) {
                $errors['sku'] = 'SKU must be between 1 and 64 characters.';
            } elseif ($this->productRepository->existsBySku($sku, $productId)) {
                $errors['sku'] = 'Another product already uses this SKU.';
            } else {
                $updates['sku'] = $sku;
            }
        }

        if (array_key_exists('image_path', $input)) {
            $imagePath = $input['image_path'] !== null ? trim((string) $input['image_path']) : '';
            if (mb_strlen($imagePath) > 255) {
                $errors['image_path'] = 'Image path must not exceed 255 characters.';
            } else {
                $updates['image_path'] = $imagePath !== '' ? $imagePath : null;
            }
        }

        if (array_key_exists('is_active', $input)) {
            $parsedActive = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsedActive === null) {
                $errors['is_active'] = 'Active status must be a boolean.';
            } else {
                $updates['is_active'] = $parsedActive;
            }
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Please fix the highlighted product fields.',
                'errors' => $errors,
            ];
        }

        if ($updates !== []) {
            $this->productRepository->update($productId, $updates);
        }

        $updated = $this->productRepository->findById($productId);

        return [
            'ok' => true,
            'status' => 200,
            'message' => sprintf('Product "%s" updated.', (string) ($updated['description'] ?? $existing['description'])),
            'product' => $updated ?? $existing,
        ];
    }

    /**
     * Deletes a product if it has no historical order_items references;
     * otherwise deactivates it so historical invoices remain intact.
     *
     * @return array{
     *   ok: bool,
     *   status: int,
     *   message: string,
     *   action?: 'deleted'|'deactivated',
     *   product?: array<string, mixed>,
     *   deleted_id?: int
     * }
     */
    public function deleteProduct(int $productId): array
    {
        $existing = $this->productRepository->findById($productId);
        if ($existing === null) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Product not found.',
            ];
        }

        if ($this->productRepository->hasOrderReferences($productId)) {
            $this->productRepository->setActive($productId, false);
            $deactivated = $this->productRepository->findById($productId);

            return [
                'ok' => true,
                'status' => 200,
                'action' => 'deactivated',
                'message' => sprintf(
                    'Product "%s" has existing order history and was deactivated instead of permanently deleted.',
                    (string) $existing['description']
                ),
                'product' => $deactivated ?? $existing,
            ];
        }

        $this->productRepository->deleteById($productId);

        return [
            'ok' => true,
            'status' => 200,
            'action' => 'deleted',
            'message' => sprintf('Product "%s" deleted from inventory.', (string) $existing['description']),
            'deleted_id' => $productId,
        ];
    }

    public static function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    public static function parseNonNegativeDecimal(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric(trim($value)))) {
            return null;
        }

        $num = (float) $value;
        if (!is_finite($num) || $num < 0) {
            return null;
        }

        return round($num, 2);
    }

    private function generateUniqueSku(string $description): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $description) ?? '');
        $prefix = substr($clean !== '' ? $clean : 'PRD', 0, 4);

        for ($i = 0; $i < 10; $i++) {
            $suffix = strtoupper(bin2hex(random_bytes(3)));
            $candidate = "PRD-{$prefix}-{$suffix}";
            if (!$this->productRepository->existsBySku($candidate)) {
                return $candidate;
            }
        }

        return 'PRD-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $candidate = substr($baseSlug, 0, 175);
        if (!$this->productRepository->existsBySlug($candidate)) {
            return $candidate;
        }

        for ($i = 2; $i <= 50; $i++) {
            $numbered = substr($baseSlug, 0, 170) . '-' . $i;
            if (!$this->productRepository->existsBySlug($numbered)) {
                return $numbered;
            }
        }

        return substr($baseSlug, 0, 165) . '-' . bin2hex(random_bytes(3));
    }
}
