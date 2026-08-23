<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePasswordResetTokens extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
            ],
            'purpose' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'request_id' => [
                'type'       => 'CHAR',
                'constraint' => 32,
            ],
            'token_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'consumed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash', 'uq_password_reset_token_hash');
        $this->forge->addKey(['user_id', 'purpose'], false, false, 'idx_password_reset_user_purpose');
        $this->forge->addKey('expires_at', false, false, 'idx_password_reset_expires');
        $this->forge->createTable('ci4_password_reset_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_password_reset_tokens', true);
    }
}
