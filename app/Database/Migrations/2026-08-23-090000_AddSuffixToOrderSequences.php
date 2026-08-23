<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSuffixToOrderSequences extends Migration
{
    public function up(): void
    {
        $this->forge->dropTable('ci4_order_sequences', true);
        $this->forge->addField([
            'period' => ['type' => 'CHAR', 'constraint' => 4],
            'suffix' => ['type' => 'VARCHAR', 'constraint' => 10],
            'next_value' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['period', 'suffix']);
        $this->forge->createTable('ci4_order_sequences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_order_sequences', true);
        $this->forge->addField([
            'period' => ['type' => 'CHAR', 'constraint' => 4],
            'next_value' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('period');
        $this->forge->createTable('ci4_order_sequences', true);
    }
}
