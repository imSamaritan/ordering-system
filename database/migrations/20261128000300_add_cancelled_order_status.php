<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCancelledOrderStatus extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('orders');

        $table->changeColumn('status', 'enum', [
            'values' => ['pending', 'received', 'in_progress', 'delivered', 'declined', 'cancelled'],
            'default' => 'pending',
            'null' => false,
        ]);

        if (!$table->hasColumn('cancelled_at')) {
            $table->addColumn('cancelled_at', 'datetime', [
                'null' => true,
                'after' => 'delivered_at',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        // Map any cancelled orders back to declined before shrinking the ENUM
        $this->execute("UPDATE orders SET status = 'declined' WHERE status = 'cancelled'");

        $table = $this->table('orders');

        if ($table->hasColumn('cancelled_at')) {
            $table->removeColumn('cancelled_at');
        }

        $table->changeColumn('status', 'enum', [
            'values' => ['pending', 'received', 'in_progress', 'delivered', 'declined'],
            'default' => 'pending',
            'null' => false,
        ]);

        $table->update();
    }
}
