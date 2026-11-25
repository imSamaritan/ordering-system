<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInitialSchema extends AbstractMigration
{
    public function change(): void
    {
        // 1. users
        $this->table('users', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('role', 'enum', ['values' => ['customer', 'company', 'supplier'], 'default' => 'customer', 'null' => false])
            ->addColumn('company_name', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('contact_name', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('location', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email_verified_at', 'datetime', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true, 'null' => false])
            ->addTimestamps()
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_users_email'])
            ->addIndex(['role', 'is_active'], ['name' => 'idx_users_role_active'])
            ->create();

        // 2. email_verifications
        $this->table('email_verifications', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uq_email_verifications_token_hash'])
            ->addIndex(['user_id'], ['name' => 'idx_email_verifications_user_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION', 'constraint' => 'fk_email_verifications_user'])
            ->create();

        // 3. categories
        $this->table('categories', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('sort_order', 'integer', ['default' => 0, 'null' => false])
            ->addIndex(['name'], ['unique' => true, 'name' => 'uq_categories_name'])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_categories_slug'])
            ->create();

        // 4. units
        $this->table('units', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('name', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('allow_decimal', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('step_size', 'decimal', ['precision' => 4, 'scale' => 2, 'default' => '1.00', 'null' => false])
            ->addIndex(['name'], ['unique' => true, 'name' => 'uq_units_name'])
            ->create();

        // 5. products
        $this->table('products', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('category_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('unit_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('sku', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('image_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('unit_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('stock_quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '100.00', 'null' => false])
            ->addColumn('is_active', 'boolean', ['default' => true, 'null' => false])
            ->addTimestamps()
            ->addIndex(['sku'], ['unique' => true, 'name' => 'uq_products_sku'])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_products_slug'])
            ->addIndex(['category_id', 'is_active'], ['name' => 'idx_products_category_active'])
            ->addIndex(['is_active'], ['name' => 'idx_products_active'])
            ->addForeignKey('category_id', 'categories', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION', 'constraint' => 'fk_products_category'])
            ->addForeignKey('unit_id', 'units', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION', 'constraint' => 'fk_products_unit'])
            ->create();

        // 6. orders
        $this->table('orders', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('order_number', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['pending', 'received', 'in_progress', 'delivered', 'declined'], 'default' => 'pending', 'null' => false])
            ->addColumn('total_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('ordered_at', 'datetime', ['null' => false])
            ->addColumn('received_at', 'datetime', ['null' => true])
            ->addColumn('delivered_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex(['order_number'], ['unique' => true, 'name' => 'uq_orders_order_number'])
            ->addIndex(['user_id', 'ordered_at'], ['name' => 'idx_orders_user_ordered_at'])
            ->addIndex(['status', 'ordered_at'], ['name' => 'idx_orders_status_ordered_at'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION', 'constraint' => 'fk_orders_user'])
            ->create();

        // 7. order_items
        $this->table('order_items', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('product_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('product_name_snapshot', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('unit_name_snapshot', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('unit_price_snapshot', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('line_total', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
            ->addIndex(['order_id'], ['name' => 'idx_order_items_order_id'])
            ->addIndex(['product_id'], ['name' => 'idx_order_items_product_id'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION', 'constraint' => 'fk_order_items_order'])
            ->addForeignKey('product_id', 'products', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION', 'constraint' => 'fk_order_items_product'])
            ->create();

        // 8. order_status_history
        $this->table('order_status_history', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('old_status', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('new_status', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('changed_by_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('changed_at', 'datetime', ['null' => false])
            ->addIndex(['order_id', 'changed_at'], ['name' => 'idx_order_status_history_order_changed'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION', 'constraint' => 'fk_order_status_history_order'])
            ->addForeignKey('changed_by_user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION', 'constraint' => 'fk_order_status_history_user'])
            ->create();

        // 9. email_logs
        $this->table('email_logs', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('order_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('recipient_email', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('email_type', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['sent', 'failed'], 'default' => 'sent', 'null' => false])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('sent_at', 'datetime', ['null' => false])
            ->addIndex(['order_id'], ['name' => 'idx_email_logs_order_id'])
            ->addIndex(['user_id', 'sent_at'], ['name' => 'idx_email_logs_user_sent_at'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION', 'constraint' => 'fk_email_logs_order'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION', 'constraint' => 'fk_email_logs_user'])
            ->create();
    }
}
