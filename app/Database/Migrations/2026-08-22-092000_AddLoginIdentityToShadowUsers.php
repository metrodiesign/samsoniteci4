<?php

namespace App\Database\Migrations;

use App\Authentication\LegacyUserImporter;
use CodeIgniter\Database\Migration;

final class AddLoginIdentityToShadowUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ci4_users', [
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'email',
            ],
            'display_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
                'after'      => 'username',
            ],
            'group_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'branch_id',
            ],
            'role_text' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'group_id',
            ],
        ]);

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('ci4_users'));
        $index = $this->db->escapeIdentifiers('uq_ci4_users_username');
        $this->db->query("CREATE UNIQUE INDEX {$index} ON {$table} (username)");

        if (ENVIRONMENT !== 'testing') {
            (new LegacyUserImporter($this->db))->import();
        }
    }

    public function down(): void
    {
        $this->forge->dropKey('ci4_users', 'uq_ci4_users_username', false);
        $this->forge->dropColumn('ci4_users', ['username', 'display_name', 'group_id', 'role_text']);
    }
}
