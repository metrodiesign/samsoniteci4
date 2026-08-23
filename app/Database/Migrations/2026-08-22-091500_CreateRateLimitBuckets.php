<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateRateLimitBuckets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'bucket_key' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'request_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1,
            ],
            'window_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addPrimaryKey('bucket_key');
        $this->forge->addKey('expires_at', false, false, 'idx_rate_limit_buckets_expiry');
        $this->forge->createTable('ci4_rate_limit_buckets', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_rate_limit_buckets', true);
    }
}
