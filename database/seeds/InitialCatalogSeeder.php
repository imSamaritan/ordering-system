<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class InitialCatalogSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedUnits();
        $this->seedDefaultSupplier();
        $this->seedProducts();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Fruit', 'slug' => 'fruit', 'sort_order' => 1],
            ['name' => 'Vegetable', 'slug' => 'vegetable', 'sort_order' => 2],
            ['name' => 'Herb', 'slug' => 'herb', 'sort_order' => 3],
            ['name' => 'Mushrooms', 'slug' => 'mushrooms', 'sort_order' => 4],
            ['name' => 'Eggs', 'slug' => 'eggs', 'sort_order' => 5],
        ];

        foreach ($categories as $cat) {
            $exists = $this->fetchRow("SELECT id FROM categories WHERE slug = '{$cat['slug']}'");
            if (!$exists) {
                $this->table('categories')->insert($cat)->saveData();
            }
        }
    }

    private function seedUnits(): void
    {
        $units = [
            ['name' => 'kg', 'allow_decimal' => 1, 'step_size' => 0.10],
            ['name' => 'Unit', 'allow_decimal' => 0, 'step_size' => 1.00],
            ['name' => 'Punnet', 'allow_decimal' => 0, 'step_size' => 1.00],
        ];

        foreach ($units as $unit) {
            $exists = $this->fetchRow("SELECT id FROM units WHERE name = '{$unit['name']}'");
            if (!$exists) {
                $this->table('units')->insert($unit)->saveData();
            }
        }
    }

    private function seedDefaultSupplier(): void
    {
        $email = $_ENV['SUPPLIER_EMAIL'] ?? 'supplier@freshproduce.co.za';
        $password = $_ENV['SUPPLIER_PASSWORD'] ?? 'Supplier@2026!';

        $quotedEmail = $this->getAdapter()->getConnection()->quote($email);
        $exists = $this->fetchRow("SELECT id FROM users WHERE email = {$quotedEmail}");
        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('users')->insert([
            'role' => 'supplier',
            'company_name' => 'Fresh Produce Wholesale SA',
            'contact_name' => 'Primary Supplier Admin',
            'email' => $email,
            'phone' => '+27 11 555 0100',
            'location' => 'Johannesburg Fresh Produce Market, City Deep',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->saveData();
    }

    private function seedProducts(): void
    {
        $categoryMap = [];
        foreach ($this->fetchAll('SELECT id, name FROM categories') as $row) {
            $categoryMap[$row['name']] = (int) $row['id'];
        }

        $unitMap = [];
        foreach ($this->fetchAll('SELECT id, name FROM units') as $row) {
            $unitMap[$row['name']] = (int) $row['id'];
        }

        /**
         * Exact 98 active catalog items derived from the 4-page Produce & Inventory Catalog PDF
         * (31 Fruit, 42 Vegetable, 22 Herb, 1 Mushrooms, 2 Eggs).
         * Format: [description, category, unit, default_zar_price]
         */
        $catalog = [
            // Fruit (31)
            ['Apple Granny', 'Fruit', 'kg', 28.50],
            ['Apple Red', 'Fruit', 'kg', 29.90],
            ['Apple Standard', 'Fruit', 'kg', 25.00],
            ['Avocado', 'Fruit', 'kg', 65.00],
            ['Bananas', 'Fruit', 'kg', 24.50],
            ['Blue Berry Punnet 125g', 'Fruit', 'Punnet', 38.00],
            ['Fruit pack (4 variety)', 'Fruit', 'Unit', 55.00],
            ['Gooseberries', 'Fruit', 'kg', 95.00],
            ['Granadilla', 'Fruit', 'kg', 85.00],
            ['Grapefruit kg', 'Fruit', 'kg', 24.00],
            ['Grapes Green Punnet', 'Fruit', 'Punnet', 36.00],
            ['Grapes Red Punnet', 'Fruit', 'Punnet', 36.00],
            ['Kiwi Fruit', 'Fruit', 'kg', 78.00],
            ['Lemons', 'Fruit', 'kg', 26.00],
            ['Limes', 'Fruit', 'kg', 48.00],
            ['Litchi', 'Fruit', 'kg', 68.00],
            ['Melon', 'Fruit', 'Unit', 34.00],
            ['Naartjie', 'Fruit', 'kg', 29.00],
            ['Nectarines', 'Fruit', 'kg', 46.00],
            ['Oranges', 'Fruit', 'kg', 22.00],
            ['PawPaw', 'Fruit', 'kg', 32.00],
            ['Pears', 'Fruit', 'kg', 31.00],
            ['Pines', 'Fruit', 'Unit', 28.00],
            ['Plums', 'Fruit', 'kg', 44.00],
            ['Pomegranate', 'Fruit', 'kg', 72.00],
            ['Sponspek', 'Fruit', 'Unit', 35.00],
            ['Strawberry Punnet', 'Fruit', 'Punnet', 34.00],
            ['Tomato Cocktail 200g', 'Fruit', 'kg', 54.00],
            ['Tomatoes', 'Fruit', 'kg', 26.50],
            ['Watermelon', 'Fruit', 'Unit', 65.00],
            ['Watermelon Sugar Babies', 'Fruit', 'kg', 18.50],

            // Vegetable (42)
            ['Amadumbe', 'Vegetable', 'kg', 36.00],
            ['Asparagus Mini 100g', 'Vegetable', 'Punnet', 42.00],
            ['Asparagus Spears', 'Vegetable', 'Unit', 45.00],
            ['Baby Corn', 'Vegetable', 'kg', 64.00],
            ['Baby Gemsquash', 'Vegetable', 'kg', 34.00],
            ['Baby Marrow', 'Vegetable', 'kg', 38.00],
            ['Baby Spinach', 'Vegetable', 'kg', 58.00],
            ['Beetroot', 'Vegetable', 'kg', 19.50],
            ['Brinjal', 'Vegetable', 'kg', 32.00],
            ['Broccoli', 'Vegetable', 'Unit', 26.00],
            ['Broccoli Long Stem', 'Vegetable', 'kg', 68.00],
            ['Brussel', 'Vegetable', 'kg', 62.00],
            ['Butternut', 'Vegetable', 'kg', 16.50],
            ['Cabbage Green', 'Vegetable', 'Unit', 22.00],
            ['Cabbage Red', 'Vegetable', 'Unit', 28.00],
            ['Carrot', 'Vegetable', 'kg', 18.00],
            ['Carrot Baby', 'Vegetable', 'kg', 36.00],
            ['Cauliflower', 'Vegetable', 'Unit', 29.00],
            ['Celery', 'Vegetable', 'Unit', 24.00],
            ['Cucumber', 'Vegetable', 'Unit', 16.00],
            ['Gemsquash', 'Vegetable', 'kg', 22.00],
            ['Green Bean', 'Vegetable', 'kg', 44.00],
            ['Leeks', 'Vegetable', 'kg', 38.00],
            ['Lettuce Iceberg', 'Vegetable', 'kg', 28.00],
            ['Onion Sliced', 'Vegetable', 'kg', 34.00],
            ['Onions', 'Vegetable', 'kg', 19.00],
            ['Onions Red', 'Vegetable', 'kg', 26.00],
            ['Pattypan Green', 'Vegetable', 'Punnet', 26.00],
            ['Pattypan Yellow', 'Vegetable', 'Punnet', 26.00],
            ['Pepper Green', 'Vegetable', 'kg', 36.00],
            ['Pepper Red', 'Vegetable', 'kg', 48.00],
            ['Pepper Robot Pack each', 'Vegetable', 'Unit', 34.00],
            ['Pepper Yellow', 'Vegetable', 'kg', 48.00],
            ['Potato', 'Vegetable', 'kg', 18.50],
            ['Potato Baby', 'Vegetable', 'kg', 29.00],
            ['Pumpkin', 'Vegetable', 'kg', 15.50],
            ['Salad Pack Lettuce Green', 'Vegetable', 'Unit', 25.00],
            ['Salad Pack Lettuce Red', 'Vegetable', 'Unit', 27.00],
            ['Spinach Bunch', 'Vegetable', 'Unit', 18.00],
            ['Sugar Snap Pea 85g', 'Vegetable', 'Punnet', 32.00],
            ['Sweet Corn', 'Vegetable', 'kg', 35.00],
            ['Sweet Potato', 'Vegetable', 'kg', 24.00],

            // Herb (22)
            ['Basil', 'Herb', 'kg', 140.00],
            ['Chilli Green', 'Herb', 'kg', 65.00],
            ['Chilli Red', 'Herb', 'kg', 68.00],
            ['Chives', 'Herb', 'kg', 135.00],
            ['Coriander', 'Herb', 'kg', 110.00],
            ['Curry Leaves', 'Herb', 'kg', 150.00],
            ['Dill', 'Herb', 'kg', 135.00],
            ['Flower Edible', 'Herb', 'kg', 220.00],
            ['Garlic', 'Herb', 'kg', 95.00],
            ['Garlic Crushed', 'Herb', 'kg', 105.00],
            ['Ginger', 'Herb', 'kg', 98.00],
            ['Lemon Grass', 'Herb', 'kg', 120.00],
            ['Micro Herb', 'Herb', 'kg', 240.00],
            ['Mint', 'Herb', 'kg', 125.00],
            ['Parsley', 'Herb', 'kg', 95.00],
            ['Parsley Italian', 'Herb', 'kg', 105.00],
            ['Petite leaf Crimson', 'Herb', 'Punnet', 34.00],
            ['Radish', 'Herb', 'kg', 42.00],
            ['Rocket', 'Herb', 'kg', 130.00],
            ['Rosemary', 'Herb', 'kg', 130.00],
            ['Spring Onion', 'Herb', 'kg', 52.00],
            ['Thyme', 'Herb', 'kg', 135.00],

            // Mushrooms (1)
            ['Mushroom', 'Mushrooms', 'kg', 88.00],

            // Eggs (2)
            ['Eggs Large (30) - Free Range', 'Eggs', 'Unit', 115.00],
            ['Eggs Large (30) - Plain', 'Eggs', 'Unit', 89.00],
        ];

        $prefixMap = [
            'Fruit' => 'FRT',
            'Vegetable' => 'VEG',
            'Herb' => 'HRB',
            'Mushrooms' => 'MSH',
            'Eggs' => 'EGG',
        ];

        $counters = [];
        $now = date('Y-m-d H:i:s');
        $pdo = $this->getAdapter()->getConnection();

        foreach ($catalog as [$description, $categoryName, $unitName, $price]) {
            $slug = $this->slugify($description);
            $quotedSlug = $pdo->quote($slug);
            $exists = $this->fetchRow("SELECT id FROM products WHERE slug = {$quotedSlug}");
            if ($exists) {
                continue;
            }

            $counters[$categoryName] = ($counters[$categoryName] ?? 0) + 1;
            $sku = sprintf('%s-%03d', $prefixMap[$categoryName], $counters[$categoryName]);

            $this->table('products')->insert([
                'category_id' => $categoryMap[$categoryName],
                'unit_id' => $unitMap[$unitName],
                'sku' => $sku,
                'slug' => $slug,
                'description' => $description,
                'image_path' => "assets/images/products/{$slug}.png",
                'unit_price' => $price,
                'stock_quantity' => 100.00,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])->saveData();
        }
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
