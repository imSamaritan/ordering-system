<?php

declare(strict_types=1);

namespace Tests\Integration;

use Dibi\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class SchemaAndSeedTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();

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

    public function testAllNineCoreTablesExist(): void
    {
        $expectedTables = [
            'users',
            'email_verifications',
            'password_resets',
            'categories',
            'units',
            'products',
            'orders',
            'order_items',
            'order_status_history',
            'email_logs',
        ];

        $tables = array_map(
            static fn ($row): string => (string) array_values($row->toArray())[0],
            $this->db->query('SHOW TABLES')->fetchAll()
        );
        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tables, "Expected table '{$table}' to exist.");
        }
    }

    public function testSeededCatalogCountsMatchBlueprint(): void
    {
        $totalProducts = (int) $this->db->query('SELECT COUNT(*) FROM products')->fetchSingle();
        $this->assertSame(98, $totalProducts);

        $byCategory = $this->db->query(
            'SELECT c.name, COUNT(p.id) AS total
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id, c.name'
        )->fetchPairs('name', 'total');

        $this->assertSame(31, (int) ($byCategory['Fruit'] ?? 0));
        $this->assertSame(42, (int) ($byCategory['Vegetable'] ?? 0));
        $this->assertSame(22, (int) ($byCategory['Herb'] ?? 0));
        $this->assertSame(1, (int) ($byCategory['Mushrooms'] ?? 0));
        $this->assertSame(2, (int) ($byCategory['Eggs'] ?? 0));
    }

    public function testUnitsSupportDecimalStepsForKgOnly(): void
    {
        $units = $this->db->query('SELECT name, allow_decimal, step_size FROM units')->fetchAssoc('name');

        $this->assertArrayHasKey('kg', $units);
        $this->assertSame(1, (int) $units['kg']['allow_decimal']);
        $this->assertEqualsWithDelta(0.10, (float) $units['kg']['step_size'], 0.001);

        $this->assertArrayHasKey('Unit', $units);
        $this->assertSame(0, (int) $units['Unit']['allow_decimal']);
        $this->assertEqualsWithDelta(1.00, (float) $units['Unit']['step_size'], 0.001);

        $this->assertArrayHasKey('Punnet', $units);
        $this->assertSame(0, (int) $units['Punnet']['allow_decimal']);
        $this->assertEqualsWithDelta(1.00, (float) $units['Punnet']['step_size'], 0.001);
    }

    public function testDefaultSupplierUserIsSeededAndVerified(): void
    {
        $supplierEmail = $_ENV['SUPPLIER_EMAIL'] ?? 'supplier@freshproduce.co.za';
        $supplier = $this->db->query('SELECT role, email_verified_at, is_active FROM users WHERE email = %s', $supplierEmail)->fetch();

        $this->assertNotNull($supplier);
        $this->assertSame('supplier', $supplier->role);
        $this->assertNotNull($supplier->email_verified_at);
        $this->assertSame(1, (int) $supplier->is_active);
    }
}
