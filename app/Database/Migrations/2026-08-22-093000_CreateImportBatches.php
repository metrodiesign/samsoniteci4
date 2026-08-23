<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateImportBatches extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'batch_id' => ['type' => 'CHAR', 'constraint' => 32],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 16],
            'owner_user_id' => ['type' => 'INT', 'unsigned' => true],
            'owner_branch_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'state' => ['type' => 'VARCHAR', 'constraint' => 16],
            'file_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'row_count' => ['type' => 'INT', 'unsigned' => true],
            'accepted_count' => ['type' => 'INT', 'unsigned' => true],
            'rejected_count' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'confirmed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('batch_id');
        $this->forge->addKey(['owner_user_id', 'state']);
        $this->forge->createTable('ci4_import_batches', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'CHAR', 'constraint' => 32],
            'row_number' => ['type' => 'INT', 'unsigned' => true],
            'accepted' => ['type' => 'TINYINT', 'unsigned' => true],
            'error_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'payload_ciphertext' => ['type' => 'TEXT'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['batch_id', 'row_number']);
        $this->forge->addKey(['batch_id', 'accepted']);
        $this->forge->createTable('ci4_import_rows', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ci4_import_rows', true);
        $this->forge->dropTable('ci4_import_batches', true);
    }
}
