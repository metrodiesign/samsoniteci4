<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateOrderSequences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'period' => ['type' => 'CHAR', 'constraint' => 4],
            'next_value' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('period');
        $this->forge->createTable('ci4_order_sequences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_order_sequences', true);
    }
}
