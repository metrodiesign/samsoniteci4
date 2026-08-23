<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePasswordResetAudit extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'event' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'identity_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'client_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'occurred_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('ci4_password_reset_audit', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_password_reset_audit', true);
    }
}
