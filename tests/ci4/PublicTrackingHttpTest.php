<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PublicTrackingHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedTimeline();
    }

    public function testKnownTrackingIdRendersEnglishAndThaiTimelineInOrder(): void
    {
        $english = $this->get('/tracking/WP00C-TRACK-005');
        $english->assertStatus(200);
        $english->assertSee('Repair tracking');
        $english->assertSee('WP00C-TRACK-005');
        $english->assertSeeInOrder([
            'SYNTHETIC RETURN',
            'SYNTHETIC REPAIR COMPLETE',
            'SYNTHETIC REQUEST',
            'SYNTHETIC NEW',
        ]);
        $english->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        $english->assertDontSee('wp00c-customer-5@example.invalid');

        $thai = $this->get('/tracking-th/WP00C-TRACK-005');
        $thai->assertStatus(200);
        $thai->assertSee('ติดตามงานซ่อม');
        $thai->assertSeeInOrder([
            'สถานะทดสอบ 5',
            'สถานะทดสอบ 4',
            'สถานะทดสอบ 2',
            'สถานะทดสอบ 1',
        ]);
        $thai->assertDontSee('SYNTHETIC CUSTOMER FIVE');
    }

    public function testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch(): void
    {
        $known = $this->get('/tracking?tracking_id=WP00C-TRACK-005');
        $known->assertStatus(200);
        $known->assertSee('SYNTHETIC RETURN');

        foreach (['WP00C-TRACK-999', 'WP00C%', str_repeat('A', 101)] as $trackingId) {
            $result = $this->get('/tracking?tracking_id=' . rawurlencode($trackingId));
            $result->assertStatus(200);
            $result->assertSee('Tracking ID not found');
            $result->assertDontSee('SYNTHETIC RETURN');
            $result->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }
    }

    private function createTables(): void
    {
        $tables = [
            'request_order' => 'request_id INTEGER PRIMARY KEY, trackID VARCHAR(100) NOT NULL, customerFullname VARCHAR(250), customerEmail VARCHAR(100)',
            'status_log'    => 'id INTEGER PRIMARY KEY, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
            'statusaction'  => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(128) NOT NULL, status_name_th VARCHAR(128) NOT NULL',
        ];

        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
    }

    private function seedTimeline(): void
    {
        $this->db->table('request_order')->insert([
            'request_id'      => 91005,
            'trackID'         => 'WP00C-TRACK-005',
            'customerFullname' => 'SYNTHETIC CUSTOMER FIVE',
            'customerEmail'   => 'wp00c-customer-5@example.invalid',
        ]);
        $this->db->table('statusaction')->insertBatch([
            ['status_id' => 1, 'status_name' => 'SYNTHETIC NEW', 'status_name_th' => 'สถานะทดสอบ 1'],
            ['status_id' => 2, 'status_name' => 'SYNTHETIC REQUEST', 'status_name_th' => 'สถานะทดสอบ 2'],
            ['status_id' => 4, 'status_name' => 'SYNTHETIC REPAIR COMPLETE', 'status_name_th' => 'สถานะทดสอบ 4'],
            ['status_id' => 5, 'status_name' => 'SYNTHETIC RETURN', 'status_name_th' => 'สถานะทดสอบ 5'],
        ]);
        $this->db->table('status_log')->insertBatch([
            ['id' => 92007, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 1, 'cdate' => '2026-08-05 00:00:00'],
            ['id' => 92008, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 2, 'cdate' => '2026-08-06 00:00:00'],
            ['id' => 92009, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 4, 'cdate' => '2026-08-07 00:00:00'],
            ['id' => 92010, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 5, 'cdate' => '2026-08-08 00:00:00'],
        ]);
    }
}
