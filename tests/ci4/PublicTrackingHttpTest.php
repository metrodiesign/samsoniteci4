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
        $english->assertSee('05/08/2569');
        $english->assertSee('08/08/2569');

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
        $thai->assertSee('05/08/2569');
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

    public function testPublicLayoutShowsPublicChromeWithoutAdminControls(): void
    {
        $page = $this->get('/tracking');
        $page->assertStatus(200);
        $page->assertSee('TRACK &amp; TRACE');
        $page->assertSee('main-logo.png');
        $page->assertSee('02-761-9999');
        $page->assertSee('https://www.samsonite.co.th/');
        $page->assertDontSee('Sign out');
        $page->assertDontSee('Dashboard');
    }

    public function testCompleteStatusRendersGreenCircleInBothLanguages(): void
    {
        $english = $this->get('/tracking/WP00C-TRACK-006');
        $english->assertStatus(200);
        $english->assertSee('circle-awe bg-success circle-awe-animate');

        $thai = $this->get('/tracking-th/WP00C-TRACK-006');
        $thai->assertStatus(200);
        $thai->assertSee('circle-awe bg-success circle-awe-animate');
    }

    public function testTrackingFormShowsLanguageSpecificHowToCheckPopup(): void
    {
        $english = $this->get('/tracking');
        $english->assertStatus(200);
        $english->assertSee('CHECK NOW');
        $english->assertSee('popup_en.png');
        $english->assertDontSee('popup_th.png');

        $thai = $this->get('/tracking-th');
        $thai->assertStatus(200);
        $thai->assertSee('popup_th.png');
        $thai->assertDontSee('popup_en.png');
    }

    public function testResultBannerRendersWhenBackgroundPublishedAndOmittedWhenNull(): void
    {
        // Null branch: no tbl_background_web → banner cut whole.
        $withoutBanner = $this->get('/tracking/WP00C-TRACK-006');
        $withoutBanner->assertStatus(200);
        $withoutBanner->assertDontSee('banner-control');

        $name = str_repeat('a', 32) . '.png';
        // Full BackgroundStore::FIELDS schema: the shared in-memory connection keeps this table
        // for later suites, which query other image fields — a partial schema would break them.
        $columns = implode(', ', array_map(
            static fn (string $field): string => $field . ' VARCHAR(64)',
            \App\Master\BackgroundStore::FIELDS,
        ));
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('tbl_background_web'));
        $this->db->query("DROP TABLE IF EXISTS {$table}");
        $this->db->query("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, status INTEGER, {$columns})");
        $this->db->table('tbl_background_web')->insert([
            'id'                       => 1,
            'status'                   => 1,
            'image_trackstatus_laptop' => $name,
        ]);

        $withBanner = $this->get('/tracking/WP00C-TRACK-006');
        $withBanner->assertStatus(200);
        $withBanner->assertSee('banner-control');
        $withBanner->assertSee('/background-image/' . $name);
    }

    public function testResultViewEmptyTimelineShowsUnpassPlaceholder(): void
    {
        $html = view('tracking_result', [
            'language'              => 'en',
            'trackId'               => 'WP00C-TRACK-005',
            'timeline'              => [],
            'backgroundImage'       => null,
            'backgroundImageMobile' => null,
        ]);

        $this->assertStringContainsString('circle-awe bg-unpass', $html);
        $this->assertStringContainsString('ไม่มีสินค้า', $html);
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
            // 'complete' drives the green circle. status_name_th deliberately NOT 'complete'
            // so the bg-success mutation (comparing status_name_th) turns the TH case red.
            ['status_id' => 6, 'status_name' => 'complete', 'status_name_th' => 'เสร็จสมบูรณ์'],
        ]);
        $this->db->table('status_log')->insertBatch([
            ['id' => 92007, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 1, 'cdate' => '2026-08-05 00:00:00'],
            ['id' => 92008, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 2, 'cdate' => '2026-08-06 00:00:00'],
            ['id' => 92009, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 4, 'cdate' => '2026-08-07 00:00:00'],
            ['id' => 92010, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 5, 'cdate' => '2026-08-08 00:00:00'],
        ]);

        // Separate order carrying a 'complete' status, kept apart from WP00C-TRACK-005 so the
        // locked in-order assertions there stay untouched.
        $this->db->table('request_order')->insert([
            'request_id'       => 91006,
            'trackID'          => 'WP00C-TRACK-006',
            'customerFullname' => 'SYNTHETIC CUSTOMER SIX',
            'customerEmail'    => 'wp00c-customer-6@example.invalid',
        ]);
        $this->db->table('status_log')->insertBatch([
            ['id' => 92020, 'order_id' => 'WP00C-TRACK-006', 'action_id' => 1, 'cdate' => '2026-08-10 00:00:00'],
            ['id' => 92021, 'order_id' => 'WP00C-TRACK-006', 'action_id' => 6, 'cdate' => '2026-08-12 00:00:00'],
        ]);
    }
    public function testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract(): void
    {
        $canonical = $this->get('/tracking?tracking_id=WP00C-TRACK-005&searchText=WP00C-TRACK-999');
        $canonical->assertStatus(200);
        $canonical->assertSee('SYNTHETIC RETURN');

        $legacy = $this->get('/tracking?searchText=WP00C-TRACK-005');
        $legacy->assertStatus(200);
        $legacy->assertSee('SYNTHETIC RETURN');

        foreach ([
            '/tracking?tracking_id=%20WP00C-TRACK-005%20&searchText=WP00C-TRACK-005',
            '/tracking?searchText=%20WP00C-TRACK-005%20',
            '/tracking?tracking_id%5B%5D=WP00C-TRACK-005&searchText=WP00C-TRACK-005',
        ] as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $response->assertSee('TRACK &amp; TRACE');
            $response->assertDontSee('SYNTHETIC RETURN');
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }
    }
}
