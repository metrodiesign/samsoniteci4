<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;
use RuntimeException;

final class AddUniqueOrderBusinessKey extends Migration
{
    private const INDEX = 'uq_request_order_order_show_tel';

    private BaseConnection $connection;

    public function __construct(?Forge $forge = null, private bool $runInTests = false)
    {
        parent::__construct($forge);
        $connection = $this->forge->getConnection();
        if (! $connection instanceof BaseConnection) {
            throw new RuntimeException('Unsupported database connection.');
        }
        $this->connection = $connection;
    }

    public function up(): void
    {
        // PHPUnit creates legacy tables per test after the automatic migration pass; the dedicated
        // migration test opts in explicitly once its representative request_order table exists.
        if (ENVIRONMENT === 'testing' && ! $this->runInTests) {
            return;
        }
        if (! $this->connection->tableExists($this->connection->prefixTable('request_order'), false)) {
            throw new RuntimeException('Missing request_order table.');
        }
        $duplicate = $this->connection->table('request_order')
            ->select('orderIDShow, customerTel, COUNT(*) AS duplicate_count', false)
            ->where('orderIDShow IS NOT NULL', null, false)
            ->where('customerTel IS NOT NULL', null, false)
            ->groupBy(['orderIDShow', 'customerTel'])
            ->having('COUNT(*) > 1', null, false)
            ->get()
            ->getRowArray();
        if ($duplicate !== null) {
            throw new RuntimeException('Duplicate order business key detected; migration aborted before DDL.');
        }

        $this->forge->addUniqueKey(['orderIDShow', 'customerTel'], self::INDEX);
        if (! $this->forge->processIndexes('request_order')) {
            throw new RuntimeException('Unable to create order business key index.');
        }
    }

    public function down(): void
    {
        if (ENVIRONMENT === 'testing' && ! $this->runInTests) {
            return;
        }
        if (! $this->connection->tableExists($this->connection->prefixTable('request_order'), false)) {
            throw new RuntimeException('Missing request_order table.');
        }
        if (! array_key_exists(self::INDEX, $this->connection->getIndexData('request_order'))) {
            if (ENVIRONMENT === 'testing') {
                return;
            }
            throw new RuntimeException('Missing order business key index.');
        }
        if (! $this->forge->dropKey('request_order', self::INDEX, false)) {
            throw new RuntimeException('Unable to drop order business key index.');
        }
    }
}
