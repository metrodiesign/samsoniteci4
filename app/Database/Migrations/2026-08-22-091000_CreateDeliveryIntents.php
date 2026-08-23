<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDeliveryIntents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'idempotency_key' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'kind' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'request_id' => [
                'type'       => 'CHAR',
                'constraint' => 32,
            ],
            'payload_ciphertext' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'pending',
            ],
            'attempt_count' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'available_at' => [
                'type' => 'DATETIME',
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('idempotency_key', 'uq_delivery_intents_idempotency');
        $this->forge->addUniqueKey('request_id', 'uq_delivery_intents_request');
        $this->forge->addKey(['status', 'available_at'], false, false, 'idx_delivery_intents_ready');
        $this->forge->createTable('ci4_delivery_intents', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_delivery_intents', true);
    }
}
