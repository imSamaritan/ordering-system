<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePasswordResetsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('password_resets', ['signed' => false, 'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('hashed_token', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('expiry_date', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['hashed_token'], ['unique' => true, 'name' => 'unique_token'])
            ->addIndex(['user_id'], ['name' => 'idx_password_resets_user_id'])
            ->addIndex(['expiry_date'], ['name' => 'idx_password_resets_expiry_date'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_password_resets_user',
            ])
            ->create();
    }
}
