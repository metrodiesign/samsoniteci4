<?php

namespace Tests\Ci4;

use App\Database\Migrations\AddUniqueOrderBusinessKey;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

final class OrderBusinessKeyMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('request_order'));
        $this->db->query("DROP TABLE IF EXISTS {$table}");
        $this->db->query("CREATE TABLE {$table} (request_id INTEGER PRIMARY KEY AUTOINCREMENT, orderIDShow VARCHAR(100), customerTel VARCHAR(100), marker VARCHAR(20))");
    }

    public function testUpCreatesNamedUniqueIndexAndEnforcesSqliteBusinessKey(): void
    {
        $migration = $this->migration();
        $migration->up();

        $index = $this->db->getIndexData('request_order')['uq_request_order_order_show_tel'] ?? null;
        self::assertNotNull($index);
        self::assertSame('UNIQUE', $index->type);
        self::assertSame(['orderIDShow', 'customerTel'], $index->fields);

        self::assertTrue($this->db->table('request_order')->insert([
            'orderIDShow' => 'ABC/1', 'customerTel' => '0000000000', 'marker' => 'first',
        ]));
        self::assertTrue($this->db->table('request_order')->insert([
            'orderIDShow' => 'ABC/1', 'customerTel' => '1111111111', 'marker' => 'different-tel',
        ]));
        self::assertTrue($this->db->table('request_order')->insert([
            'orderIDShow' => null, 'customerTel' => '0000000000', 'marker' => 'legacy-null-a',
        ]));
        self::assertTrue($this->db->table('request_order')->insert([
            'orderIDShow' => null, 'customerTel' => '0000000000', 'marker' => 'legacy-null-b',
        ]));
        try {
            $this->db->table('request_order')->insert([
                'orderIDShow' => 'ABC/1', 'customerTel' => '0000000000', 'marker' => 'duplicate',
            ]);
            self::fail('Expected the database to reject the duplicate business key.');
        } catch (DatabaseException $exception) {
            self::assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
        }
    }

    public function testUpAbortsBeforeDdlWhenPreflightFindsDuplicate(): void
    {
        $this->db->table('request_order')->insertBatch([
            ['orderIDShow' => 'ABC/2', 'customerTel' => '0000000000', 'marker' => 'first'],
            ['orderIDShow' => 'ABC/2', 'customerTel' => '0000000000', 'marker' => 'second'],
        ]);
        $before = $this->db->table('request_order')->orderBy('request_id', 'ASC')->get()->getResultArray();

        try {
            $this->migration()->up();
            self::fail('Expected duplicate preflight to abort the migration.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('aborted before DDL', $exception->getMessage());
        }

        self::assertArrayNotHasKey('uq_request_order_order_show_tel', $this->db->getIndexData('request_order'));
        self::assertSame($before, $this->db->table('request_order')->orderBy('request_id', 'ASC')->get()->getResultArray());
    }

    public function testDownDropsOnlyIndexAndPreservesRows(): void
    {
        $migration = $this->migration();
        $migration->up();
        $this->db->table('request_order')->insert([
            'orderIDShow' => 'ABC/3', 'customerTel' => '0000000000', 'marker' => 'preserved',
        ]);

        $migration->down();

        self::assertArrayNotHasKey('uq_request_order_order_show_tel', $this->db->getIndexData('request_order'));
        self::assertSame('preserved', $this->db->table('request_order')->get()->getRow('marker'));
        self::assertTrue($this->db->table('request_order')->insert([
            'orderIDShow' => 'ABC/3', 'customerTel' => '0000000000', 'marker' => 'allowed-after-down',
        ]));
        self::assertSame(2, $this->db->table('request_order')->countAllResults());
    }

    private function migration(): AddUniqueOrderBusinessKey
    {
        return new AddUniqueOrderBusinessKey(Database::forge($this->db), true);
    }
}
