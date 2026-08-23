<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShadowUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'role_id' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
            ],
            'branch_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'default'    => 1,
            ],
            'session_version' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email', 'uq_ci4_users_email');
        $this->forge->addKey(['role_id', 'branch_id'], false, false, 'idx_ci4_users_role_branch');
        $this->forge->createTable('ci4_users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_users', true);
    }
}
