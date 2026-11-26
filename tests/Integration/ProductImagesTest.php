<?php

declare(strict_types=1);

namespace Tests\Integration;

use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class ProductImagesTest extends TestCase
{
    private Connection $db;
    private string $publicDir;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();
        $this->publicDir = $root . '/public';

        $this->db = new Connection([
            'driver' => 'mysqli',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'] ?? 'ordering_system',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ]);
    }

    public function testAllNinetyEightCatalogProductsHaveValidPngImagesOnDisk(): void
    {
        $products = $this->db->query('SELECT slug, image_path FROM products ORDER BY id')->fetchAll();
        $this->assertCount(98, $products, 'Expected exactly 98 seeded products.');

        $missing = [];
        $invalid = [];

        foreach ($products as $product) {
            $slug = (string) $product->slug;
            $imagePath = (string) ($product->image_path ?? '');
            $expectedRel = "assets/images/products/{$slug}.png";

            $this->assertSame($expectedRel, $imagePath, "Product '{$slug}' has unexpected image_path.");

            $fullPath = $this->publicDir . '/' . $imagePath;
            if (!is_file($fullPath) || filesize($fullPath) < 4096) {
                $missing[] = $slug;
                continue;
            }

            $info = @getimagesize($fullPath);
            if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] !== 512 || $info[1] !== 512) {
                $invalid[] = $slug;
            }
        }

        $this->assertSame([], $missing, 'Missing or empty product image files: ' . implode(', ', $missing));
        $this->assertSame([], $invalid, 'Invalid or non-512x512 PNG files: ' . implode(', ', $invalid));
    }
}
