<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Orders\OrderSequence;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;

final class OrderHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $encryption = new Encryption();
        $encryption->driver = 'Sodium';
        $encryption->key = str_repeat("\x40", 32);
        Services::injectMock('encrypter', Services::encrypter($encryption, false));
        foreach ([
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL UNIQUE, bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerTel2 VARCHAR(100), customerEmail VARCHAR(100), detailAgent VARCHAR(10), detailTypeId INTEGER, detailBrandId INTEGER, detailDatePurchase DATETIME, detailSKUName VARCHAR(250), detailNumberWaranty VARCHAR(250), detailCondition VARCHAR(250), detailConditionOther VARCHAR(250), detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250), detailFixed VARCHAR(250), detailFixedOther VARCHAR(250), detailEquipment VARCHAR(250), warantyType INTEGER, detailNote TEXT, detailImage VARCHAR(500), branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, provider_id INTEGER, logistics_etc_detail TEXT, date_create DATETIME, date_repair DATETIME, date_repair_waranty DATETIME, date_repair_complete DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, action_status INTEGER, RepairPrice DECIMAL(8,2), number_cmg VARCHAR(100), waranty_cmg VARCHAR(10), create_by_user VARCHAR(250), CONSTRAINT uq_request_order_order_show_tel UNIQUE (orderIDShow, customerTel)',
            'book' => 'book_id INTEGER PRIMARY KEY, branch_id INTEGER NOT NULL, book_detail VARCHAR(3) NOT NULL, status INTEGER NOT NULL',
            'status_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
            'statusaction' => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(250) NOT NULL, status_name_th VARCHAR(250)',
            'provider' => 'provider_id INTEGER PRIMARY KEY, provider_name VARCHAR(250) NOT NULL',
            'brand' => 'brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250) NOT NULL',
            'type' => 'type_id INTEGER PRIMARY KEY, type_details VARCHAR(250) NOT NULL',
            'condition' => 'condition_id INTEGER PRIMARY KEY, condition_details VARCHAR(250) NOT NULL',
            'estimateprice' => 'estimateprice_id INTEGER PRIMARY KEY, estimateprice_details VARCHAR(250) NOT NULL',
            'fixed' => 'fixed_id INTEGER PRIMARY KEY, fixed_details VARCHAR(250) NOT NULL',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER NOT NULL, branch_user_name VARCHAR(100), branch_name VARCHAR(250) NOT NULL, default_suffix VARCHAR(10), book_order VARCHAR(10), customer_ref VARCHAR(50)',
            'branch_type' => 'branch_type_id INTEGER PRIMARY KEY, branch_type_details VARCHAR(250) NOT NULL, branch_type_image VARCHAR(250)',
            'tracking_status' => 'status_id INTEGER PRIMARY KEY, description_th VARCHAR(250)',
            'uploadstaus' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, tracking_id VARCHAR(100), Telephone VARCHAR(100), tracking_status INTEGER, cdate DATETIME',
            'rating' => 'rating_id INTEGER PRIMARY KEY AUTOINCREMENT, add_id INTEGER NOT NULL, rating INTEGER NOT NULL, order_id VARCHAR(100) NOT NULL, branchID INTEGER NOT NULL, cdate DATETIME NOT NULL',
            'rating_comment' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, track_id VARCHAR(100) NOT NULL, branch_id INTEGER NOT NULL, comment TEXT NOT NULL, created_at DATETIME NOT NULL',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->table('ci4_users')->truncate();
        $this->db->table('ci4_delivery_intents')->truncate();
        if ($this->db->tableExists($this->db->prefixTable('ci4_order_sequences'), false)) {
            $this->db->table('ci4_order_sequences')->truncate();
        }
        $this->db->resetDataCache();
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'branch-a', 'branch_name' => 'BRANCH A', 'default_suffix' => 'WPA', 'book_order' => 'WPA', 'customer_ref' => 'WPA'],
            ['branch_id' => 2, 'branch_type' => 2, 'branch_user_name' => 'branch-b', 'branch_name' => 'BRANCH B', 'default_suffix' => 'WPB', 'book_order' => 'WPB', 'customer_ref' => 'WPB'],
        ]);
        $this->db->table('branch_type')->insertBatch([
            ['branch_type_id' => 1, 'branch_type_details' => 'TYPE ONE'],
            ['branch_type_id' => 2, 'branch_type_details' => 'TYPE TWO'],
        ]);
        $this->db->table('book')->insertBatch([
            ['book_id' => 1, 'branch_id' => 1, 'book_detail' => 'ABC', 'status' => 1],
            ['book_id' => 2, 'branch_id' => 1, 'book_detail' => 'OLD', 'status' => 0],
            ['book_id' => 3, 'branch_id' => 2, 'book_detail' => 'XYZ', 'status' => 1],
            ['book_id' => 4, 'branch_id' => 2, 'book_detail' => 'ABC', 'status' => 1],
        ]);
        $this->db->table('brand')->insert(['brand_id' => 1, 'brand_details' => 'BRAND A']);
        $this->db->table('type')->insert(['type_id' => 1, 'type_details' => 'TYPE A']);
        $this->db->table('provider')->insert(['provider_id' => 1, 'provider_name' => 'PROVIDER A']);
        $this->db->table('condition')->insertBatch([
            ['condition_id' => 1, 'condition_details' => 'CONDITION ONE'],
            ['condition_id' => 2, 'condition_details' => 'CONDITION TWO'],
            ['condition_id' => 3, 'condition_details' => 'CONDITION THREE'],
        ]);
        $this->db->table('estimateprice')->insertBatch([
            ['estimateprice_id' => 1, 'estimateprice_details' => 'ESTIMATE ONE'],
            ['estimateprice_id' => 2, 'estimateprice_details' => 'ESTIMATE TWO'],
        ]);
        $this->db->table('fixed')->insertBatch([
            ['fixed_id' => 1, 'fixed_details' => 'FIXED ONE'],
            ['fixed_id' => 2, 'fixed_details' => 'FIXED TWO'],
        ]);
        for ($status = 1; $status <= 8; $status++) {
            $this->db->table('statusaction')->insert(['status_id' => $status, 'status_name' => 'STATUS ' . $status, 'status_name_th' => 'สถานะ ' . $status]);
            $this->db->table('request_order')->insert([
                'request_id' => 91000 + $status, 'requestDate' => sprintf('2026-08-%02d 00:00:00', $status),
                'trackID' => 'WP00C-TRACK-00' . $status, 'bookID' => '1', 'numberID' => 'N' . $status,
                'orderID' => 'O' . $status, 'orderIDShow' => 'WPC/' . $status,
                'customerFullname' => 'CUSTOMER ' . $status, 'customerTel' => '0000000000',
                'branchID' => $status <= 6 ? 1 : 2, 'branch_type_id' => $status <= 6 ? 1 : 2,
                'UserID' => $status <= 6 ? 9002 : 9003, 'action_status' => $status,
            ]);
        }
        $users = new ShadowUserStore($this->db);
        $users->create('order-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null);
        $users->create('order-branch@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1);
        $users->create('order-branch-read@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 3, 1);
    }

    public function testAllStatusListingsLegacyRoutesSearchAndBranchScope(): void
    {
        $admin = $this->session(1, 1, null);
        foreach ([
            '/ordersListing' => 1, '/sendorderListing' => 1, '/TrackingListing' => 2,
            '/TrackingcloseListing' => 3, '/TrackingreturnListing' => 4,
            '/TrackingcompleteListing' => 5, '/TrackingCompletedListing' => 7,
            '/orders?status=6' => 6, '/orders?status=8' => 8,
        ] as $path => $status) {
            $response = $this->withSession($admin)->get($path);
            $response->assertStatus(200);
            $response->assertSee('WP00C-TRACK-00' . $status);
        }

        $branch = $this->withSession($this->session(2, 2, 1))->get('/orders?status=7');
        $branch->assertStatus(200);
        $branch->assertDontSee('WP00C-TRACK-007');
        $search = $this->withSession($admin)->get('/orders?status=2&search=TRACK-002');
        $search->assertSee('WP00C-TRACK-002');
        $search->assertDontSee('WP00C-TRACK-001');
        $injection = $this->withSession($admin)->get('/orders?status=2&search=%25%27%20OR%201%3D1--');
        $injection->assertDontSee('WP00C-TRACK-002');
    }

    public function testCompletedListingRedirectsAnonymousRequestsLikeCi3(): void
    {
        $get = $this->withSession([])->get('/TrackingCompletedListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/TrackingCompletedListing', [
            'searchText' => 'WP00C-TRACK-007',
            'sdate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');
    }

    public function testCompletedListingInvalidLegacyDateReturnsNoRowsLikeCi3(): void
    {
        $response = $this->withSession($this->session(1, 1, null))->post('/TrackingCompletedListing', [
            'searchText' => '',
            'sdate' => 'not-a-date',
        ]);

        $response->assertStatus(200);
        $response->assertDontSee('WP00C-TRACK-007');
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="sdate")(?=[^>]*value="not-a-date")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingTreatsLegacyZeroDateAsEmpty(): void
    {
        $response = $this->withSession($this->session(1, 1, null))->post('/TrackingCompletedListing', [
            'searchText' => '',
            'sdate' => '0',
        ]);

        $response->assertStatus(200);
        $response->assertSee('WP00C-TRACK-007');
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="sdate")(?=[^>]*value="")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingPreservesLegacySearchWhitespace(): void
    {
        $response = $this->withSession($this->session(1, 1, null))->post('/TrackingCompletedListing', [
            'searchText' => ' WP00C-TRACK-007 ',
            'sdate' => '',
        ]);

        $response->assertStatus(200);
        self::assertSame(1, preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', (string) $response->getBody(), $tableBody));
        self::assertStringNotContainsString('WP00C-TRACK-007', $tableBody[1]);
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="searchText")(?=[^>]*value=" WP00C-TRACK-007 ")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingTreatsLegacyZeroSearchAsEmptyFilter(): void
    {
        $this->db->table('request_order')->where('request_id', 91007)->update([
            'trackID' => 'PARITYTRACKSEVEN',
        ]);
        $response = $this->withSession($this->session(1, 1, null))->post('/TrackingCompletedListing', [
            'searchText' => '0',
            'sdate' => '',
        ]);

        $response->assertStatus(200);
        $response->assertSee('PARITYTRACKSEVEN');
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="searchText")(?=[^>]*value="0")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingDoesNotClearLongLegacySearch(): void
    {
        $search = str_repeat('X', 129);
        $response = $this->withSession($this->session(1, 1, null))->post('/TrackingCompletedListing', [
            'searchText' => $search,
            'sdate' => '',
        ]);

        $response->assertStatus(200);
        self::assertSame(1, preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', (string) $response->getBody(), $tableBody));
        self::assertStringNotContainsString('WP00C-TRACK-007', $tableBody[1]);
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="searchText")(?=[^>]*value="' . $search . '")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingIgnoresLegacyGetFilterAndPageParameters(): void
    {
        $response = $this->withSession($this->session(1, 1, null))->get(
            '/TrackingCompletedListing?search=PARITY-NO-MATCH&sdate=01%2F01%2F2000&page=2',
        );

        $response->assertStatus(200);
        $response->assertSee('WP00C-TRACK-007');
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="searchText")(?=[^>]*value="")[^>]*>/s',
            (string) $response->getBody(),
        );
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="sdate")(?=[^>]*value="")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testCompletedListingOrdersRowsByRequestDateLikeCi3(): void
    {
        $this->db->table('request_order')->insertBatch([
            [
                'request_id' => 90007, 'requestDate' => '2030-01-01 00:00:00',
                'trackID' => 'PARITY-NEWEST-DATE', 'orderID' => 'ODN', 'orderIDShow' => 'DATE/NEW',
                'customerFullname' => 'NEWEST DATE', 'customerTel' => '0000000000',
                'branchID' => 2, 'branch_type_id' => 2, 'UserID' => 9003, 'action_status' => 7,
            ],
            [
                'request_id' => 99007, 'requestDate' => '2020-01-01 00:00:00',
                'trackID' => 'PARITY-OLDEST-DATE', 'orderID' => 'ODO', 'orderIDShow' => 'DATE/OLD',
                'customerFullname' => 'OLDEST DATE', 'customerTel' => '0000000000',
                'branchID' => 2, 'branch_type_id' => 2, 'UserID' => 9003, 'action_status' => 7,
            ],
        ]);

        $body = (string) $this->withSession($this->session(1, 1, null))
            ->get('/TrackingCompletedListing')->getBody();

        $newest = strpos($body, 'PARITY-NEWEST-DATE');
        $existing = strpos($body, 'WP00C-TRACK-007');
        $oldest = strpos($body, 'PARITY-OLDEST-DATE');
        self::assertIsInt($newest);
        self::assertIsInt($existing);
        self::assertIsInt($oldest);
        self::assertLessThan($existing, $newest);
        self::assertLessThan($oldest, $existing);
    }

    public function testCompletedListingLegacyPathUsesRawCi3Offset(): void
    {
        $this->db->table('request_order')->insertBatch([
            [
                'request_id' => 90007, 'requestDate' => '2030-01-01 00:00:00',
                'trackID' => 'PARITY-OFFSET-FIRST', 'orderID' => 'OOF', 'orderIDShow' => 'OFFSET/FIRST',
                'customerFullname' => 'OFFSET FIRST', 'customerTel' => '0000000000',
                'branchID' => 2, 'branch_type_id' => 2, 'UserID' => 9003, 'action_status' => 7,
            ],
            [
                'request_id' => 99007, 'requestDate' => '2020-01-01 00:00:00',
                'trackID' => 'PARITY-OFFSET-LAST', 'orderID' => 'OOL', 'orderIDShow' => 'OFFSET/LAST',
                'customerFullname' => 'OFFSET LAST', 'customerTel' => '0000000000',
                'branchID' => 2, 'branch_type_id' => 2, 'UserID' => 9003, 'action_status' => 7,
            ],
        ]);

        $response = $this->withSession($this->session(1, 1, null))->get('/TrackingCompletedListing/1');

        $response->assertStatus(200);
        self::assertStringContainsString('<title>Tracking :  Listing</title>', (string) $response->getBody());
        self::assertSame(1, preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', (string) $response->getBody(), $tableBody));
        self::assertStringNotContainsString('PARITY-OFFSET-FIRST', $tableBody[1]);
        self::assertStringContainsString('WP00C-TRACK-007', $tableBody[1]);
        self::assertStringContainsString('PARITY-OFFSET-LAST', $tableBody[1]);
    }

    public function testCompletedListingPaginationRendersCi3OffsetLinks(): void
    {
        for ($index = 1; $index <= 50; $index++) {
            $this->db->table('request_order')->insert([
                'request_id' => 92000 + $index,
                'requestDate' => sprintf('2025-01-01 00:%02d:00', $index - 1),
                'trackID' => sprintf('PARITY-PAGE-%03d', $index),
                'orderID' => 'OP' . $index,
                'orderIDShow' => 'PAGE/' . $index,
                'customerFullname' => 'PAGINATION ' . $index,
                'customerTel' => '0000000000',
                'branchID' => 2,
                'branch_type_id' => 2,
                'UserID' => 9003,
                'action_status' => 7,
            ]);
        }
        $admin = $this->session(1, 1, null);

        $first = (string) $this->withSession($admin)->get('/TrackingCompletedListing')->getBody();
        self::assertStringContainsString('<li class="active"><a href="#">1</a></li>', $first);
        self::assertStringContainsString(
            '<li><a href="http://example.invalid/TrackingCompletedListing/50" data-ci-pagination-page="2">2</a></li>',
            $first,
        );
        self::assertStringContainsString(
            '<li class="arrow"><a href="http://example.invalid/TrackingCompletedListing/50" data-ci-pagination-page="2" rel="next">Next</a></li>',
            $first,
        );
        self::assertStringNotContainsString('>Previous</a>', $first);

        $second = (string) $this->withSession($admin)->get('/TrackingCompletedListing/50')->getBody();
        self::assertStringContainsString('PARITY-PAGE-001', $second);
        self::assertStringNotContainsString('PARITY-PAGE-050', $second);
        self::assertStringContainsString(
            '<li class="arrow"><a href="http://example.invalid/TrackingCompletedListing/" data-ci-pagination-page="1" rel="prev">Previous</a></li>',
            $second,
        );
        self::assertStringContainsString(
            '<li><a href="http://example.invalid/TrackingCompletedListing/" data-ci-pagination-page="1" rel="start">1</a></li>',
            $second,
        );
        self::assertStringContainsString('<li class="active"><a href="#">2</a></li>', $second);
        self::assertStringNotContainsString('>Next</a>', $second);

        $beyondLast = (string) $this->withSession($admin)->get('/TrackingCompletedListing/999999')->getBody();
        self::assertStringNotContainsString('PARITY-PAGE-', $beyondLast);
        self::assertStringContainsString(
            '<li class="arrow"><a href="http://example.invalid/TrackingCompletedListing/" data-ci-pagination-page="1" rel="prev">Previous</a></li>',
            $beyondLast,
        );
        self::assertStringNotContainsString('/TrackingCompletedListing/999949', $beyondLast);
        self::assertStringContainsString('<li class="active"><a href="#">2</a></li>', $beyondLast);
    }

    public function testCi3OrderEditAndPrintAliasesKeepSourceFormActions(): void
    {
        $session = $this->session(1, 1, null);
        $edit = $this->withSession($session)->get('/editOrdersOld/91001');
        $edit->assertStatus(200);
        self::assertStringContainsString('action="http://example.invalid/editOrders"', (string) $edit->getBody());
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*name="request_id")(?=[^>]*value="91001")[^>]*>/s',
            (string) $edit->getBody(),
        );

        $print = $this->withSession($session)->get('/OrderPrint/91001');
        $print->assertStatus(200);
        self::assertStringContainsString('WP00C-TRACK-001', (string) $print->getBody());
    }

    public function testOrderListingSearchMatchesParityFieldsAndDropsExtras(): void
    {
        $admin = $this->session(1, 1, null);
        $this->db->table('request_order')->where('request_id', 91002)->update([
            'detailSKUName' => 'ZZSKU002', 'orderID' => 'ZZORD002',
            'orderIDShow' => 'ZZSHOW002', 'customerTel' => '027619999',
        ]);

        // AC-1: detailSKUName, branch_name (join) and status_name (join) each return the row.
        // AC-3: orderID returns the row.
        foreach (['ZZSKU002', 'BRANCH A', 'STATUS 2', 'ZZORD002'] as $term) {
            $hit = $this->withSession($admin)->get('/orders?status=2&search=' . rawurlencode($term));
            $hit->assertSee('WP00C-TRACK-002');
        }

        // AC-2: customerTel and orderIDShow are no longer searchable on this page.
        foreach (['027619999', 'ZZSHOW002'] as $term) {
            $miss = $this->withSession($admin)->get('/orders?status=2&search=' . rawurlencode($term));
            $miss->assertDontSee('WP00C-TRACK-002');
        }
    }

    public function testLifecycleQueuesExposeBrowserFormsForNormalTransitions(): void
    {
        $session = $this->session(2, 2, 1);

        // status 1 is a bulk provider form (T7): one form over the table, provider dropdown in the
        // footer, no per-row provider form left.
        $listing = $this->withSession($session)->get('/sendorderListing');
        $listing->assertStatus(200);
        $listingBody = $listing->getBody();
        self::assertStringContainsString('action="http://example.invalid/sendorderUpdate"', $listingBody);
        self::assertMatchesRegularExpression('/<input(?=[^>]*type="checkbox")(?=[^>]*name="select_list_id\[\]")[^>]*>/s', $listingBody);
        self::assertStringContainsString('id="selectall_tracking"', $listingBody);
        self::assertStringContainsString('name="provider_id"', $listingBody);
        self::assertStringContainsString(
            'บันทึกข้อมูล',
            html_entity_decode((string) $listing->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        self::assertStringNotContainsString('Send to provider', $listingBody);

        // TRANSPORTING (2) and STATUS REPAIR (3) are now blocked for branch users (wp03f t2), so their
        // browser forms are asserted through an admin session; the return queue stays branch-visible.
        $admin = $this->session(1, 1, null);
        foreach ([
            '/TrackingListing' => ['/sendorderUpdateStatus', 'status_id', 'Send', [3, 4], $admin],
            '/TrackingcloseListing' => ['/sendorderUpdateStatus', 'status_id', 'Send', [4], $admin],
            '/TrackingreturnListing' => ['/sendorder_deliver', 'status_id', 'Send', [5], $session],
        ] as $route => [$action, $field, $button, $optionValues, $actor]) {
            $response = $this->withSession($actor)->get($route);
            $response->assertStatus(200);
            $body = $response->getBody();
            self::assertStringContainsString('action="http://example.invalid' . $action . '"', $body);
            self::assertStringContainsString('name="select_list_id[]"', $body);
            self::assertStringContainsString('name="' . $field . '"', $body);
            self::assertStringContainsString($button, (string) $response->getBody());
            // Discriminating anchors: the JS also mentions select_list_id and selectall_tracking,
            // so match the actual row checkbox and the header input, not the bare names.
            self::assertMatchesRegularExpression('/<input(?=[^>]*type="checkbox")(?=[^>]*name="select_list_id\[\]")[^>]*>/s', $body);
            self::assertStringContainsString('id="selectall_tracking"', $body);
            foreach ($optionValues as $value) {
                self::assertStringContainsString('<option value="' . $value . '"', $body);
            }
            self::assertSame(count($optionValues) + 1, substr_count($body, '<option')); // CI3 Select Status placeholder
        }
    }

    public function testBranchUserIsForbiddenFromTransportingAndStatusRepairListings(): void
    {
        // AC-1: a branch user (BranchID not null) is denied the two hidden queues by route filter.
        $branch = $this->session(2, 2, 1);
        foreach (['/TrackingListing', '/TrackingcloseListing'] as $route) {
            $this->withSession($branch)->get($route)->assertStatus(403);
        }
    }

    public function testBranchlessAdminStillSeesTransportingAndStatusRepairListings(): void
    {
        // AC-2: an admin (BranchID null) still gets 200 with the queue rows on both routes.
        $admin = $this->session(1, 1, null);
        $transporting = $this->withSession($admin)->get('/TrackingListing');
        $transporting->assertStatus(200);
        $transporting->assertSee('WP00C-TRACK-002');
        $repair = $this->withSession($admin)->get('/TrackingcloseListing');
        $repair->assertStatus(200);
        $repair->assertSee('WP00C-TRACK-003');
    }

    public function testBranchUserBlockedOnQueueTwoThreeButNotOtherTransitions(): void
    {
        $branch = $this->session(2, 2, 1);

        // AC-3: a branch user cannot transition an order sourced from queue 2 (from=2) or 3 (from=3),
        // and no row in request_order or status_log is touched. Both disjuncts of the gate are covered.
        foreach ([['91002', '3'], ['91003', '4']] as [$id, $target]) {
            $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => [$id], 'status_id' => $target], $branch)
                ->assertStatus(403);
        }
        self::assertSame(2, (int) $this->db->table('request_order')->where('request_id', 91002)->get()->getRow('action_status'));
        self::assertSame(3, (int) $this->db->table('request_order')->where('request_id', 91003)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());

        // AC-4 (discriminator): the COMPLETE queue (from=5 -> 7) shares /sendorderUpdateStatus and must
        // still succeed for the same branch user, proving the gate keys on source status, not the endpoint.
        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91005'], 'status_id' => '7'], $branch)
            ->assertRedirectTo('/ReportTrackingListing');
        self::assertSame(7, (int) $this->db->table('request_order')->where('request_id', 91005)->get()->getRow('action_status'));

        // AC-5: the provider (from=1) and deliver (from=4) transitions are untouched for the same branch user.
        $this->postTransition('/sendorderUpdate', ['select_list_id' => ['91001'], 'provider_id' => '1'], $branch)
            ->assertRedirectTo('/sendorderListing');
        self::assertSame(2, (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'));
        $this->postTransition('/sendorder_deliver', ['select_list_id' => ['91004'], 'status_id' => '5'], $branch)
            ->assertRedirectTo('/TrackingreturnListing');
        self::assertSame(5, (int) $this->db->table('request_order')->where('request_id', 91004)->get()->getRow('action_status'));
    }

    public function testBranchUserCannotReachTransportingOrStatusRepairViaQueryString(): void
    {
        // AC-1: /orders?status= resolves the same $status as the named routes, so the branch user
        // must be denied 2 and 3 there too — the guessable query string cannot side-step the gate.
        $branch = $this->session(2, 2, 1);
        $this->withSession($branch)->get('/orders?status=2')->assertStatus(403);
        $this->withSession($branch)->get('/orders?status=3')->assertStatus(403);

        // AC-2: an admin (BranchID null) still reaches both queues through the query string.
        $admin = $this->session(1, 1, null);
        $this->withSession($admin)->get('/orders?status=2')->assertSee('WP00C-TRACK-002');
        $this->withSession($admin)->get('/orders?status=3')->assertSee('WP00C-TRACK-003');
    }

    public function testControllerBusinessForbiddenRemainsJsonWhenHtmlIsRequested(): void
    {
        $response = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession($this->session(2, 2, 1))
            ->get('/orders?status=2');

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->assertJSONExact(['error' => 'forbidden']);
        $response->assertDontSee('Access Denied');
    }

    public function testBranchUserStillReachesEveryOtherQueueThroughQueryString(): void
    {
        // AC-6 (discriminator): only queues 2 and 3 are gated; every other status stays 200 for the
        // branch user, proving the gate keys on the resolved status and does not over-block.
        $branch = $this->session(2, 2, 1);
        foreach ([1, 4, 5, 6, 7, 8] as $status) {
            $this->withSession($branch)->get('/orders?status=' . $status)->assertStatus(200);
        }
    }

    public function testBranchUserCannotSoftDeleteTransportingOrStatusRepairOrder(): void
    {
        // AC-3: soft-delete rewrites action_status to 8; a branch user must be forbidden (403) from
        // doing so to an order sourced from queue 2 or 3, and the row must stay untouched.
        $branch = $this->session(2, 2, 1);
        foreach ([91002 => 2, 91003 => 3] as $id => $status) {
            $this->postTransition('/orders/' . $id . '/delete', [], $branch)->assertStatus(403);
            self::assertSame($status, (int) $this->db->table('request_order')->where('request_id', $id)->get()->getRow('action_status'));
        }

        // AC-4 (discriminator): the same branch user still soft-deletes an order it may act on (status 1).
        $this->postTransition('/orders/91001/delete', [], $branch)->assertStatus(204);
        self::assertSame(8, (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'));
    }

    public function testBulkTransitionUpdatesEverySelectedOrder(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 92002, 'requestDate' => '2026-08-02 00:00:00',
            'trackID' => 'WP00C-BULK-002', 'orderID' => 'OB2', 'orderIDShow' => 'WPC/B2',
            'customerFullname' => 'BULK CUSTOMER', 'customerTel' => '0000000000',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 2,
        ]);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91002', '92002'], 'status_id' => '3'])
            ->assertRedirectTo('/ReportTrackingListing');

        self::assertSame(3, (int) $this->db->table('request_order')->where('request_id', 91002)->get()->getRow('action_status'));
        self::assertSame(3, (int) $this->db->table('request_order')->where('request_id', 92002)->get()->getRow('action_status'));
        self::assertSame(2, $this->db->table('status_log')->countAllResults());
    }

    public function testBulkSendToProviderUpdatesEverySelectedOrder(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 92001, 'requestDate' => '2026-08-01 00:00:00',
            'trackID' => 'WP00C-BULK-001', 'orderID' => 'OB1', 'orderIDShow' => 'WPC/B1',
            'customerFullname' => 'BULK PROVIDER CUSTOMER', 'customerTel' => '0000000000',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 1,
        ]);

        $this->postTransition('/sendorderUpdate', ['select_list_id' => ['91001', '92001'], 'provider_id' => '1'])
            ->assertRedirectTo('/sendorderListing');

        foreach ([91001, 92001] as $requestId) {
            $row = $this->db->table('request_order')->where('request_id', $requestId)->get()->getRowArray();
            self::assertSame(2, (int) $row['action_status']);
            self::assertSame(1, (int) $row['provider_id']);
        }
        self::assertSame(2, $this->db->table('status_log')->countAllResults());
    }

    public function testCompletedListingHasNoBulkControls(): void
    {
        // status 5 gained a bulk complete form in WP-04B T3; status 7 (COMPLETED) is terminal and
        // keeps no bulk endpoint. The status 5 bulk form is asserted by testCompleteListing... below.
        $session = $this->session(1, 1, null);
        foreach (['/TrackingCompletedListing'] as $route) {
            $response = $this->withSession($session)->get($route);
            $response->assertStatus(200);
            $body = $response->getBody();
            self::assertStringNotContainsString('name="select_list_id[]"', $body);
            self::assertStringNotContainsString('selectall_tracking', $body);
        }
    }

    public function testCompletedListingShowsCompletedDateColumnScopedToStatusSeven(): void
    {
        $this->db->table('request_order')->where('request_id', 91007)->update(['date_complete' => '2026-07-15 10:30:00']);
        $this->db->table('request_order')->insert([
            'request_id' => 91077, 'requestDate' => '2026-08-07 00:00:00',
            'trackID' => 'WP00C-TRACK-077', 'orderID' => 'O77', 'orderIDShow' => 'WPC/77',
            'customerFullname' => 'COMPLETED NULL DATE', 'customerTel' => '0000000000',
            'branchID' => 2, 'branch_type_id' => 2, 'UserID' => 9003, 'action_status' => 7,
        ]);
        $admin = $this->session(1, 1, null);

        $completed = $this->withSession($admin)->get('/TrackingCompletedListing');
        $completed->assertStatus(200);
        $completed->assertSee('Completed Date');
        $completed->assertSee('15/07/2026');
        $completed->assertSee('WP00C-TRACK-077'); // null date_complete row still renders (blank cell)
        self::assertStringNotContainsString('name="select_list_id[]"', $completed->getBody());

        // Column is scoped to status 7: status 5 listing must not expose it.
        $complete = $this->withSession($admin)->get('/TrackingcompleteListing');
        $complete->assertStatus(200);
        $complete->assertDontSee('Completed Date');
    }

    public function testCompleteListingRendersRatingDialogScopedToStatusFive(): void
    {
        $admin = $this->session(1, 1, null);

        $complete = $this->withSession($admin)->get('/TrackingcompleteListing');
        $complete->assertStatus(200);
        $body = $complete->getBody();
        self::assertStringContainsString('<div id="modal_rating" class="modal fade modal-rating"', $body);
        self::assertStringContainsString("url: base_url + 'rating/addRating'", $body);
        for ($question = 1; $question <= 8; $question++) {
            self::assertStringContainsString('name="rating_' . $question . '"', $body);
        }
        self::assertStringContainsString('name="rating_comment"', $body);

        $completed = $this->withSession($admin)->get('/TrackingCompletedListing');
        $completed->assertStatus(200);
        self::assertStringNotContainsString('id="modal_rating"', $completed->getBody());
    }

    public function testSubmittedRatingMovesOrderFromCompleteToCompletedListing(): void
    {
        $admin = $this->session(1, 1, null);
        $this->withSession($admin)->get('/TrackingcompleteListing')->assertSee('WP00C-TRACK-005');

        $payload = ['csrf_test_name' => service('security')->getHash(), 'request_id' => '91005', 'track_id' => 'WP00C-TRACK-005', 'rating_comment' => 'SYNTHETIC RATING COMMENT'];
        foreach ([5, 4, 3, 2, 1, 5, 4, 3] as $index => $score) {
            $payload['rating_' . ($index + 1)] = (string) $score;
        }
        $this->post('/rating', $payload)->assertStatus(201);
        self::assertSame(7, (int) $this->db->table('request_order')->where('request_id', 91005)->get()->getRow('action_status'));

        $this->withSession($admin)->get('/TrackingcompleteListing')->assertDontSee('WP00C-TRACK-005');
        $this->withSession($admin)->get('/TrackingCompletedListing')->assertSee('WP00C-TRACK-005');
    }

    public function testBatchStatusUpdateShowsLatestDescriptionInSingleQuery(): void
    {
        $this->db->table('tracking_status')->insertBatch([
            ['status_id' => 10, 'description_th' => 'อยู่ระหว่างซ่อม'],
            ['status_id' => 11, 'description_th' => 'ซ่อมเสร็จแล้ว'],
        ]);
        $this->db->table('uploadstaus')->insertBatch([
            ['tracking_id' => 'O2', 'Telephone' => '0000000000', 'tracking_status' => 10, 'cdate' => '2026-08-02 09:00:00'],
            ['tracking_id' => 'O2', 'Telephone' => '0000000000', 'tracking_status' => 11, 'cdate' => '2026-08-02 10:00:00'],
        ]);

        $uploadQueries = 0;
        Events::on('DBQuery', static function ($query) use (&$uploadQueries): void {
            if (stripos($query->getQuery(), 'uploadstaus') !== false) {
                $uploadQueries++;
            }
        });

        $response = $this->withSession($this->session(1, 1, null))->get('/TrackingListing');
        $response->assertStatus(200);
        $responseBody = html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (['TRANSPORTING', 'WPC/2', 'STATUS 2', '02/08/2026', 'ซ่อมเสร็จแล้ว'] as $text) {
            self::assertStringContainsString($text, $responseBody);
        }
        self::assertStringNotContainsString('อยู่ระหว่างซ่อม', $responseBody);
        self::assertSame(1, $uploadQueries, 'listing must batch Status Update into one uploadstaus query per page');
    }

    public function testEveryListingShowsCi3DateAndDetailFilters(): void
    {
        $admin = $this->session(1, 1, null);
        foreach (range(1, 8) as $status) {
            $response = $this->withSession($admin)->get('/orders?status=' . $status);
            $response->assertStatus(200);
            $body = $response->getBody();
            if ($status === 1) {
                self::assertMatchesRegularExpression('/from\s+Date\s*:/i', $body);
                self::assertStringContainsString('To Date :', $body);
                self::assertStringContainsString('Detail :', $body);
                self::assertStringContainsString('name="sdate"', $body);
                self::assertStringContainsString('name="searchText"', $body);
            } elseif (in_array($status, [2, 3, 4], true)) {
                self::assertStringContainsString('Date :', $body);
                self::assertStringContainsString('Detail :', $body);
                self::assertStringContainsString('name="sdate"', $body);
                self::assertStringContainsString('name="searchText"', $body);
                self::assertStringNotContainsString('To Date :', $body);
            } elseif (in_array($status, [5, 7], true)) {
                self::assertStringContainsString('Detail:', $body);
                self::assertStringContainsString('Date:', $body);
                self::assertStringContainsString('name="searchText"', $body);
                self::assertStringContainsString('name="sdate"', $body);
            } else {
                // Status 6/8 have no CI3 queue template and retain the independent CI4 fallback.
                self::assertStringContainsString('name="search"', $body);
            }
        }
    }

    public function testListingHeadingsAndHeadersMatchCi3PerQueue(): void
    {
        $admin = $this->session(1, 1, null);
        // Header casing is inconsistent in CI3 and the visual comparison reads it verbatim.
        /** @var array<int, array{0: string, 1: string, 2: string, 3: list<string>}> $expected */
        $expected = [
            1 => ['NEW REQUEST REPAIR', 'Add, Edit, Delete', 'Request order List', ['TrackID', 'OrderID', 'Action status']],
            2 => ['TRANSPORTING', '', 'TRANSPORTING List', ['trackID', 'orderID', 'Action status', 'status Update']],
            3 => ['STATUS REPAIR', '', 'STATUS REPAIR List', ['trackID', 'orderID', 'Action Status', 'Status Update']],
            4 => ['DELIVER TO CUSTOMER', '', 'DELIVER TO CUSTOMER List', ['trackID', 'orderID', 'Action Status', 'Status Update']],
            5 => ['COMPLETE FEEDBACK', '', '', ['TrackID', 'OrderID', 'Action Status', 'Status Update']],
            7 => ['COMPLETED JOB', '', '', ['Track Id', 'Order Id', 'Full Name', 'Request Date', 'Completed Date']],
        ];

        foreach ($expected as $status => [$title, $subtitle, $listTitle, $headers]) {
            $body = (string) $this->withSession($admin)->get('/orders?status=' . $status)->getBody();
            // The subtitle follows the title inside <h1> on its own line, so match the opening
            // tag boundary rather than a closing one.
            self::assertStringContainsString($title, $body, 'title, status ' . $status);
            if ($subtitle !== '') {
                self::assertStringContainsString($subtitle, $body, 'subtitle, status ' . $status);
            }
            self::assertSame(
                $listTitle !== '',
                str_contains($body, '<h3 class="box-title">' . $listTitle . '</h3>'),
                'list title, status ' . $status,
            );
            foreach ($headers as $header) {
                self::assertMatchesRegularExpression(
                    '/<th(?:\s[^>]*)?>\s*' . preg_quote($header, '/') . '\s*<\/th>/s',
                    html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $header . ', status ' . $status,
                );
            }
        }
    }

    public function testQueueOneCarriesAddNewAndRowActionsWhileOtherQueuesDoNot(): void
    {
        $admin = $this->session(1, 1, null);

        $queueOne = (string) $this->withSession($admin)->get('/orders?status=1')->getBody();
        self::assertStringContainsString('href="http://example.invalid/Orders"', $queueOne);
        self::assertStringContainsString('Add New</a>', $queueOne);
        self::assertStringContainsString('title="Edit"', $queueOne);
        self::assertStringContainsString('title="Delete"', $queueOne);
        self::assertStringContainsString('title="Print"', $queueOne);
        self::assertStringNotContainsString('id="order-delete-csrf"', $queueOne);
        self::assertStringContainsString('class="btn btn-sm btn-danger deleteOrders"', $queueOne);

        // CI3 leaves queues 2-4 with checkboxes only: no Add New and no per-row controls.
        foreach ([2, 3, 4] as $status) {
            $body = (string) $this->withSession($admin)->get('/orders?status=' . $status)->getBody();
            self::assertStringNotContainsString('Add New</a>', $body, 'status ' . $status);
            self::assertStringNotContainsString('title="Edit"', $body, 'status ' . $status);
            self::assertStringNotContainsString('title="Print"', $body, 'status ' . $status);
            self::assertStringContainsString('Select ALL tracking', $body, 'status ' . $status);
        }
    }

    public function testTrackingCompleteListingAnonymousRequestsRedirectLikeCi3(): void
    {
        $get = $this->withSession([])->get('/TrackingcompleteListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/TrackingcompleteListing', [
            'searchText' => '', 'sdate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');
    }

    public function testTrackingCompleteListingLegacyAliasUsesPostFiltersAndSourceOrdering(): void
    {
        $admin = $this->session(1, 1, null);
        $this->db->table('request_order')->insert([
            'request_id' => 91995,
            'requestDate' => '2025-01-01 00:00:00',
            'trackID' => 'OLDER-COMPLETE-ORDER',
            'orderID' => 'OLDER-COMPLETE',
            'orderIDShow' => 'OLDER/COMPLETE',
            'customerFullname' => 'OLDER COMPLETE CUSTOMER',
            'customerTel' => '0000000000',
            'branchID' => 1,
            'branch_type_id' => 1,
            'UserID' => 9002,
            'action_status' => 5,
        ]);

        try {
            foreach (['   ', ' WP00C-TRACK-005 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/TrackingcompleteListing', [
                    'searchText' => $searchText, 'sdate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-005</td>', $body);
            }

            $zeroSearch = $this->withSession($admin)->post('/TrackingcompleteListing', [
                'searchText' => '0', 'sdate' => '',
            ]);
            $zeroSearch->assertStatus(200);
            $zeroSearch->assertSee('WP00C-TRACK-005');

            $malformedDate = $this->withSession($admin)->post('/TrackingcompleteListing', [
                'searchText' => '', 'sdate' => 'not-a-date',
            ]);
            $malformedDate->assertStatus(200);
            $malformedDate->assertDontSee('WP00C-TRACK-005');
            self::assertStringContainsString(
                'name="sdate" value="not-a-date"',
                (string) $malformedDate->getBody(),
            );

            $zeroDate = $this->withSession($admin)->post('/TrackingcompleteListing', [
                'searchText' => '', 'sdate' => '0',
            ]);
            $zeroDate->assertStatus(200);
            $zeroDate->assertSee('WP00C-TRACK-005');
            self::assertStringContainsString('name="sdate" value=""', (string) $zeroDate->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        foreach ([
            '/TrackingcompleteListing?search=PARITY-NO-MATCH',
            '/TrackingcompleteListing?sdate=06%2F08%2F2026',
            '/TrackingcompleteListing?page=2',
        ] as $uri) {
            $get = $this->withSession($admin)->get($uri);
            $get->assertStatus(200);
            $get->assertSee('WP00C-TRACK-005');
            $body = (string) $get->getBody();
            self::assertStringContainsString('name="searchText" value=""', $body);
            self::assertStringContainsString('name="sdate" value=""', $body);
        }

        $offset = $this->withSession($admin)->get('/TrackingcompleteListing/1');
        $offset->assertStatus(200);
        self::assertStringContainsString('<title>Tracking :  Listing</title>', (string) $offset->getBody());

        $listing = (string) $this->withSession($admin)->get('/TrackingcompleteListing')->getBody();
        self::assertLessThan(
            strpos($listing, 'OLDER-COMPLETE-ORDER'),
            strpos($listing, 'WP00C-TRACK-005'),
        );
    }

    public function testDeliverWithoutEncryptionStillCompletesWithoutPlaintextSmsIntent(): void
    {
        Services::resetSingle('encrypter');

        $response = $this->postTransition('/sendorder_deliver', [
            'status_id' => '5', 'select_list_id' => ['91004'],
        ]);

        $response->assertStatus(303);
        $response->assertRedirectTo('/TrackingreturnListing');
        self::assertSame('order updated successfully', service('session')->getFlashdata('success'));
        $row = $this->db->table('request_order')->where('request_id', 91004)->get()->getRowArray();
        self::assertSame(5, (int) $row['action_status']);
        self::assertNotEmpty($row['date_deliver']);
        self::assertNotEmpty($row['date_update_status']);
        self::assertSame(1, $this->db->table('status_log')->where('action_id', 5)->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->countAllResults());
    }

    public function testDeliverLegacyValidationRedirectsWithFeedback(): void
    {
        $noStatus = $this->postTransition('/sendorder_deliver', [
            'status_id' => '0', 'select_list_id' => ['91004'],
        ]);
        $noStatus->assertStatus(303);
        $noStatus->assertRedirectTo('/TrackingreturnListing');
        self::assertSame('Branch creation failed', service('session')->getFlashdata('error'));
        self::assertSame(
            4,
            (int) $this->db->table('request_order')->where('request_id', 91004)->get()->getRow('action_status'),
        );

        $noOrders = $this->postTransition('/sendorder_deliver', ['status_id' => '5']);
        $noOrders->assertStatus(303);
        $noOrders->assertRedirectTo('/TrackingreturnListing');
        self::assertSame('order updated failed', service('session')->getFlashdata('error'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testTrackingReturnListingAnonymousRequestsRedirectLikeCi3(): void
    {
        $get = $this->withSession([])->get('/TrackingreturnListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/TrackingreturnListing', [
            'searchText' => '', 'sdate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');

        $deliver = $this->withSession([])->post('/sendorder_deliver', [
            'status_id' => '5', 'select_list_id' => ['91004'],
        ]);
        $deliver->assertStatus(303);
        $deliver->assertRedirectTo('/login');
    }

    public function testTrackingReturnListingLegacyAliasUsesPostFiltersAndSourceOrdering(): void
    {
        $admin = $this->session(1, 1, null);
        $this->db->table('request_order')->insert([
            'request_id' => 91996,
            'requestDate' => '2025-01-01 00:00:00',
            'trackID' => 'OLDER-RETURN-ORDER',
            'orderID' => 'OLDER-RETURN',
            'orderIDShow' => 'OLDER/RETURN',
            'customerFullname' => 'OLDER RETURN CUSTOMER',
            'customerTel' => '0000000000',
            'branchID' => 1,
            'branch_type_id' => 1,
            'UserID' => 9002,
            'action_status' => 4,
        ]);

        try {
            foreach (['   ', ' WP00C-TRACK-004 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/TrackingreturnListing', [
                    'searchText' => $searchText, 'sdate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-004</td>', $body);
            }

            $zeroSearch = $this->withSession($admin)->post('/TrackingreturnListing', [
                'searchText' => '0', 'sdate' => '',
            ]);
            $zeroSearch->assertStatus(200);
            $zeroSearch->assertSee('WP00C-TRACK-004');

            $malformedDate = $this->withSession($admin)->post('/TrackingreturnListing', [
                'searchText' => '', 'sdate' => 'not-a-date',
            ]);
            $malformedDate->assertStatus(200);
            $malformedDate->assertDontSee('WP00C-TRACK-004');
            self::assertStringContainsString(
                'name="sdate" value="not-a-date"',
                (string) $malformedDate->getBody(),
            );

            $zeroDate = $this->withSession($admin)->post('/TrackingreturnListing', [
                'searchText' => '', 'sdate' => '0',
            ]);
            $zeroDate->assertStatus(200);
            $zeroDate->assertSee('WP00C-TRACK-004');
            self::assertStringContainsString('name="sdate" value=""', (string) $zeroDate->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        foreach ([
            '/TrackingreturnListing?search=PARITY-NO-MATCH',
            '/TrackingreturnListing?sdate=05%2F08%2F2026',
            '/TrackingreturnListing?page=2',
        ] as $uri) {
            $get = $this->withSession($admin)->get($uri);
            $get->assertStatus(200);
            $get->assertSee('WP00C-TRACK-004');
            $body = (string) $get->getBody();
            self::assertStringContainsString('name="searchText" value=""', $body);
            self::assertStringContainsString('name="sdate" value=""', $body);
        }

        $offset = $this->withSession($admin)->get('/TrackingreturnListing/1');
        $offset->assertStatus(200);
        self::assertStringContainsString('<title>Tracking :  Listing</title>', (string) $offset->getBody());

        $listing = (string) $this->withSession($admin)->get('/TrackingreturnListing')->getBody();
        self::assertLessThan(
            strpos($listing, 'OLDER-RETURN-ORDER'),
            strpos($listing, 'WP00C-TRACK-004'),
        );

        $rows = [];
        for ($index = 1; $index <= 49; $index++) {
            $rows[] = [
                'request_id' => 93000 + $index,
                'requestDate' => '2024-01-01 00:00:00',
                'trackID' => sprintf('RETURN-PAGE-%02d', $index),
                'orderID' => 'RETURN-' . $index,
                'orderIDShow' => 'RETURN/' . $index,
                'customerFullname' => 'RETURN PAGINATION CUSTOMER',
                'customerTel' => '0000000000',
                'branchID' => 1,
                'branch_type_id' => 1,
                'UserID' => 9002,
                'action_status' => 4,
            ];
        }
        $this->db->table('request_order')->insertBatch($rows);
        $paginated = (string) $this->withSession($admin)->get('/TrackingreturnListing')->getBody();
        self::assertStringContainsString(
            'href="http://example.invalid/Trackingreturn/50" data-ci-pagination-page="2" rel="next">Next</a>',
            $paginated,
        );
    }

    public function testTrackingCloseListingAnonymousRequestsRedirectLikeCi3(): void
    {
        $get = $this->withSession([])->get('/TrackingcloseListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/TrackingcloseListing', [
            'searchText' => '', 'sdate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');
    }

    public function testTrackingCloseListingLegacyAliasUsesPostFiltersAndSourceOrdering(): void
    {
        $admin = $this->session(1, 1, null);
        $this->db->table('request_order')->insert([
            'request_id' => 91997,
            'requestDate' => '2025-01-01 00:00:00',
            'trackID' => 'OLDER-REPAIR-ORDER',
            'orderID' => 'OLDER-REPAIR',
            'orderIDShow' => 'OLDER/REPAIR',
            'customerFullname' => 'OLDER REPAIR CUSTOMER',
            'customerTel' => '0000000000',
            'branchID' => 1,
            'branch_type_id' => 1,
            'UserID' => 9002,
            'action_status' => 3,
        ]);

        try {
            foreach (['   ', ' WP00C-TRACK-003 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/TrackingcloseListing', [
                    'searchText' => $searchText, 'sdate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-003</td>', $body);
            }

            $zeroSearch = $this->withSession($admin)->post('/TrackingcloseListing', [
                'searchText' => '0', 'sdate' => '',
            ]);
            $zeroSearch->assertStatus(200);
            $zeroSearch->assertSee('WP00C-TRACK-003');

            $malformedDate = $this->withSession($admin)->post('/TrackingcloseListing', [
                'searchText' => '', 'sdate' => 'not-a-date',
            ]);
            $malformedDate->assertStatus(200);
            $malformedDate->assertDontSee('WP00C-TRACK-003');
            self::assertStringContainsString(
                'name="sdate" value="not-a-date"',
                (string) $malformedDate->getBody(),
            );

            $zeroDate = $this->withSession($admin)->post('/TrackingcloseListing', [
                'searchText' => '', 'sdate' => '0',
            ]);
            $zeroDate->assertStatus(200);
            $zeroDate->assertSee('WP00C-TRACK-003');
            self::assertStringContainsString('name="sdate" value=""', (string) $zeroDate->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        foreach ([
            '/TrackingcloseListing?search=PARITY-NO-MATCH',
            '/TrackingcloseListing?sdate=04%2F08%2F2026',
            '/TrackingcloseListing?page=2',
        ] as $uri) {
            $get = $this->withSession($admin)->get($uri);
            $get->assertStatus(200);
            $get->assertSee('WP00C-TRACK-003');
            $body = (string) $get->getBody();
            self::assertStringContainsString('name="searchText" value=""', $body);
            self::assertStringContainsString('name="sdate" value=""', $body);
        }

        $offset = $this->withSession($admin)->get('/TrackingcloseListing/1');
        $offset->assertStatus(200);
        self::assertStringContainsString('<title>Tracking :  Listing</title>', (string) $offset->getBody());

        $listing = (string) $this->withSession($admin)->get('/TrackingcloseListing')->getBody();
        self::assertLessThan(
            strpos($listing, 'OLDER-REPAIR-ORDER'),
            strpos($listing, 'WP00C-TRACK-003'),
        );
    }

    public function testTrackingStatusUpdateLegacyValidationRedirectsWithFeedback(): void
    {
        $noStatus = $this->postTransition('/sendorderUpdateStatus', [
            'status_id' => '0', 'select_list_id' => ['91002'],
        ]);
        $noStatus->assertStatus(303);
        $noStatus->assertRedirectTo('/ReportTrackingListing');
        self::assertSame('Branch creation failed', service('session')->getFlashdata('error'));
        self::assertSame(
            2,
            (int) $this->db->table('request_order')->where('request_id', 91002)->get()->getRow('action_status'),
        );

        // CI3 redirects after this malformed submission but incorrectly reports success.
        // Keep the redirect contract while retaining intended validation feedback.
        $noOrders = $this->postTransition('/sendorderUpdateStatus', ['status_id' => '3']);
        $noOrders->assertStatus(303);
        $noOrders->assertRedirectTo('/ReportTrackingListing');
        self::assertSame('order updated failed', service('session')->getFlashdata('error'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testTrackingListingAnonymousRequestsRedirectLikeCi3(): void
    {
        $get = $this->withSession([])->get('/TrackingListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/TrackingListing', [
            'searchText' => '', 'sdate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');

        $update = $this->withSession([])->post('/sendorderUpdateStatus', [
            'status_id' => '3', 'select_list_id' => ['91002'],
        ]);
        $update->assertStatus(303);
        $update->assertRedirectTo('/login');
    }

    public function testTrackingListingLegacyAliasUsesPostFiltersAndPreservesRawValues(): void
    {
        $admin = $this->session(1, 1, null);

        try {
            foreach (['   ', ' WP00C-TRACK-002 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/TrackingListing', [
                    'searchText' => $searchText, 'sdate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-002</td>', $body);
            }

            $zeroSearch = $this->withSession($admin)->post('/TrackingListing', [
                'searchText' => '0', 'sdate' => '',
            ]);
            $zeroSearch->assertStatus(200);
            $zeroSearch->assertSee('WP00C-TRACK-002');
            self::assertStringContainsString('name="searchText" value="0"', (string) $zeroSearch->getBody());

            $malformedDate = $this->withSession($admin)->post('/TrackingListing', [
                'searchText' => '', 'sdate' => 'not-a-date',
            ]);
            $malformedDate->assertStatus(200);
            $malformedDate->assertDontSee('WP00C-TRACK-002');
            self::assertStringContainsString(
                'name="sdate" value="not-a-date"',
                (string) $malformedDate->getBody(),
            );

            $zeroDate = $this->withSession($admin)->post('/TrackingListing', [
                'searchText' => '', 'sdate' => '0',
            ]);
            $zeroDate->assertStatus(200);
            $zeroDate->assertSee('WP00C-TRACK-002');
            self::assertStringContainsString('name="sdate" value=""', (string) $zeroDate->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        foreach ([
            '/TrackingListing?search=PARITY-NO-MATCH',
            '/TrackingListing?sdate=03%2F08%2F2026',
            '/TrackingListing?page=2',
        ] as $uri) {
            $get = $this->withSession($admin)->get($uri);
            $get->assertStatus(200);
            $get->assertSee('WP00C-TRACK-002');
            $body = (string) $get->getBody();
            self::assertStringContainsString('name="searchText" value=""', $body);
            self::assertStringContainsString('name="sdate" value=""', $body);
        }

        $offset = $this->withSession($admin)->get('/TrackingListing/1');
        $offset->assertStatus(200);
        self::assertStringContainsString('<title>Tracking :  Listing</title>', (string) $offset->getBody());
    }

    public function testSendToProviderSupportsLegacyOtherLogisticsOption(): void
    {
        $response = $this->postTransition('/sendorderUpdate', [
            'provider_id' => '9999',
            'select_list_id' => ['91001'],
            'logistics_etc_detail' => '  CUSTOM LOGISTICS DETAIL  ',
        ]);

        $response->assertStatus(303);
        $response->assertRedirectTo('/sendorderListing');
        self::assertSame('order updated successfully', service('session')->getFlashdata('success'));
        $row = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame(2, (int) $row['action_status']);
        self::assertSame(0, (int) $row['provider_id']);
        self::assertSame('  CUSTOM LOGISTICS DETAIL  ', $row['logistics_etc_detail']);
        self::assertNotEmpty($row['date_create']);
        self::assertNotEmpty($row['date_update_status']);
        self::assertSame(1, $this->db->table('status_log')->where('action_id', 2)->countAllResults());
    }

    public function testSendToProviderDoesNotRequireSmsEncryptionAndShowsLegacySuccess(): void
    {
        Services::resetSingle('encrypter');

        $response = $this->postTransition('/sendorderUpdate', [
            'provider_id' => '1', 'select_list_id' => ['91001'],
        ]);

        $response->assertStatus(303);
        $response->assertRedirectTo('/sendorderListing');
        self::assertSame('order updated successfully', service('session')->getFlashdata('success'));
        $row = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame(2, (int) $row['action_status']);
        self::assertSame(1, (int) $row['provider_id']);
        self::assertNotEmpty($row['date_create']);
        self::assertNotEmpty($row['date_update_status']);
        self::assertSame(1, $this->db->table('status_log')->where('action_id', 2)->countAllResults());
    }

    public function testSendToProviderLegacyValidationRedirectsWithExactFeedback(): void
    {
        $noProvider = $this->postTransition('/sendorderUpdate', [
            'provider_id' => '0', 'select_list_id' => ['91001'],
        ]);
        $noProvider->assertStatus(303);
        $noProvider->assertRedirectTo('/sendorderListing');
        self::assertSame('Logistics Detil failed', service('session')->getFlashdata('error'));
        self::assertSame(
            1,
            (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'),
        );

        $noOrders = $this->postTransition('/sendorderUpdate', ['provider_id' => '1']);
        $noOrders->assertStatus(303);
        $noOrders->assertRedirectTo('/sendorderListing');
        self::assertSame('order updated failed', service('session')->getFlashdata('error'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testSendOrderListingAnonymousRequestsRedirectLikeCi3(): void
    {
        $get = $this->withSession([])->get('/sendorderListing');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/sendorderListing', [
            'searchText' => '', 'sdate' => '', 'edate' => '',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');

        $update = $this->withSession([])->post('/sendorderUpdate', [
            'provider_id' => '1', 'select_list_id' => ['91001'],
        ]);
        $update->assertStatus(303);
        $update->assertRedirectTo('/login');
    }

    public function testSendOrderListingAcceptsBuddhistEndYearProducedByLegacyDatepicker(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 91998,
            'requestDate' => '2027-01-01 00:00:00',
            'trackID' => 'FUTURE-ORDER',
            'orderID' => 'FUTURE',
            'orderIDShow' => 'FUTURE/1',
            'customerFullname' => 'FUTURE CUSTOMER',
            'customerTel' => '0000000000',
            'branchID' => 1,
            'branch_type_id' => 1,
            'UserID' => 9002,
            'action_status' => 1,
        ]);

        try {
            $response = $this->withSession($this->session(1, 1, null))->post('/sendorderListing', [
                'searchText' => '', 'sdate' => '01/08/2026', 'edate' => '01/08/2569',
            ]);
            $response->assertStatus(200);
            $response->assertSee('WP00C-TRACK-001');
            $response->assertDontSee('FUTURE-ORDER');
            self::assertStringContainsString('name="edate" value="01/08/2569"', (string) $response->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testSendOrderListingLegacyAliasPreservesPostFiltersAndOffsetTemplate(): void
    {
        $admin = $this->session(1, 1, null);

        try {
            foreach (['   ', ' WP00C-TRACK-001 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/sendorderListing', [
                    'searchText' => $searchText, 'sdate' => '', 'edate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-001</td>', $body);
            }
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        foreach ([
            '/sendorderListing?search=PARITY-NO-MATCH&sdate=not-a-date&edate=also-bad',
            '/sendorderListing?page=2',
        ] as $uri) {
            $get = $this->withSession($admin)->get($uri);
            $get->assertStatus(200);
            $get->assertSee('WP00C-TRACK-001');
            $getBody = (string) $get->getBody();
            self::assertStringContainsString('name="searchText" value=""', $getBody);
            self::assertStringContainsString('name="sdate" value=""', $getBody);
            self::assertStringContainsString('name="edate" value=""', $getBody);
        }

        $offset = $this->withSession($admin)->get('/sendorderListing/1');
        $offset->assertStatus(200);
        $offsetBody = (string) $offset->getBody();
        self::assertStringContainsString('<title>Tracking :  Listing</title>', $offsetBody);
        self::assertStringContainsString('<h3 class="box-title">LOGISTICS List</h3>', $offsetBody);
        self::assertStringContainsString('action="http://example.invalid/sendorderUpdate"', $offsetBody);
        self::assertStringNotContainsString('<h3 class="box-title">Request order List</h3>', $offsetBody);
    }

    public function testOrdersListingLegacyAliasPreservesPostFiltersAndIgnoresGetFilters(): void
    {
        $admin = $this->session(1, 1, null);

        try {
            foreach (['   ', ' WP00C-TRACK-001 ', str_repeat('X', 129)] as $searchText) {
                $response = $this->withSession($admin)->post('/ordersListing', [
                    'searchText' => $searchText, 'sdate' => '', 'edate' => '',
                ]);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('name="searchText" value="' . $searchText . '"', $body);
                self::assertStringNotContainsString('WP00C-TRACK-001</td>', $body);
            }

            $zeroSearch = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '0', 'sdate' => '', 'edate' => '',
            ]);
            $zeroSearch->assertStatus(200);
            $zeroSearch->assertSee('WP00C-TRACK-001');
            self::assertStringContainsString('name="searchText" value="0"', (string) $zeroSearch->getBody());
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }

        $getFilters = $this->withSession($admin)->get(
            '/ordersListing?search=PARITY-NO-MATCH&sdate=not-a-date&edate=also-bad',
        );
        $getFilters->assertStatus(200);
        $getFilters->assertSee('WP00C-TRACK-001');
        $getBody = (string) $getFilters->getBody();
        self::assertStringContainsString('name="searchText" value=""', $getBody);
        self::assertStringContainsString('name="sdate" value=""', $getBody);
        self::assertStringContainsString('name="edate" value=""', $getBody);

        $offset = $this->withSession($admin)->get('/ordersListing/1');
        $offset->assertStatus(200);
        self::assertStringContainsString('<title>Tracking : branch Listing</title>', (string) $offset->getBody());
    }

    public function testQueueOneLegacyAliasesDateEdgeCasesMatchSourceWithoutRegressingFullDayRange(): void
    {
        $admin = $this->session(1, 1, null);
        $this->db->table('request_order')->insert([
            'request_id' => 91999,
            'requestDate' => '2026-08-01 18:30:00',
            'trackID' => 'FULL-DAY-ORDER',
            'orderID' => 'FULL-DAY',
            'orderIDShow' => 'FULL/DAY',
            'customerFullname' => 'FULL DAY CUSTOMER',
            'customerTel' => '0000000000',
            'branchID' => 1,
            'branch_type_id' => 1,
            'UserID' => 9002,
            'action_status' => 1,
        ]);

        try {
            $fromOnly = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '', 'sdate' => '01/08/2026', 'edate' => '',
            ]);
            $fromOnly->assertStatus(200);
            $fromOnly->assertDontSee('WP00C-TRACK-001');

            $malformed = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '', 'sdate' => 'not-a-date', 'edate' => 'also-bad',
            ]);
            $malformed->assertStatus(200);
            $malformed->assertDontSee('WP00C-TRACK-001');
            self::assertStringContainsString('name="sdate" value="not-a-date"', (string) $malformed->getBody());

            $endOnly = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '', 'sdate' => '', 'edate' => '01/08/2026',
            ]);
            $endOnly->assertStatus(200);
            $endOnly->assertSee('WP00C-TRACK-001');
            self::assertStringContainsString('name="edate" value="01/08/2026"', (string) $endOnly->getBody());

            $zeroDates = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '', 'sdate' => '0', 'edate' => '0',
            ]);
            $zeroDates->assertStatus(200);
            $zeroDates->assertSee('WP00C-TRACK-001');
            self::assertStringContainsString('name="sdate" value=""', (string) $zeroDates->getBody());
            self::assertStringContainsString('name="edate" value=""', (string) $zeroDates->getBody());

            // Intentional correction: unlike CI3's midnight-only upper bound, CI4 keeps
            // the complete end day while retaining the legacy POST contract.
            $validRange = $this->withSession($admin)->post('/ordersListing', [
                'searchText' => '', 'sdate' => '01/08/2026', 'edate' => '01/08/2026',
            ]);
            $validRange->assertStatus(200);
            $validRange->assertSee('WP00C-TRACK-001');
            $validRange->assertSee('FULL-DAY-ORDER');

            $sendFromOnly = $this->withSession($admin)->post('/sendorderListing', [
                'searchText' => '', 'sdate' => '01/08/2026', 'edate' => '',
            ]);
            $sendFromOnly->assertStatus(200);
            $sendFromOnly->assertDontSee('WP00C-TRACK-001');

            $sendMalformed = $this->withSession($admin)->post('/sendorderListing', [
                'searchText' => '', 'sdate' => 'not-a-date', 'edate' => 'also-bad',
            ]);
            $sendMalformed->assertStatus(200);
            $sendMalformed->assertDontSee('WP00C-TRACK-001');

            $sendZeroDates = $this->withSession($admin)->post('/sendorderListing', [
                'searchText' => '', 'sdate' => '0', 'edate' => '0',
            ]);
            $sendZeroDates->assertStatus(200);
            $sendZeroDates->assertSee('WP00C-TRACK-001');
            self::assertStringContainsString('name="sdate" value=""', (string) $sendZeroDates->getBody());
            self::assertStringContainsString('name="edate" value=""', (string) $sendZeroDates->getBody());

            $sendValidRange = $this->withSession($admin)->post('/sendorderListing', [
                'searchText' => '', 'sdate' => '01/08/2026', 'edate' => '01/08/2026',
            ]);
            $sendValidRange->assertStatus(200);
            $sendValidRange->assertSee('WP00C-TRACK-001');
            $sendValidRange->assertSee('FULL-DAY-ORDER');
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testOrdersListingGuestAndLegacyDeleteEndpointMatchBrowserContract(): void
    {
        $guestGet = $this->withSession([])->get('/ordersListing');
        $guestGet->assertStatus(307);
        $guestGet->assertRedirectTo('/login');
        $guestPost = $this->withSession([])->post('/ordersListing', [
            'searchText' => '', 'sdate' => '', 'edate' => '',
        ]);
        $guestPost->assertStatus(303);
        $guestPost->assertRedirectTo('/login');

        $admin = $this->session(1, 1, null);
        $listing = (string) $this->withSession($admin)->get('/ordersListing')->getBody();
        self::assertSame(1, preg_match('/name="csrf_test_name" value="([^"]+)"/', $listing, $matches));

        $deleted = $this->withSession($admin)->post('/deleteOrders', [
            'orderid' => '91001', 'csrf_test_name' => $matches[1],
        ]);
        $deleted->assertStatus(200);
        $deleted->assertJSONExact(['status' => true]);
        self::assertSame(
            8,
            (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'),
        );
        self::assertNotSame('', $deleted->response()->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testQueueOneLegacyAliasesUseCi3LargePaginatorWindowAndAttributes(): void
    {
        for ($start = 1; $start <= 350; $start += 50) {
            $rows = [];
            for ($index = $start; $index <= min($start + 49, 350); $index++) {
                $rows[] = [
                    'request_id' => 92000 + $index,
                    'requestDate' => '2026-08-01 00:00:00',
                    'trackID' => sprintf('ORDER-PAGE-%03d', $index),
                    'orderID' => 'ORDER-' . $index,
                    'orderIDShow' => 'PAGE/' . $index,
                    'customerFullname' => 'PAGINATION CUSTOMER',
                    'customerTel' => '0000000000',
                    'branchID' => 1,
                    'branch_type_id' => 1,
                    'UserID' => 9002,
                    'action_status' => 1,
                ];
            }
            $this->db->table('request_order')->insertBatch($rows);
        }

        $admin = $this->session(1, 1, null);
        $pageOne = (string) $this->withSession($admin)->get('/ordersListing')->getBody();
        self::assertStringContainsString('data-ci-pagination-page="2" rel="next">Next</a>', $pageOne);
        self::assertStringContainsString('data-ci-pagination-page="8">Last</a>', $pageOne);
        self::assertStringNotContainsString('data-ci-pagination-page="7">7</a>', $pageOne);

        $lastPage = (string) $this->withSession($admin)->get('/ordersListing/350')->getBody();
        self::assertStringContainsString('<title>Tracking : branch Listing</title>', $lastPage);
        self::assertStringContainsString('WP00C-TRACK-001', $lastPage);
        self::assertStringContainsString('data-ci-pagination-page="1" rel="start">First</a>', $lastPage);
        self::assertStringContainsString('data-ci-pagination-page="7" rel="prev">Previous</a>', $lastPage);
        self::assertStringContainsString('data-ci-pagination-page="3">3</a>', $lastPage);
        self::assertStringNotContainsString('data-ci-pagination-page="2">2</a>', $lastPage);

        $sendPageOne = (string) $this->withSession($admin)->get('/sendorderListing')->getBody();
        self::assertStringContainsString(
            'href="http://example.invalid/sendorderListing/50" data-ci-pagination-page="2" rel="next">Next</a>',
            $sendPageOne,
        );
        self::assertStringContainsString('data-ci-pagination-page="8">Last</a>', $sendPageOne);

        $sendLastPage = (string) $this->withSession($admin)->get('/sendorderListing/350')->getBody();
        self::assertStringContainsString('<title>Tracking :  Listing</title>', $sendLastPage);
        self::assertStringContainsString('<h3 class="box-title">LOGISTICS List</h3>', $sendLastPage);
        self::assertStringContainsString('WP00C-TRACK-001', $sendLastPage);
        self::assertStringContainsString('data-ci-pagination-page="1" rel="start">First</a>', $sendLastPage);
        self::assertStringContainsString('data-ci-pagination-page="7" rel="prev">Previous</a>', $sendLastPage);
        self::assertStringContainsString('data-ci-pagination-page="3">3</a>', $sendLastPage);
        self::assertStringNotContainsString('data-ci-pagination-page="2">2</a>', $sendLastPage);
    }

    public function testQueueOneDateRangeFiltersBetweenFromAndToInclusive(): void
    {
        $admin = $this->session(1, 1, null);
        $range = static fn (string $from, string $to): string => '/orders?status=1&sdate='
            . rawurlencode($from) . '&edate=' . rawurlencode($to);

        $wide = $this->withSession($admin)->get($range('01/08/2026', '31/08/2026'));
        $wide->assertStatus(200);
        $wide->assertSee('WP00C-TRACK-001');

        // The upper bound is inclusive of the whole day, so a same-day range still matches.
        $sameDay = $this->withSession($admin)->get($range('01/08/2026', '01/08/2026'));
        $sameDay->assertStatus(200);
        $sameDay->assertSee('WP00C-TRACK-001');

        $before = $this->withSession($admin)->get($range('01/07/2026', '31/07/2026'));
        $before->assertStatus(200);
        $before->assertDontSee('WP00C-TRACK-001');
    }

    public function testModernListingDateFilterMatchesExactDayPersistsAndIgnoresMalformedInput(): void
    {
        $admin = $this->session(1, 1, null);

        $match = $this->withSession($admin)->get('/orders?status=2&sdate=' . rawurlencode('02/08/2026'));
        $match->assertStatus(200);
        $match->assertSee('WP00C-TRACK-002');
        self::assertMatchesRegularExpression('/<input(?=[^>]*name="sdate")(?=[^>]*value="02\/08\/2026")[^>]*>/s', (string) $match->getBody());

        $miss = $this->withSession($admin)->get('/orders?status=2&sdate=' . rawurlencode('03/08/2026'));
        $miss->assertStatus(200);
        $miss->assertDontSee('WP00C-TRACK-002');
        self::assertMatchesRegularExpression('/<input(?=[^>]*name="sdate")(?=[^>]*value="03\/08\/2026")[^>]*>/s', (string) $miss->getBody());

        $malformed = $this->withSession($admin)->get('/orders?status=2&sdate=abc');
        $malformed->assertStatus(200);
        $malformed->assertSee('WP00C-TRACK-002');
        self::assertMatchesRegularExpression('/<input(?=[^>]*name="sdate")(?=[^>]*value="abc")[^>]*>/s', (string) $malformed->getBody());
    }

    public function testListingPaginationPreservesResolvedDateAndSearchFilters(): void
    {
        $rows = [];
        for ($index = 1; $index <= 50; $index++) {
            $rows[] = [
                'request_id' => 92000 + $index, 'requestDate' => '2026-08-02 00:00:00',
                'trackID' => sprintf('WP03F-PAGE-%03d', $index), 'orderID' => 'NEEDLE-' . $index,
                'customerFullname' => 'PAGINATION CUSTOMER', 'customerTel' => '0000000000',
                'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 1,
            ];
        }
        $this->db->table('request_order')->insertBatch($rows);

        $response = $this->withSession($this->session(1, 1, null))->get(
            '/orders?status=1&sdate=' . rawurlencode('02/08/2026') . '&search=needle',
        );
        $response->assertStatus(200);
        // Exactly 50 filtered rows are one CI3 page; search/date persist in the POST form.
        self::assertStringNotContainsString('href="http://example.invalid/ordersListing/50"', $response->getBody());
        self::assertStringContainsString('name="sdate" value="02/08/2026"', $response->getBody());
        self::assertStringContainsString('name="searchText" value="needle"', $response->getBody());

        $withoutFilters = $this->withSession($this->session(1, 1, null))->get('/orders?status=1');
        self::assertStringContainsString('href="http://example.invalid/ordersListing/50"', $withoutFilters->getBody());
    }

    public function testOrderSequenceStartsAfterExistingLegacyTrackingId(): void
    {
        self::assertSame(0, $this->db->table('ci4_order_sequences')->countAllResults());
        $this->db->table('request_order')->insert([
            'requestDate' => '2026-08-22 00:00:00',
            'trackID' => 'WPA26080042',
        ]);
        $sequence = new OrderSequence($this->db);
        $now = new DateTimeImmutable('2026-08-22T00:00:00+00:00');

        self::assertSame('WPA26080043', $sequence->next($now, 'WPA'));
        self::assertSame('WPA26080044', $sequence->next($now, 'WPA'));
    }

    public function testOrderSequenceScopesSequenceByBranchSuffix(): void
    {
        $sequence = new OrderSequence($this->db);
        $now = new DateTimeImmutable('2026-08-22T00:00:00+00:00');

        // Two branches with different suffixes in the same month each start their own run at 0001.
        self::assertSame('WPA26080001', $sequence->next($now, 'WPA'));
        self::assertSame('WPB26080001', $sequence->next($now, 'WPB'));
        self::assertSame('WPA26080002', $sequence->next($now, 'WPA'));
        self::assertSame('WPB26080002', $sequence->next($now, 'WPB'));
    }

    public function testCreateAndEditUseOrderLayoutUploadContractAndConditionalValidationScripts(): void
    {
        $expectedOrderAssets = [
            '/assets/css/style.css',
            '/assets/js/browse/jquery.knob.js',
            '/assets/js/browse/jquery.ui.widget.js',
            '/assets/js/browse/jquery.iframe-transport.js',
            '/assets/js/browse/jquery.fileupload.js',
            '/assets/js/browse/script.js',
        ];

        foreach ([
            'admin' => $this->session(1, 1, null),
            'branch' => $this->session(2, 2, 1),
        ] as $actor => $session) {
            foreach (['/orders/new', '/orders/91001'] as $route) {
                $response = $this->withSession($session)->get($route);
                $response->assertStatus(200);
                $body = (string) $response->getBody();
                self::assertStringContainsString('<body class="skin-blue sidebar-mini">', $body, $actor . ' ' . $route);
                self::assertStringContainsString('id="addOrder"', $body, $actor . ' ' . $route);
                self::assertStringContainsString('id="upload"', $body, $actor . ' ' . $route);
                self::assertStringContainsString('id="drop"', $body, $actor . ' ' . $route);
                self::assertStringContainsString('name="upl"', $body, $actor . ' ' . $route);
                self::assertStringNotContainsString('name="detail_image[]"', $body, $actor . ' ' . $route);
                self::assertStringNotContainsString('new DataTransfer()', $body, $actor . ' ' . $route);
                self::assertSame(1, substr_count($body, 'class="content-wrapper"'), $actor . ' ' . $route);

                self::assertSame(1, preg_match(
                    '#value="([a-f0-9]{32})" id="times" name="times"#',
                    $body,
                    $submission,
                ));
                self::assertStringContainsString('action="http://example.invalid//order/do_upload_multi/' . $submission[1] . '"', $body);
                self::assertStringContainsString('var xtimesite = "' . $submission[1] . '";', $body);
                $actorScript = $actor === 'admin' ? '/assets/js/admin_addOrder.js' : '/assets/js/addOrder.js';
                $otherScript = $actor === 'admin' ? '/assets/js/addOrder.js' : '/assets/js/admin_addOrder.js';
                self::assertStringContainsString($actorScript, $body, $actor . ' ' . $route);
                self::assertStringNotContainsString($otherScript, $body, $actor . ' ' . $route);

                $offset = -1;
                foreach ($expectedOrderAssets as $asset) {
                    $next = strpos($body, $asset);
                    self::assertNotFalse($next, $actor . ' ' . $route . ' missing ' . $asset);
                    self::assertGreaterThan($offset, $next, $actor . ' ' . $route . ' asset order ' . $asset);
                    $offset = $next;
                }
            }
        }
    }

    public function testCreateAndEditUsePinnedCi3BackgroundFormAsset(): void
    {
        foreach ([$this->session(1, 1, null), $this->session(2, 2, 1)] as $session) {
            foreach (['/orders/new', '/orders/91001'] as $route) {
                $body = (string) $this->withSession($session)->get($route)->getBody();
                self::assertStringContainsString('/assets/images/bg-form.png', $body, $route);
            }
        }

        $asset = ROOTPATH . 'public/assets/images/bg-form.png';
        self::assertFileExists($asset);
        self::assertSame('65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0', hash_file('sha256', $asset));
    }

    public function testUploadAdapterFollowsExactCallbacksAcrossOperationAndContextBoundaries(): void
    {
        $partial = view('partials/order_upload', [
            'submissionId' => str_repeat('a', 32),
            'targetId' => 'order-image',
        ]);
        self::assertSame(1, preg_match('#<script>(.*)</script>#s', $partial, $match));
        $glue = file_get_contents(ROOTPATH . 'public/assets/js/browse/script.js');
        self::assertIsString($glue);

        $path = tempnam(sys_get_temp_dir(), 'tpl01-upload-exact-');
        self::assertIsString($path);
        $harness = <<<'JS'
const handlers = {};
const failures = [];
const triggerOrder = [];
const target = { files: [] };
const uploadRoot = element('upload');
const uploadList = element('ul', uploadRoot);
const dropRoot = element('drop');
const dropLink = element('a', dropRoot);
const documentElement = element('document');
let fileuploadOptions;
let readerMode = 'deferred';
let pendingReaders = [];
let activeSubmission;
const parsedFindCounts = [];
const parsedInputs = [];
const parsedRootKinds = [];

function element(kind, parent) {
    return {
        kind,
        parent: parent || null,
        classes: new Set(kind === 'li' ? ['working'] : []),
        values: new Map(),
        children: {},
        clickHandlers: [],
        removed: false,
        value: undefined,
        changed: 0,
        knobbed: 0
    };
}

class Collection {
    constructor(elements) {
        this.length = 0;
        Array.prototype.push.apply(this, elements || []);
    }
    get() { return Array.prototype.slice.call(this, 0, this.length); }
    on(event, selectorOrHandler, handler) {
        if (this[0] === uploadRoot) handlers[event] = handler || selectorOrHandler;
        return this;
    }
    fileupload(options) { fileuploadOptions = options; return this; }
    click(handler) { this.get().forEach((item) => { item.clickHandlers.push(handler); }); return this; }
    parent() { return new Collection(this.get().map((item) => item.parent).filter(Boolean)); }
    find(selector) {
        const found = [];
        function visit(item) {
            Object.keys(item.children).forEach((key) => {
                const child = item.children[key];
                if (child.kind === selector) found.push(child);
                visit(child);
            });
        }
        this.get().forEach(visit);
        return new Collection(found);
    }
    filter(selector) { return new Collection(this.get().filter((item) => item.kind === selector)); }
    first() { return new Collection(this.length ? [this[0]] : []); }
    appendTo(target) {
        if (target.length) this.get().forEach((item) => { item.parent = target[0]; });
        return this;
    }
    prependTo(target) {
        if (this.length && target.length) {
            this[0].parent = target[0];
            target[0].children[this[0].kind] = this[0];
        }
        return this;
    }
    text(value) { this.get().forEach((item) => { item.text = value; }); return this; }
    append(value) { this.get().forEach((item) => { item.appended = value; }); return this; }
    knob() { this.get().forEach((item) => { item.knobbed += 1; }); return this; }
    val(value) {
        if (arguments.length === 0) return this.length ? this[0].value : undefined;
        this.get().forEach((item) => { item.value = value; });
        return this;
    }
    change() { this.get().forEach((item) => { item.changed += 1; }); return this; }
    addClass(name) { this.get().forEach((item) => item.classes.add(name)); return this; }
    removeClass(name) { this.get().forEach((item) => item.classes.delete(name)); return this; }
    hasClass(name) { return this.get().some((item) => item.classes.has(name)); }
    data(key, value) {
        if (arguments.length === 2) {
            this.get().forEach((item) => item.values.set(key, value));
            return this;
        }
        return this.length ? this[0].values.get(key) : undefined;
    }
    closest(selector) {
        if (selector !== 'li' || !this.length) return new Collection([]);
        let item = this[0];
        while (item && item.kind !== 'li') item = item.parent;
        return new Collection(item ? [item] : []);
    }
    fadeOut(callback) { if (callback) callback(); return this; }
    remove() {
        this.get().forEach((item) => {
            item.removed = true;
            item.parent = null;
        });
        return this;
    }
}
Collection.prototype.push = Array.prototype.push;
Collection.prototype.splice = Array.prototype.splice;

function previewCollection() {
    const input = element('input');
    const root = element('li');
    root.children.p = element('p', root);
    root.children.span = element('span', root);
    const collection = new Collection([input, root]);
    parsedFindCounts.push(collection.find('input').length);
    parsedInputs.push(input);
    parsedRootKinds.push(collection.get().map((item) => item.kind).join(','));
    return collection;
}

function jquery(value) {
    if (typeof value === 'function') { value(); return new Collection([]); }
    if (value instanceof Collection) return value;
    if (value === undefined || value === null) return new Collection([]);
    if (value === '#upload') return new Collection([uploadRoot]);
    if (value === '#upload ul') return new Collection([uploadList]);
    if (value === '#drop') return new Collection([dropRoot]);
    if (value === '#drop a') return new Collection([dropLink]);
    if (value === global.document) return new Collection([documentElement]);
    if (typeof value === 'string' && value.charAt(0) === '<') return previewCollection();
    if (typeof value === 'string' && value.indexOf('input[name="') === 0) return new Collection([element('csrf')]);
    if (typeof value === 'object') return new Collection([value]);
    throw new Error('Unexpected jQuery value: ' + String(value));
}
jquery.inArray = (value, values) => values.indexOf(value);

class FakeDataTransfer {
    constructor() {
        const files = [];
        this.files = files;
        this.items = { add(file) { files.push(file); } };
    }
}

class FakeFileReader {
    readAsDataURL(file) {
        this.file = file;
        if (readerMode === 'sync') this.flush();
        else pendingReaders.push(this);
    }
    flush() { this.onload({ target: { result: 'data:image/png;base64,AA==' } }); }
}

global.DataTransfer = FakeDataTransfer;
global.FileReader = FakeFileReader;
global.document = { getElementById() { return target; } };
global.jQuery = global.$ = jquery;
global.window = { alert() {} };

function check(condition, label) {
    if (!condition) failures.push(label);
}
function safely(label, callback) {
    try { callback(); } catch (error) { failures.push(label + ':' + error.name + ':' + error.message); }
}
function makeFile(name, marker) { return { name, marker, size: 1024 }; }
function trigger(type, data) {
    triggerOrder.push('event:' + type);
    const handler = handlers['fileupload' + type];
    if (handler) handler(null, data);
    const callback = fileuploadOptions && fileuploadOptions[type];
    if (callback) {
        triggerOrder.push('callback:' + type);
        return callback(null, data);
    }
}
function addOperation(files, mode) {
    readerMode = mode || 'deferred';
    const data = { files };
    data.abortCount = 0;
    data.submit = function() {
        const completion = Object.assign({}, data);
        activeSubmission = { original: data, completion };
        return { abort() { data.abortCount += 1; } };
    };
    activeSubmission = null;
    trigger('add', data);
    check(activeSubmission !== null, 'EXACT_ADD_DID_NOT_SUBMIT');
    return activeSubmission;
}
function flushNextReader() {
    const reader = pendingReaders.shift();
    check(!!reader, 'MISSING_PENDING_READER');
    if (reader) reader.flush();
}
function complete(operation, status) {
    operation.completion.result = { status: status || 'success' };
    trigger('done', operation.completion);
}
function fail(operation, textStatus) {
    operation.completion.jqXHR = { responseJSON: null };
    operation.completion.textStatus = textStatus || 'error';
    trigger('fail', operation.completion);
}
function progress(operation, loaded, total) {
    operation.completion.loaded = loaded;
    operation.completion.total = total;
    trigger('progress', operation.completion);
}
function contextOf(operation) { return operation.original.context; }
function previewOf(operation) {
    const context = contextOf(operation);
    return context && context.get().find((item) => item.kind === 'li') || null;
}
function progressInputOf(operation) {
    const context = contextOf(operation);
    const inputs = context ? context.find('input') : new Collection([]);
    return inputs.length ? inputs[0] : null;
}
function clickPreview(operation) {
    const preview = previewOf(operation);
    check(!!preview, 'MISSING_PREVIEW');
    if (!preview) return { direct: [], delegated: 0 };

    const span = preview.children.span;
    const direct = [];
    span.clickHandlers.slice().forEach((handler) => {
        handler.call(span);
        direct.push({ queue: target.files.length, liInDom: preview.parent !== null });
    });

    let node = span;
    while (node && node !== uploadRoot) node = node.parent;
    if (node === uploadRoot && handlers.click) {
        handlers.click.call(span);
        return { direct, delegated: 1 };
    }
    return { direct, delegated: 0 };
}
function expectQueue(label, expected) {
    check(target.files.length === expected.length, label + '_COUNT=' + target.files.length);
    expected.forEach((file, index) => check(target.files[index] === file, label + '_IDENTITY_' + index));
}

const earlyFile = makeFile('early.jpg', 'early');
const early = addOperation([earlyFile], 'sync');
check(triggerOrder[0] === 'event:add' && triggerOrder[1] === 'callback:add', 'ADD_ORDER=' + triggerOrder.slice(0, 2).join('>'));
check(early.original !== early.completion, 'CLONE_NOT_DISTINCT');
check(early.original.files[0] === early.completion.files[0], 'FILE_IDENTITY_NOT_SHARED');
const sharedMarker = Object.keys(early.original).find((key) => {
    const value = early.original[key];
    return value && value === early.completion[key] && Array.isArray(value.files);
});
check(!!sharedMarker, 'OPERATION_MARKER_NOT_SHARED');
check(early.original.context === early.completion.context, 'CONTEXT_BRIDGE_NOT_SHARED');
check(parsedRootKinds[0] === 'input,li', 'EXACT_FRAGMENT_ROOTS=' + parsedRootKinds[0]);
check(parsedFindCounts[0] === 0, 'EXACT_FRAGMENT_INPUT_WAS_NOT_TOP_LEVEL');
check(early.original.context.find('input').length === 1, 'NORMALIZED_CONTEXT_INPUT_COUNT=' + early.original.context.find('input').length);
check(progressInputOf(early) === parsedInputs[0], 'NORMALIZED_CONTEXT_REPLACED_INPUT');
check(progressInputOf(early) && progressInputOf(early).knobbed === 1, 'KNOB_DID_NOT_REACH_REAL_INPUT');
complete(early);
expectQueue('EARLY_QUEUE', [earlyFile]);
check(!previewOf(early).classes.has('working'), 'EARLY_PREVIEW_STILL_WORKING');
const earlyClick = clickPreview(early);
check(
    earlyClick.direct.length === 2,
    'NATIVE_CLICK_ORDER queue=' + target.files.length + ' delegated=' + earlyClick.delegated + ' liInDom=' + (previewOf(early).parent !== null)
);
check(earlyClick.direct[0] && earlyClick.direct[0].queue === 0, 'ADAPTER_DIRECT_DID_NOT_REMOVE_QUEUE_FIRST');
check(earlyClick.direct[0] && earlyClick.direct[0].liInDom, 'ADAPTER_DIRECT_RAN_AFTER_DOM_REMOVAL');
check(earlyClick.direct[1] && !earlyClick.direct[1].liInDom, 'EXACT_DIRECT_DID_NOT_REMOVE_PREVIEW');
check(earlyClick.delegated === 0, 'DETACHED_PREVIEW_UNEXPECTEDLY_REACHED_DELEGATED_FALLBACK');
expectQueue('EARLY_DELETE', []);

const lateFile = makeFile('late.jpg', 'late');
const late = addOperation([lateFile], 'deferred');
complete(late);
expectQueue('COMPLETION_BEFORE_CONTEXT_QUEUE', [lateFile]);
flushNextReader();
check(!previewOf(late).classes.has('working'), 'LATE_PREVIEW_STILL_WORKING');
clickPreview(late);
expectQueue('COMPLETION_BEFORE_CONTEXT_DELETE', []);

const progressFile = makeFile('progress.jpg', 'progress');
const progressing = addOperation([progressFile], 'deferred');
safely('PROGRESS_BEFORE_CONTEXT_THROW', () => progress(progressing, 25, 100));
flushNextReader();
safely('PROGRESS_AFTER_CONTEXT_THROW', () => progress(progressing, 50, 100));
const progressInput = progressInputOf(progressing);
check(progressInput && progressInput.value === 50, 'PROGRESS_DID_NOT_REACH_PREVIEW');
check(progressInput && progressInput.changed > 0, 'PROGRESS_DID_NOT_CHANGE_PREVIEW');

const callbackFile = makeFile('callbacks.jpg', 'callbacks');
const callbacks = addOperation([callbackFile], 'deferred');
safely('FAIL_BEFORE_CONTEXT_THROW', () => fail(callbacks));
expectQueue('CALLBACKS_BEFORE_CONTEXT_QUEUE', []);
flushNextReader();
const callbackPreview = previewOf(callbacks);
check(!callbackPreview.classes.has('working'), 'FAIL_REPLAY_LEFT_PREVIEW_WORKING');
check(callbackPreview.classes.has('error'), 'FAIL_REPLAY_DID_NOT_MARK_PREVIEW');
clickPreview(callbacks);
expectQueue('FAILED_PENDING_DELETE', []);

const repeatedFile = makeFile('repeat.jpg', 'same-file-object');
const repeatedFirst = addOperation([repeatedFile], 'sync');
const repeatedSecond = addOperation([repeatedFile], 'sync');
complete(repeatedFirst);
complete(repeatedSecond);
expectQueue('SAME_FILE_TWICE_QUEUE', [repeatedFile, repeatedFile]);
check(
    previewOf(repeatedFirst).values.get('orderQueueItem') !== previewOf(repeatedSecond).values.get('orderQueueItem'),
    'SAME_FILE_OCCURRENCES_SHARE_GROUP'
);
clickPreview(repeatedFirst);
expectQueue('SAME_FILE_FIRST_DELETE', [repeatedFile]);
clickPreview(repeatedSecond);
expectQueue('SAME_FILE_SECOND_DELETE', []);

const multiFirst = makeFile('multi-a.jpg', 'multi-a');
const multiSecond = makeFile('multi-b.jpg', 'multi-b');
const multi = addOperation([multiFirst, multiSecond], 'sync');
complete(multi);
expectQueue('MULTI_FILE_QUEUE', [multiFirst, multiSecond]);
clickPreview(multi);
expectQueue('MULTI_FILE_GROUP_DELETE', []);

const duplicateFirstFile = makeFile('duplicate.jpg', 'duplicate-first');
const duplicateSecondFile = makeFile('duplicate.jpg', 'duplicate-second');
const duplicateFirst = addOperation([duplicateFirstFile], 'sync');
const duplicateSecond = addOperation([duplicateSecondFile], 'sync');
complete(duplicateSecond);
complete(duplicateFirst);
expectQueue('INTERLEAVED_DUPLICATE_QUEUE', [duplicateSecondFile, duplicateFirstFile]);
clickPreview(duplicateFirst);
expectQueue('INTERLEAVED_FIRST_DELETE', [duplicateSecondFile]);
clickPreview(duplicateSecond);
expectQueue('INTERLEAVED_SECOND_DELETE', []);

const pendingSurvivorFile = makeFile('pending-survivor.jpg', 'pending-survivor');
const pendingSurvivor = addOperation([pendingSurvivorFile], 'sync');
complete(pendingSurvivor);
const pending = addOperation([makeFile('pending.jpg', 'pending')], 'sync');
const pendingClick = clickPreview(pending);
check(pending.original.abortCount === 1, 'PENDING_ABORT_COUNT=' + pending.original.abortCount);
check(pendingClick.direct.length === 2, 'PENDING_DIRECT_HANDLER_COUNT=' + pendingClick.direct.length);
expectQueue('PENDING_CANCEL_QUEUE', [pendingSurvivorFile]);
clickPreview(pendingSurvivor);
expectQueue('PENDING_SURVIVOR_DELETE', []);

const aborted = addOperation([makeFile('abort.jpg', 'abort')], 'deferred');
safely('ABORT_BEFORE_CONTEXT_THROW', () => fail(aborted, 'abort'));
expectQueue('ABORT_QUEUE', []);
flushNextReader();
clickPreview(aborted);

const rejected = addOperation([makeFile('rejected.jpg', 'rejected')], 'sync');
complete(rejected, 'error');
complete(rejected, 'success');
expectQueue('REJECTED_THEN_SUCCESS_QUEUE', []);
check(previewOf(rejected).classes.has('error'), 'REJECTED_PREVIEW_NOT_ERROR');
check(previewOf(rejected).values.get('orderQueueItem') === undefined, 'REJECTED_GAINED_QUEUE_MARKER');
clickPreview(rejected);

const failedThenDone = addOperation([makeFile('failed-then-done.jpg', 'failed-then-done')], 'sync');
fail(failedThenDone);
complete(failedThenDone);
expectQueue('FAILED_THEN_DONE_QUEUE', []);
check(!previewOf(failedThenDone).classes.has('working'), 'FAILED_PREVIEW_STILL_WORKING');
check(previewOf(failedThenDone).classes.has('error'), 'FAILED_PREVIEW_NOT_ERROR');

const limitFiles = [0, 1, 2, 3, 4].map((index) => makeFile('limit-' + index + '.jpg', 'limit-' + index));
const limit = addOperation(limitFiles, 'sync');
complete(limit);
expectQueue('LIMIT_BASE_QUEUE', limitFiles);
const overLimit = addOperation([makeFile('over-limit.jpg', 'over-limit')], 'sync');
complete(overLimit);
clickPreview(limit);
expectQueue('LIMIT_BASE_DELETE', []);
complete(overLimit);
expectQueue('OVER_LIMIT_REPEAT_QUEUE', []);
check(previewOf(overLimit).classes.has('error'), 'OVER_LIMIT_PREVIEW_NOT_ERROR');
check(previewOf(overLimit).values.get('orderQueueItem') === undefined, 'OVER_LIMIT_GAINED_QUEUE_MARKER');
clickPreview(overLimit);

const onceFile = makeFile('once.jpg', 'once');
const once = addOperation([onceFile], 'sync');
complete(once);
complete(once);
fail(once);
expectQueue('REPEATED_COMPLETION_QUEUE', [onceFile]);
check(!previewOf(once).classes.has('error'), 'SUCCESS_CHANGED_TO_ERROR');
clickPreview(once);
expectQueue('REPEATED_COMPLETION_DELETE', []);

const missingFile = makeFile('missing.jpg', 'missing');
const missing = addOperation([missingFile], 'deferred');
safely('MISSING_CONTEXT_COMPLETION_THROW', () => complete(missing));
expectQueue('MISSING_CONTEXT_QUEUE', [missingFile]);
flushNextReader();
clickPreview(missing);
expectQueue('FINAL_QUEUE', []);

if (failures.length) throw new Error(failures.join('\n'));
JS;

        [$setup, $assertions] = explode("\nconst earlyFile =", $harness, 2);
        file_put_contents(
            $path,
            $setup . "\n" . $match[1] . "\n" . $glue . "\nconst earlyFile =" . $assertions,
        );

        try {
            $process = proc_open(
                ['/usr/bin/env', 'node', $path],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                ROOTPATH,
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), trim($output . "\n" . $error));
        } finally {
            @unlink($path);
        }
    }

    public function testUploadAdapterDeletesTheFileBoundToTheClickedDuplicateNamePreview(): void
    {
        $partial = view('partials/order_upload', [
            'submissionId' => str_repeat('a', 32),
            'targetId' => 'order-image',
        ]);
        self::assertSame(1, preg_match('#<script>(.*)</script>#s', $partial, $match));

        $path = tempnam(sys_get_temp_dir(), 'tpl01-upload-adapter-');
        self::assertIsString($path);
        $harness = <<<'JS'
const handlers = {};
const target = { files: [] };

class FakeDataTransfer {
    constructor() {
        const files = [];
        this.files = files;
        this.items = { add(file) { files.push(file); } };
    }
}

class ClickTarget {
    constructor(context) {
        this.context = context;
        this.handlers = [];
    }
    click(handler) { this.handlers.push(handler); return this; }
    dispatchClick() {
        this.handlers.slice().forEach((handler) => handler.call(this));
        if (handlers.click) handlers.click.call(this);
    }
}

class Context {
    constructor(fileName) {
        this.fileName = fileName;
        this.values = new Map();
        this.span = new ClickTarget(this);
        this.kind = 'li';
        this.length = 1;
        this[0] = this;
    }
    get() { return [this]; }
    filter(selector) { return selector === 'li' ? this : new Bridge(); }
    first() { return this; }
    prependTo() { return this; }
    addClass() { return this; }
    removeClass() { return this; }
    find(selector) { return selector === 'span' ? this.span : { val: () => this.fileName }; }
    data(key, value) {
        if (arguments.length === 2) {
            this.values.set(key, value);
            return this;
        }
        return this.values.get(key);
    }
}

class Bridge {
    constructor() { this.length = 0; }
    get() { return Array.prototype.slice.call(this, 0, this.length); }
    filter(selector) {
        const result = new Bridge();
        Array.prototype.push.apply(result, this.get().filter((item) => item.kind === selector));
        return result;
    }
    first() {
        const result = new Bridge();
        if (this.length) result.push(this[0]);
        return result;
    }
    prependTo() { return this; }
    addClass(name) { if (this.length) this[0].addClass(name); return this; }
    removeClass(name) { if (this.length) this[0].removeClass(name); return this; }
    data(key, value) {
        if (!this.length) return arguments.length === 2 ? this : undefined;
        return arguments.length === 2 ? this[0].data(key, value) : this[0].data(key);
    }
}
Bridge.prototype.push = Array.prototype.push;

const upload = {
    on(event, selectorOrHandler, handler) {
        handlers[event] = handler || selectorOrHandler;
        return this;
    }
};
const csrf = { val() { return this; } };
function jquery(value) {
    if (value === undefined) return new Bridge();
    if (value === '#upload') return upload;
    if (value instanceof Context || value instanceof Bridge) return value;
    if (typeof value === 'string' && value.startsWith('input[name="')) return csrf;
    if (value && value.context) return { closest() { return value.context; } };
    throw new Error('Unexpected jQuery value: ' + String(value));
}

global.DataTransfer = FakeDataTransfer;
global.document = { getElementById() { return target; } };
global.jQuery = jquery;
JS;
        $assertions = <<<'JS'
function original(file) {
    const data = { files: [file] };
    if (handlers.fileuploadadd) handlers.fileuploadadd(null, data);
    return data;
}
function completion(data, status = 'success') {
    return Object.assign({}, data, { result: { status } });
}
function expectQueue(label, expected) {
    if (target.files.length !== expected.length || expected.some((file, index) => target.files[index] !== file)) {
        throw new Error(label + '=' + target.files.length);
    }
}

const first = { name: 'camera.jpg', bytes: 'first' };
const second = { name: 'camera.jpg', bytes: 'second' };
const firstOriginal = original(first);
const secondOriginal = original(second);
const firstContext = firstOriginal.context = new Context(first.name);
const secondContext = secondOriginal.context = new Context(second.name);
handlers.fileuploaddone(null, completion(firstOriginal));
handlers.fileuploaddone(null, completion(secondOriginal));
expectQueue('EARLY_DUPLICATE_QUEUE', [first, second]);

const pendingContext = new Context(first.name);
pendingContext.span.dispatchClick();
expectQueue('PENDING_CANCEL_QUEUE', [first, second]);
secondContext.span.dispatchClick();
expectQueue('CLICKED_SECOND_QUEUE', [first]);
firstContext.span.dispatchClick();
expectQueue('CLICKED_FIRST_QUEUE', []);

const late = { name: 'camera.jpg', bytes: 'late' };
const lateOriginal = original(late);
const lateCompletion = completion(lateOriginal);
if (lateCompletion === lateOriginal || lateCompletion.files[0] !== lateOriginal.files[0]) {
    throw new Error('PLUGIN_SHALLOW_CLONE_SEMANTICS_MISSING');
}
handlers.fileuploaddone(null, lateCompletion);
expectQueue('REAL_CLONE_LATE_COMPLETION_QUEUE', [late]);
const lateContext = lateOriginal.context = new Context(late.name);
lateContext.span.dispatchClick();
expectQueue('REAL_CLONE_LATE_QUEUE', []);

const interleavedFirst = { name: 'same.jpg', bytes: 'interleaved-first' };
const interleavedSecond = { name: 'same.jpg', bytes: 'interleaved-second' };
const interleavedFirstOriginal = original(interleavedFirst);
const interleavedSecondOriginal = original(interleavedSecond);
handlers.fileuploaddone(null, completion(interleavedSecondOriginal));
handlers.fileuploaddone(null, completion(interleavedFirstOriginal));
const interleavedFirstContext = interleavedFirstOriginal.context = new Context(interleavedFirst.name);
const interleavedSecondContext = interleavedSecondOriginal.context = new Context(interleavedSecond.name);
expectQueue('INTERLEAVED_DUPLICATE_ORDER', [interleavedSecond, interleavedFirst]);
interleavedFirstContext.span.dispatchClick();
expectQueue('INTERLEAVED_FIRST_DELETE_QUEUE', [interleavedSecond]);
interleavedSecondContext.span.dispatchClick();
expectQueue('INTERLEAVED_SECOND_DELETE_QUEUE', []);

const failed = original({ name: 'failed.jpg' });
handlers.fileuploadfail(null, completion(failed));
expectQueue('FAILED_QUEUE', []);
const aborted = original({ name: 'aborted.jpg' });
handlers.fileuploadfail(null, Object.assign(completion(aborted), { textStatus: 'abort' }));
expectQueue('ABORTED_QUEUE', []);
const rejected = original({ name: 'rejected.jpg' });
handlers.fileuploaddone(null, completion(rejected, 'error'));
rejected.context = new Context('rejected.jpg');
expectQueue('ERROR_RESULT_QUEUE', []);

const orphan = { name: 'same.jpg', bytes: 'orphan' };
const orphanOriginal = original(orphan);
const orphanCompletion = completion(orphanOriginal);
handlers.fileuploaddone(null, orphanCompletion);
handlers.fileuploaddone(null, orphanCompletion);
expectQueue('COMPLETION_ONCE_QUEUE', [orphan]);
const other = { name: 'same.jpg', bytes: 'other' };
const otherOriginal = original(other);
const otherContext = otherOriginal.context = new Context(other.name);
handlers.fileuploaddone(null, completion(otherOriginal));
otherContext.span.dispatchClick();
expectQueue('MISSING_CONTEXT_NO_CROSS_BIND_QUEUE', [orphan]);
const orphanContext = orphanOriginal.context = new Context(orphan.name);
orphanContext.span.dispatchClick();
expectQueue('FINAL_QUEUE', []);
JS;
        file_put_contents($path, $harness . "\n" . $match[1] . "\n" . $assertions);

        try {
            $process = proc_open(
                ['/usr/bin/env', 'node', $path],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                ROOTPATH,
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), trim($output . "\n" . $error));
        } finally {
            @unlink($path);
        }
    }

    public function testEditRendersCi3UploadPathsWhileEscapingStoredImageNames(): void
    {
        $first = str_repeat('a', 32) . '.png';
        $second = str_repeat('b', 32) . '.png';
        $malformed = '"><script>alert(1)</script>.png';
        $this->db->table('request_order')->where('request_id', 91001)->update([
            'detailImage' => implode('|', [$first, 'legacy.jpg', '../escape.png', $malformed, $second]),
        ]);

        $response = $this->withSession($this->session(2, 2, 1))->get('/orders/91001');
        $response->assertStatus(200);
        $body = $response->getBody();
        self::assertStringContainsString('src="http://example.invalid/uploads/' . $first . '"', $body);
        self::assertStringContainsString('src="http://example.invalid/uploads/' . $second . '"', $body);
        // CI3 edit_order.php renders every pipe-delimited stored name under uploads/.
        self::assertStringContainsString('src="http://example.invalid/uploads/legacy.jpg"', $body);
        self::assertStringContainsString('src="http://example.invalid/uploads/../escape.png"', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function testOrderLayoutRendersPageOwnedContentDirectlyBetweenSidebarAndFooter(): void
    {
        $body = view('layout_order', [
            'pageTitle' => 'Order', 'title' => 'Order', 'content' => '<div id="order-page-probe"></div>',
            'isLoggedIn' => true, 'name' => '', 'role_text' => '', 'last_login' => '', 'GroupID' => 1,
            'BranchID' => null, 'BranchName' => '', 'subtitle' => '', 'actions' => '', 'menuItems' => [],
            'showBranchAutocomplete' => false, 'branchOptions' => [], 'accessDeniedProfile' => false,
        ]);

        self::assertMatchesRegularExpression('#</aside>\s*<div id="order-page-probe"></div>\s*<footer class="main-footer">#', $body);
        self::assertStringNotContainsString('<div class="content-wrapper">', $body);
        self::assertStringNotContainsString('<section class="content-header">', $body);
        self::assertStringNotContainsString('<section class="content">', $body);
    }

    public function testOrderAssetsDoNotLeakIntoIndependentAdminAndPrintCallers(): void
    {
        $admin = $this->session(1, 1, null);
        foreach (['/ordersListing', '/TrackingListing', '/ReportTrackingListing', '/orders/91001/print'] as $route) {
            $response = $this->withSession($admin)->get($route);
            $response->assertStatus(200);
            $body = (string) $response->getBody();
            self::assertStringNotContainsString('/assets/css/style.css', $body, $route);
            self::assertStringNotContainsString('/assets/js/browse/', $body, $route);
            self::assertStringNotContainsString('/assets/js/addOrder.js', $body, $route);
            self::assertStringNotContainsString('/assets/js/admin_addOrder.js', $body, $route);
        }
    }

    public function testLegacyValidationFieldNamesReachCreateAndEditPersistence(): void
    {
        $create = $this->validOrderPayload([
            'submission_id' => str_repeat('d', 32), 'number_id' => '7301', 'customer_tel' => '7777777777',
        ]);
        foreach ([
            'book_id' => 'bookshort', 'customer_name' => 'customerFullname', 'customer_tel' => 'customerTel',
            'customer_email' => 'email', 'type_id' => 'detailTypeId', 'brand_id' => 'detailBrandId',
        ] as $canonical => $legacy) {
            $create[$legacy] = $create[$canonical];
            unset($create[$canonical]);
        }
        $this->postOrder($create)->assertRedirect();
        self::assertSame(1, $this->db->table('request_order')->where('customerTel', '7777777777')->countAllResults());

        $edit = $this->editPayload(['customer_tel' => '8888888888']);
        foreach ([
            'customer_name' => 'customerFullname', 'customer_tel' => 'customerTel', 'customer_email' => 'email',
            'type_id' => 'detailTypeId', 'brand_id' => 'detailBrandId',
        ] as $canonical => $legacy) {
            $edit[$legacy] = $edit[$canonical];
            unset($edit[$canonical]);
        }
        $this->postEdit(91001, $edit)->assertRedirect();
        self::assertSame('8888888888', (string) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('customerTel'));
    }

    public function testNewOrderFormUsesCi3AjaxBranchAndBookCascade(): void
    {
        $admin = $this->withSession($this->session(1, 1, null))->get('/orders/new');
        $admin->assertStatus(200);
        $adminBody = (string) $admin->getBody();
        self::assertStringContainsString('id="branch_type" name="branch_type"', $adminBody);
        self::assertStringContainsString('TYPE ONE', $adminBody);
        self::assertStringContainsString('id="branch_id" name="branch_id"', $adminBody);
        self::assertStringContainsString('id="bookshort" name="bookshort"', $adminBody);
        self::assertStringContainsString('id="numberID"', $adminBody);
        self::assertStringContainsString("value=value.replace(/\\D/g,'')", $adminBody);
        self::assertStringContainsString('new XMLHttpRequest()', $adminBody);
        self::assertStringContainsString('user/get_list_branch/', $adminBody);
        self::assertStringContainsString('user/get_list_book/', $adminBody);
        self::assertStringNotContainsString('data-book-detail=', $adminBody);
        self::assertStringNotContainsString('<output id="order-id-preview"', $adminBody);

        foreach ([2, 3] as $role) {
            $branchBody = (string) $this->withSession($this->session($role, $role, 1))->get('/orders/new')->getBody();
            self::assertStringNotContainsString('id="branch_type" name="branch_type"', $branchBody);
            self::assertStringContainsString('id="bookshort" name="bookshort" value=""', $branchBody);
            self::assertStringContainsString('id="branchshort" name="branchshort" value="WPA"', $branchBody);
            self::assertStringNotContainsString('data-branch-id=', $branchBody);
        }
    }

    public function testCreateOrderDerivesCanonicalIdentifiersAndKeepsDigitBoundaries(): void
    {
        $payload = $this->validOrderPayload([
            'submission_id' => str_repeat('a', 32),
            'number_id' => '000001',
            'order_id' => 'CLIENT-ORDER',
            'bookshort' => 'BAD',
            'orderID' => 'BAD-ORDER',
            'orderIDShow' => 'BAD/ORDER',
        ]);
        $this->postOrder($payload)->assertRedirect();
        $row = $this->db->table('request_order')->where('customerFullname', 'VALID CUSTOMER')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('1', (string) $row['bookID']);
        self::assertSame('000001', (string) $row['numberID']);
        self::assertSame('ABC000001', (string) $row['orderID']);
        self::assertSame('ABC/000001', (string) $row['orderIDShow']);

        $this->postOrderAs($this->validOrderPayload([
            'submission_id' => str_repeat('4', 32), 'number_id' => '000002', 'customer_tel' => '5555555555',
        ]), $this->session(3, 3, 1))->assertRedirect();
        self::assertSame('ABC/000002', (string) $this->db->table('request_order')
            ->where('customerTel', '5555555555')->get()->getRow('orderIDShow'));

        $this->postOrderAs($this->validOrderPayload([
            'submission_id' => str_repeat('5', 32), 'number_id' => '000003', 'customer_tel' => '6666666666',
            'branch_id' => '2', 'book_id' => '3',
        ]), $this->session(1, 1, null))->assertRedirect();
        self::assertSame('XYZ/000003', (string) $this->db->table('request_order')
            ->where('customerTel', '6666666666')->get()->getRow('orderIDShow'));

        $max = str_repeat('7', 96);
        $this->postOrder($this->validOrderPayload([
            'submission_id' => str_repeat('b', 32), 'number_id' => $max, 'customer_tel' => '1111111111',
            'order_id' => ['ignored-array'],
        ]))->assertRedirect();
        self::assertSame($max, (string) $this->db->table('request_order')
            ->where('customerTel', '1111111111')->get()->getRow('numberID'));

        $this->postOrder($this->validOrderPayload([
            'submission_id' => str_repeat('c', 32), 'number_id' => str_repeat('8', 97),
        ]))->assertStatus(422);
        $this->assertCreateCounts(12, 4, 4);
    }

    public function testCreateOrderUsesCharacterLengthForCanonicalBookLabels(): void
    {
        $this->db->table('book')->insertBatch([
            ['book_id' => 5, 'branch_id' => 1, 'book_detail' => 'กขค', 'status' => 1],
            ['book_id' => 6, 'branch_id' => 1, 'book_detail' => 'กขคง', 'status' => 1],
        ]);

        $this->postOrder($this->validOrderPayload([
            'submission_id' => str_repeat('7', 32), 'book_id' => '5', 'number_id' => '0042',
            'customer_tel' => '7777777777',
        ]))->assertRedirect();
        $row = $this->db->table('request_order')->where('customerTel', '7777777777')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('5', (string) $row['bookID']);
        self::assertSame('0042', (string) $row['numberID']);
        self::assertSame('กขค0042', (string) $row['orderID']);
        self::assertSame('กขค/0042', (string) $row['orderIDShow']);

        $this->postOrder($this->validOrderPayload([
            'submission_id' => str_repeat('8', 32), 'book_id' => '6', 'number_id' => '0043',
            'customer_tel' => '8888888888',
        ]))->assertStatus(422);
        self::assertSame(0, $this->db->table('request_order')->where('customerTel', '8888888888')->countAllResults());
        $this->assertCreateCounts(9, 1, 1);
    }

    public function testCreateOrderRejectsMalformedOrInaccessibleBookAndNumberWithoutMetadataLeak(): void
    {
        $bookCases = [
            'missing' => null, 'empty' => '', 'zero' => '0', 'negative' => '-1', 'float' => '1.0',
            'exponent' => '1e0', 'array' => ['1'], 'whitespace' => ' 1 ', 'unknown' => '999', 'inactive' => '2',
            'cross-branch' => '3',
        ];
        foreach ($bookCases as $label => $bookId) {
            $payload = $this->validOrderPayload(['submission_id' => md5('book-' . $label)]);
            if ($bookId === null) {
                unset($payload['book_id']);
            } else {
                $payload['book_id'] = $bookId;
            }
            $response = $this->postOrder($payload);
            $response->assertStatus(422);
            self::assertSame(['error' => 'invalid_order'], json_decode($response->getJSON(), true, 8, JSON_THROW_ON_ERROR));
            $this->assertCreateCounts(8, 0, 0);
        }

        foreach ([
            'missing' => null, 'empty' => '', 'letter' => 'A1', 'slash' => '1/2',
            'sql' => "1' OR 1=1--", 'html' => '<b>1</b>', 'unicode' => '๑', 'whitespace' => ' 1 ',
        ] as $label => $numberId) {
            $payload = $this->validOrderPayload(['submission_id' => md5('number-' . $label)]);
            if ($numberId === null) {
                unset($payload['number_id']);
            } else {
                $payload['number_id'] = $numberId;
            }
            $this->postOrder($payload)->assertStatus(422);
            $this->assertCreateCounts(8, 0, 0);
        }

        $this->postOrderAs($this->validOrderPayload([
            'submission_id' => str_repeat('d', 32), 'branch_id' => '1', 'book_id' => '3',
        ]), $this->session(1, 1, null))->assertStatus(422);
        $this->postOrderAs($this->validOrderPayload([
            'submission_id' => str_repeat('6', 32), 'branch_id' => '1', 'book_id' => '3',
        ]), $this->session(3, 3, 1))->assertStatus(422);
        $this->assertCreateCounts(8, 0, 0);
    }

    public function testCreateOrderUsesGlobalDisplayAndTelephoneBusinessKey(): void
    {
        $first = $this->validOrderPayload([
            'submission_id' => str_repeat('e', 32), 'number_id' => '7701', 'customer_tel' => '2222222222',
        ]);
        $this->postOrder($first)->assertRedirect();
        $this->db->table('request_order')->where('orderIDShow', 'ABC/7701')->update(['action_status' => 8]);

        $this->postOrder([...$first, 'submission_id' => str_repeat('f', 32)])->assertStatus(409);
        $this->postOrder([...$first, 'submission_id' => str_repeat('1', 32), 'customer_tel' => '3333333333'])
            ->assertRedirect();
        $this->postOrderAs([...$first, 'submission_id' => str_repeat('2', 32), 'branch_id' => '2', 'book_id' => '4'],
            $this->session(1, 1, null))->assertStatus(409);

        self::assertSame(1, $this->db->table('request_order')
            ->where('orderIDShow', 'ABC/7701')->where('customerTel', '2222222222')->countAllResults());
        self::assertSame(1, $this->db->table('request_order')
            ->where('orderIDShow', 'ABC/7701')->where('customerTel', '3333333333')->countAllResults());
        $this->assertCreateCounts(10, 2, 2);
    }

    public function testCreateOrderRollsBackNonBusinessDatabaseFailureAndAllowsRetry(): void
    {
        $trigger = $this->db->escapeIdentifiers($this->db->prefixTable('fail_order_status_log'));
        $statusLog = $this->db->escapeIdentifiers($this->db->prefixTable('status_log'));
        $this->db->query("CREATE TRIGGER {$trigger} BEFORE INSERT ON {$statusLog} BEGIN SELECT RAISE(FAIL, 'UNIQUE constraint failed: unrelated_table.other'); END");
        $payload = $this->validOrderPayload([
            'submission_id' => str_repeat('3', 32), 'number_id' => '8801', 'customer_tel' => '4444444444',
        ]);
        try {
            $this->postOrder($payload)->assertStatus(503);
            $this->assertCreateCounts(8, 0, 0);
        } finally {
            $this->db->query("DROP TRIGGER IF EXISTS {$trigger}");
        }

        $this->postOrder($payload)->assertRedirect();
        $this->assertCreateCounts(9, 1, 1);
    }

    public function testCreateOrderCsrfFilterRejectsBeforeWorkflow(): void
    {
        try {
            $this->withSession($this->session(2, 2, 1))->post('/orders/new', $this->validOrderPayload());
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException) {
            $this->assertCreateCounts(8, 0, 0);
        }
    }

    public function testPreviewUploadRequiresAuthenticationAndCsrfBeforeValidation(): void
    {
        $png = $this->imageFixture('png');
        try {
            $this->setPreviewUpload($png, 'repair.png', 'image/png');
            $this->withSession([])->post('/order/do_upload_multi/' . str_repeat('a', 32), [
                'csrf_test_name' => service('security')->getHash(),
            ])->assertStatus(401);

            $this->setPreviewUpload($png, 'repair.png', 'image/png');
            try {
                $this->withSession($this->session(2, 2, 1))
                    ->post('/order/do_upload_multi/' . str_repeat('a', 32), []);
                self::fail('Expected CSRF rejection.');
            } catch (SecurityException) {
                self::assertSame([], $this->orderImagesOnDisk());
            }
        } finally {
            @unlink($png);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testPreviewUploadValidatesWithoutPersistingOrExposingStoredFilename(): void
    {
        $png = $this->imageFixture('png');
        $before = $this->orderImagesOnDisk();
        try {
            $this->setPreviewUpload($png, '../../repair.php.png', 'image/png');
            $response = $this->withSession($this->session(2, 2, 1))->post(
                '/order/do_upload_multi/' . str_repeat('b', 32),
                ['csrf_test_name' => service('security')->getHash()],
            );
            $response->assertStatus(200);
            $response->assertJSONFragment(['status' => 'success']);
            $json = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('csrf_hash', $json);
            self::assertArrayNotHasKey('filename', $json);
            self::assertArrayNotHasKey('path', $json);
            self::assertSame($before, $this->orderImagesOnDisk());
        } finally {
            @unlink($png);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testPreviewUploadRejectsInvalidTokenAndInvalidImageWithoutPersistence(): void
    {
        $bad = tempnam(sys_get_temp_dir(), 'tpl01-bad-preview-');
        self::assertIsString($bad);
        file_put_contents($bad, '<?php echo "bad";');
        try {
            foreach (['not-a-token', str_repeat('c', 32)] as $token) {
                $this->setPreviewUpload($bad, 'repair.png', 'image/png');
                $response = $this->withSession($this->session(2, 2, 1))->post(
                    '/order/do_upload_multi/' . $token,
                    ['csrf_test_name' => service('security')->getHash()],
                );
                $response->assertStatus(422);
                $response->assertJSONFragment(['status' => 'error']);
                self::assertSame([], $this->orderImagesOnDisk());
            }
        } finally {
            @unlink($bad);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreateOrderWritesOrderLogEncryptedSmsIntentAndSafeImageAtomically(): void
    {
        $png = $this->imageFixture('png');
        $this->setUploads([['tmp' => $png, 'name' => 'repair.png', 'type' => 'image/png']]);
        $payload = [
            'submission_id' => str_repeat('a', 32), 'number_id' => '1001', 'order_id' => 'ORDER-1001',
            'book_id' => '1', 'customer_name' => 'NEW CUSTOMER', 'customer_tel' => '0000000000',
            'customer_tel2' => '027619999',
            'customer_email' => 'customer@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $payload['csrf_test_name'] = service('security')->getHash();
        $created = $this->withSession($this->session(2, 2, 1))->post('/orders/new', $payload);
        $created->assertRedirect();
        self::assertMatchesRegularExpression('#/orders/new\?created=WPA[0-9]{8}\z#', $created->getRedirectUrl());

        $order = $this->db->table('request_order')->where('customerFullname', 'NEW CUSTOMER')->get()->getRowArray();
        self::assertNotNull($order);
        self::assertMatchesRegularExpression('/\AWPA[0-9]{8}\z/', (string) $order['trackID']);
        self::assertSame(1, (int) $order['action_status']);
        self::assertSame(1, (int) $order['branchID']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', (string) $order['detailImage']);
        self::assertFileExists(WRITEPATH . 'uploads/orders/' . $order['detailImage']);
        self::assertSame(1, $this->db->table('status_log')->where('order_id', $order['trackID'])->where('action_id', 1)->countAllResults());
        $intent = $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->get()->getRowArray();
        self::assertNotNull($intent);
        self::assertSame('pending', $intent['status']);
        self::assertStringNotContainsString('0000000000', (string) $intent['payload_ciphertext']);
        // AC-5: the alternate telephone is stored on the order but never reaches the SMS intent.
        self::assertSame('027619999', (string) $order['customerTel2']);
        self::assertStringNotContainsString('027619999', (string) $intent['payload_ciphertext']);
        @unlink(WRITEPATH . 'uploads/orders/' . $order['detailImage']);
        @unlink($png);
        service('superglobals')->setFilesArray([]);
    }

    public function testCreateOrderRejectsInvalidUploadReplayDuplicateAndCrossBranchWithoutPartialWrites(): void
    {
        $valid = [
            'submission_id' => str_repeat('b', 32), 'number_id' => '2001', 'order_id' => 'ORDER-2001',
            'book_id' => '1', 'customer_name' => 'VALID CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'customer@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $this->postOrder([...$valid, 'brand_id' => '999'])->assertStatus(422);
        $this->assertCreateCounts(8, 0, 0);

        $bad = tempnam(sys_get_temp_dir(), 'wp00c-bad-order-');
        self::assertIsString($bad);
        file_put_contents($bad, '<?php echo "bad";');
        $this->setUploads([['tmp' => $bad, 'name' => 'repair.png', 'type' => 'image/png']]);
        $this->postOrder($valid, false)->assertStatus(422);
        $this->assertCreateCounts(8, 0, 0);
        @unlink($bad);
        service('superglobals')->setFilesArray([]);

        $this->postOrder($valid)->assertRedirect();
        $this->assertCreateCounts(9, 1, 1);
        $this->postOrder($valid)->assertStatus(409);
        $this->assertCreateCounts(9, 1, 1);
        $this->postOrder([...$valid, 'submission_id' => str_repeat('c', 32)])->assertStatus(409);
        $this->assertCreateCounts(9, 1, 1);
        $this->postOrder([...$valid, 'submission_id' => str_repeat('d', 32), 'number_id' => '2002', 'branch_id' => '2'])
            ->assertStatus(422);
        $this->assertCreateCounts(9, 1, 1);
    }

    public function testCreateOrderFillsEveryCi3PrintBlockFromSubmittedFields(): void
    {
        $payload = [
            'submission_id' => str_repeat('e', 32), 'number_id' => '3001', 'order_id' => 'ORDER-3001',
            'book_id' => '1', 'customer_name' => 'FULL CUSTOMER', 'customer_tel' => '0000000000',
            'customer_tel2' => '027619999', 'customer_email' => 'full@example.invalid',
            'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_agent' => '1', 'detail_date_purchase' => '15/01/2026',
            'detail_sku_name' => 'CABIN SPINNER', 'waranty_type' => '1', 'detail_number_waranty' => 'WRT-777',
            'condition' => ['1', '2'], 'condition_other' => 'HANDLE CRACK',
            'estimateprice' => ['2'], 'estimateprice_other' => 'PRICE NOTE',
            'fixed' => ['1'], 'fixed_other' => 'FIXED NOTE',
            'detail_equipment' => 'CHARGER AND STRAP', 'create_by_user' => 'RECEIVER NAME',
        ];
        $this->postOrder($payload)->assertRedirect();

        $order = $this->db->table('request_order')->where('customerFullname', 'FULL CUSTOMER')->get()->getRowArray();
        self::assertNotNull($order);
        // Exact DB values pin the pipe-join order (mutation 1) and the column each catalogue writes (mutation 3).
        self::assertSame(1, (int) $order['detailAgent']);
        self::assertSame('027619999', (string) $order['customerTel2']);
        self::assertSame('2026-01-15 00:00:00', (string) $order['detailDatePurchase']);
        self::assertSame('CABIN SPINNER', (string) $order['detailSKUName']);
        self::assertSame(1, (int) $order['warantyType']);
        self::assertSame('WRT-777', (string) $order['detailNumberWaranty']);
        self::assertSame('1|2', (string) $order['detailCondition']);
        self::assertSame('HANDLE CRACK', (string) $order['detailConditionOther']);
        self::assertSame('2', (string) $order['detailEstimatePrice']);
        self::assertSame('1', (string) $order['detailFixed']);
        self::assertSame('CHARGER AND STRAP', (string) $order['detailEquipment']);
        self::assertSame('RECEIVER NAME', (string) $order['create_by_user']);

        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/' . (int) $order['request_id'] . '/print');
        $print->assertStatus(200);
        $body = $print->getBody();
        // AC-1: blocks 1, 8, 9, 12, 13, 14, 15, 16, 17, 19 render the submitted data instead of blanks.
        $print->assertSee('URGENT/ซ่อมด่วน');                              // block 1
        self::assertStringContainsString('027619999', $body);                // block 8
        self::assertStringContainsString('15/01/2026', $body);               // block 9
        self::assertStringContainsString('CABIN SPINNER', $body);            // block 12
        $print->assertSee('มี WRT-777');                                     // block 13
        self::assertStringContainsString('value="1" disabled checked', $body); // block 14 (condition id 1)
        self::assertStringContainsString('value="2" disabled checked', $body); // block 14 (condition id 2)
        self::assertStringContainsString('HANDLE CRACK', $body);             // block 14 (other)
        self::assertStringContainsString('PRICE NOTE', $body);               // block 15 (other)
        self::assertStringContainsString('FIXED NOTE', $body);               // block 16 (other)
        self::assertStringContainsString('CHARGER AND STRAP', $body);        // block 17
        self::assertStringContainsString('RECEIVER NAME', $body);            // block 19
    }

    public function testCreateOrderDefaultsOptionalFieldsWhenOmitted(): void
    {
        $payload = [
            'submission_id' => str_repeat('f', 32), 'number_id' => '4001', 'order_id' => 'ORDER-4001',
            'book_id' => '1', 'customer_name' => 'MINIMAL CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => '', 'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => '',
            'detail_sku_name' => 'PLAIN BAG', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $this->postOrder($payload)->assertRedirect();

        $order = $this->db->table('request_order')->where('customerFullname', 'MINIMAL CUSTOMER')->get()->getRowArray();
        self::assertNotNull($order);
        // Every omitted optional field takes its documented default (detail_agent/waranty off, zero date, blanks).
        self::assertSame(0, (int) $order['detailAgent']);
        self::assertSame(0, (int) $order['warantyType']);
        self::assertSame('', (string) $order['detailNumberWaranty']);
        self::assertSame('0000-00-00 00:00:00', (string) $order['detailDatePurchase']);
        self::assertSame('', (string) $order['customerTel2']);
        self::assertSame('', (string) $order['detailConditionOther']);
        // AC-7: no image attached leaves detailImage empty (stored NULL, print renders no <img>).
        self::assertNull($order['detailImage']);
    }

    public function testCreateOrderBlanksWarantyNumberWhenTypeIsZero(): void
    {
        $payload = [
            'submission_id' => str_repeat('1', 32), 'number_id' => '4101', 'order_id' => 'ORDER-4101',
            'book_id' => '1', 'customer_name' => 'WARANTY ZERO CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => '', 'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => '',
            'detail_sku_name' => 'PLAIN BAG', 'create_by_user' => 'RECEIVER NAME',
            'waranty_type' => '0', 'detail_number_waranty' => 'SHOULD-VANISH',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $this->postOrder($payload)->assertRedirect();

        $order = $this->db->table('request_order')->where('customerFullname', 'WARANTY ZERO CUSTOMER')->get()->getRowArray();
        self::assertNotNull($order);
        // AC-4: waranty type 0 blanks the number even though a value was submitted; print then reads "ไม่มี".
        self::assertSame('', (string) $order['detailNumberWaranty']);

        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/' . (int) $order['request_id'] . '/print');
        $print->assertStatus(200);
        $print->assertSee('ไม่มี');
        self::assertStringNotContainsString('SHOULD-VANISH', $print->getBody());
    }

    public function testCreateOrderRejectsAdversarialFieldPayloadsWithoutWriting(): void
    {
        // Cross-table row 2: a fixed id absent from the condition catalogue must still fail condition lookup.
        $this->db->table('fixed')->insert(['fixed_id' => 7, 'fixed_details' => 'FIXED SEVEN']);
        $base = [
            'submission_id' => str_repeat('a', 32), 'number_id' => '5001', 'order_id' => 'ORDER-5001',
            'book_id' => '1', 'customer_name' => 'ADV CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'adv@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        // null override means "drop the key" (unsent field); every row must answer 422 with no new row.
        foreach ([
            'condition unknown id (row 1)' => ['condition' => ['99999']],
            'condition cross-table id (row 2)' => ['condition' => ['7']],
            'condition duplicate ids (row 3)' => ['condition' => ['1', '1']],
            'condition empty (row 4)' => ['condition' => []],
            'condition missing (row 4)' => ['condition' => null],
            'estimateprice empty' => ['estimateprice' => []],
            'estimateprice missing' => ['estimateprice' => null],
            'estimateprice unknown id' => ['estimateprice' => ['99999']],
            'fixed empty' => ['fixed' => []],
            'fixed missing' => ['fixed' => null],
            'fixed unknown id' => ['fixed' => ['99999']],
            'sku empty (row 5)' => ['detail_sku_name' => ''],
            'sku over 100 (row 5)' => ['detail_sku_name' => str_repeat('x', 101)],
            'tel2 alpha (row 6)' => ['customer_tel2' => 'abc'],
            'tel2 dashes (row 6)' => ['customer_tel2' => '08-1234-5678'],
            'purchase impossible day (row 8)' => ['detail_date_purchase' => '31/02/2026'],
            'purchase iso format (row 8)' => ['detail_date_purchase' => '2026-01-15'],
            'purchase short year (row 8)' => ['detail_date_purchase' => '1/1/26'],
            'waranty out of set 2 (row 10)' => ['waranty_type' => '2'],
            'waranty out of set x (row 10)' => ['waranty_type' => 'x'],
            'condition_other over 250 (row 11)' => ['condition_other' => str_repeat('x', 251)],
            'create_by empty (row 12)' => ['create_by_user' => ''],
            'create_by missing (row 12)' => ['create_by_user' => null],
        ] as $label => $override) {
            $payload = $base;
            $payload['submission_id'] = md5($label);
            foreach ($override as $key => $value) {
                if ($value === null) {
                    unset($payload[$key]);
                } else {
                    $payload[$key] = $value;
                }
            }
            $this->postOrder($payload)->assertStatus(422);
            $this->assertCreateCounts(8, 0, 0);
        }
    }

    public function testNewOrderFormExposesCatalogueCheckboxesAndCi3FieldNames(): void
    {
        $body = $this->withSession($this->session(2, 2, 1))->get('/orders/new')->getBody();
        // The controller wires the three catalogues into the form; source names map in Order::orderInput().
        self::assertStringContainsString('name="condition[]"', $body);
        self::assertStringContainsString('name="estimateprice[]"', $body);
        self::assertStringContainsString('name="fixed[]"', $body);
        self::assertStringContainsString('CONDITION ONE', $body);
        self::assertStringContainsString('name="detailSKUName"', $body);
        self::assertStringContainsString('name="warantyType"', $body);
        self::assertStringContainsString('name="create_by_user"', $body);
        // CI3 delegates repair-image uploads to the dedicated jQuery upload form.
        self::assertStringContainsString('id="upload"', $body);
        self::assertStringContainsString('name="upl"', $body);
        self::assertStringNotContainsString('name="detail_image[]"', $body);
    }

    public function testNormalLifecycleWritesExactProviderDatesStatusesAndLogs(): void
    {
        $this->postTransition('/sendorderUpdate', ['select_list_id' => ['91001'], 'provider_id' => '1'])
            ->assertRedirectTo('/sendorderListing');
        $status2 = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame(2, (int) $status2['action_status']);
        self::assertSame(1, (int) $status2['provider_id']);
        self::assertNotEmpty($status2['date_create']);
        self::assertNotEmpty($status2['date_update_status']);

        $trackingUpdated = $this->postTransition(
            '/sendorderUpdateStatus',
            ['select_list_id' => ['91002'], 'status_id' => '3'],
        );
        $trackingUpdated->assertStatus(303);
        $trackingUpdated->assertRedirectTo('/ReportTrackingListing');
        self::assertSame('order updated successfully', service('session')->getFlashdata('success'));
        $status3 = $this->db->table('request_order')->where('request_id', 91002)->get()->getRowArray();
        self::assertSame(3, (int) $status3['action_status']);
        self::assertNotEmpty($status3['date_update_status']);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91003'], 'status_id' => '4'])
            ->assertRedirectTo('/ReportTrackingListing');
        self::assertSame(4, (int) $this->db->table('request_order')->where('request_id', 91003)->get()->getRow('action_status'));

        $delivered = $this->postTransition(
            '/sendorder_deliver',
            ['select_list_id' => ['91004'], 'status_id' => '5'],
        );
        $delivered->assertStatus(303);
        $delivered->assertRedirectTo('/TrackingreturnListing');
        self::assertSame('order updated successfully', service('session')->getFlashdata('success'));
        $status5 = $this->db->table('request_order')->where('request_id', 91004)->get()->getRowArray();
        self::assertSame(5, (int) $status5['action_status']);
        self::assertNotEmpty($status5['date_deliver']);
        self::assertNotEmpty($status5['date_update_status']);
        self::assertSame(4, $this->db->table('status_log')->countAllResults());
        self::assertSame([2, 3, 4, 5], array_map(
            'intval',
            array_column($this->db->table('status_log')->orderBy('id', 'ASC')->get()->getResultArray(), 'action_id'),
        ));
    }

    public function testDeliverTransitionQueuesReturnSmsAndDedupesOnReplay(): void
    {
        // Seed a status 4 order with a guard-conforming trackID and telephone; the WP00C seed
        // trackIDs contain hyphens and would be skipped by enqueue's trackID guard.
        $this->db->table('request_order')->insert([
            'request_id' => 92004, 'requestDate' => '2026-08-04 00:00:00',
            'trackID' => 'WPA26080044', 'orderID' => 'OD4', 'orderIDShow' => 'WPC/D4',
            'customerFullname' => 'DELIVER CUSTOMER', 'customerTel' => '0000000000',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 4,
        ]);

        $this->postTransition('/sendorder_deliver', ['select_list_id' => ['92004'], 'status_id' => '5'])
            ->assertRedirectTo('/TrackingreturnListing');
        self::assertSame(5, (int) $this->db->table('request_order')->where('request_id', 92004)->get()->getRow('action_status'));

        $intent = $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->get()->getRowArray();
        self::assertNotNull($intent);
        self::assertSame('pending', $intent['status']);
        self::assertSame(92004, (int) $intent['user_id']);
        self::assertSame(md5('sms-return:92004'), $intent['request_id']);
        self::assertStringNotContainsString('0000000000', (string) $intent['payload_ciphertext']);
        $payload = json_decode(service('encrypter')->decrypt(base64_decode((string) $intent['payload_ciphertext'], true)), true);
        self::assertStringContainsString('ส่งคืนมายังสาขาแล้ว', $payload['message']);
        self::assertStringContainsString('WPA26080044', $payload['message']);

        // Replay: order is already status 5, so the matrix rejects 4->5 (409) and intent count holds.
        $this->postTransition('/sendorder_deliver', ['select_list_id' => ['92004'], 'status_id' => '5'])
            ->assertStatus(409);
        self::assertSame(1, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testDeliverWithMalformedTelSucceedsWithoutQueuingSms(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 92005, 'requestDate' => '2026-08-05 00:00:00',
            'trackID' => 'WPA26080045', 'orderID' => 'OD5', 'orderIDShow' => 'WPC/D5',
            'customerFullname' => 'LEGACY TEL CUSTOMER', 'customerTel' => '08-1234-5678',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 4,
        ]);

        $this->postTransition('/sendorder_deliver', ['select_list_id' => ['92005'], 'status_id' => '5'])
            ->assertRedirectTo('/TrackingreturnListing');
        self::assertSame(5, (int) $this->db->table('request_order')->where('request_id', 92005)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testNonDeliverTransitionDoesNotQueueSms(): void
    {
        // Conforming trackID + telephone: the only thing that can suppress the intent here is the
        // deliver-mode gate, so a green assertion of 0 intents proves the gate discriminates.
        $this->db->table('request_order')->insert([
            'request_id' => 92006, 'requestDate' => '2026-08-06 00:00:00',
            'trackID' => 'WPA26080046', 'orderID' => 'OD6', 'orderIDShow' => 'WPC/D6',
            'customerFullname' => 'STATUS MODE CUSTOMER', 'customerTel' => '0000000000',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 2,
        ]);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['92006'], 'status_id' => '3'])
            ->assertRedirectTo('/ReportTrackingListing');
        self::assertSame(3, (int) $this->db->table('request_order')->where('request_id', 92006)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testCompleteTransitionWritesCompletionColumnsAndQueuesCompletionSms(): void
    {
        // Guard-conforming trackID + telephone so the completion SMS clears enqueue's guards; the
        // WP00C seed trackIDs contain hyphens and would be skipped.
        $this->db->table('request_order')->insert([
            'request_id' => 92007, 'requestDate' => '2026-08-05 00:00:00',
            'trackID' => 'WPA26080057', 'orderID' => 'OC7', 'orderIDShow' => 'WPC/C7',
            'customerFullname' => 'COMPLETE CUSTOMER', 'customerTel' => '0000000000',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 5,
        ]);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['92007'], 'status_id' => '7'])
            ->assertRedirectTo('/ReportTrackingListing');

        $row = $this->db->table('request_order')->where('request_id', 92007)->get()->getRowArray();
        self::assertSame(7, (int) $row['action_status']);
        self::assertNotEmpty($row['date_complete']);
        self::assertNotEmpty($row['date_update_status']);
        self::assertSame(1, $this->db->table('status_log')->where('order_id', 'WPA26080057')->where('action_id', 7)->countAllResults());

        // AC-2: one completion intent carrying the verbatim CI3 copy (double space after Samsonite)
        // and the rating link ending in the trackID; the telephone never lands in the ciphertext.
        $intent = $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->get()->getRowArray();
        self::assertNotNull($intent);
        self::assertSame('pending', $intent['status']);
        self::assertSame(92007, (int) $intent['user_id']);
        self::assertSame(md5('sms-complete:92007'), $intent['request_id']);
        self::assertStringNotContainsString('0000000000', (string) $intent['payload_ciphertext']);
        $payload = json_decode(service('encrypter')->decrypt(base64_decode((string) $intent['payload_ciphertext'], true)), true);
        self::assertStringContainsString('ขอบคุณที่ใช้บริการกับ Samsonite  แสดงความคิดเห็น', $payload['message']);
        self::assertStringContainsString('/rating/WPA26080057', $payload['message']);
    }

    public function testCompleteListingExposesCi3PerRowRatingModalWithoutBulkForm(): void
    {
        $body = (string) $this->withSession($this->session(2, 2, 1))->get('/TrackingcompleteListing')->getBody();
        self::assertStringNotContainsString('action="http://example.invalid/sendorderUpdateStatus"', $body);
        self::assertStringNotContainsString('name="select_list_id[]"', $body);
        self::assertStringContainsString('onclick="openModal(', $body);
        self::assertStringContainsString('>ประเมิน</a>', html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        self::assertStringContainsString('<div id="modal_rating" class="modal fade modal-rating"', $body);
        self::assertStringContainsString("url: base_url + 'rating/addRating'", $body);
    }

    public function testCompleteRejectsNonSevenTargetsWithoutWritingOrQueuing(): void
    {
        foreach (['6', '8'] as $target) {
            $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91005'], 'status_id' => $target])
                ->assertStatus(409);
        }
        $row = $this->db->table('request_order')->where('request_id', 91005)->get()->getRowArray();
        self::assertSame(5, (int) $row['action_status']);
        self::assertEmpty($row['date_complete']);
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testCompleteWithMalformedTelSucceedsWithoutQueuingSms(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 92008, 'requestDate' => '2026-08-05 00:00:00',
            'trackID' => 'WPA26080058', 'orderID' => 'OC8', 'orderIDShow' => 'WPC/C8',
            'customerFullname' => 'COMPLETE LEGACY TEL', 'customerTel' => '08-1234-5678',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 5,
        ]);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['92008'], 'status_id' => '7'])
            ->assertRedirectTo('/ReportTrackingListing');
        self::assertSame(7, (int) $this->db->table('request_order')->where('request_id', 92008)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testInvalidDirectAndMixedBatchTransitionsRollbackEveryRow(): void
    {
        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91002'], 'status_id' => '7'])
            ->assertStatus(409);
        self::assertSame(2, (int) $this->db->table('request_order')->where('request_id', 91002)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());

        $this->postTransition('/sendorderUpdateStatus', [
            'select_list_id' => ['91002', '91003'], 'status_id' => '3',
        ])->assertStatus(409);
        self::assertSame(2, (int) $this->db->table('request_order')->where('request_id', 91002)->get()->getRow('action_status'));
        self::assertSame(3, (int) $this->db->table('request_order')->where('request_id', 91003)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91007'], 'status_id' => '4'], $this->session(2, 2, 1))
            ->assertStatus(404);
        $emptySelection = $this->postTransition(
            '/sendorderUpdateStatus',
            ['select_list_id' => [], 'status_id' => '3'],
        );
        $emptySelection->assertStatus(303);
        $emptySelection->assertRedirectTo('/ReportTrackingListing');
        self::assertSame('order updated failed', service('session')->getFlashdata('error'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testPrintEditAndSoftDeletePreserveStatusLogAndDenyCrossBranch(): void
    {
        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/91001/print');
        $print->assertStatus(200);
        $print->assertSee('WP00C-TRACK-001');
        $print->assertSee('CUSTOMER 1');

        service('superglobals')->setFilesArray([]);
        $edit = [
            'customer_name' => 'EDITED CUSTOMER', 'customer_tel' => '1111111111',
            'customer_email' => 'edited@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'note' => 'Edited note', 'action_status' => '7', 'trackID' => 'TAMPERED',
            'bookID' => '999', 'numberID' => 'TAMPERED', 'orderID' => 'TAMPERED',
            'orderIDShow' => 'TAMPERED/1', 'branchID' => '2',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $edit['csrf_test_name'] = service('security')->getHash();
        $this->withSession($this->session(2, 2, 1))->post('/orders/91001', $edit)
            ->assertRedirectTo('/orders?status=1');
        $row = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame('EDITED CUSTOMER', $row['customerFullname']);
        self::assertSame('WP00C-TRACK-001', (string) $row['trackID']);
        self::assertSame('1', (string) $row['bookID']);
        self::assertSame('N1', (string) $row['numberID']);
        self::assertSame('O1', (string) $row['orderID']);
        self::assertSame('WPC/1', (string) $row['orderIDShow']);
        self::assertSame(1, (int) $row['branchID']);
        // AC-6: action_status=7 in the post never changes the status (edit leaves the column out).
        self::assertSame(1, (int) $row['action_status']);
        self::assertSame(0, $this->db->table('status_log')->countAllResults());

        try {
            $this->withSession($this->session(2, 2, 1))->get('/orders/91007/print');
            self::fail('Expected cross-branch print denial.');
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
        }
        $edit['csrf_test_name'] = service('security')->getHash();
        $this->withSession($this->session(2, 2, 1))->post('/orders/91007', $edit)->assertStatus(404);
        self::assertSame('CUSTOMER 7', $this->db->table('request_order')->where('request_id', 91007)->get()->getRow('customerFullname'));

        $this->postTransition('/orders/91001/delete', [], $this->session(2, 2, 1))->assertStatus(204);
        self::assertSame(8, (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testPrintRendersFullCi3FormFieldsWithoutStatusLabel(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 92010, 'requestDate' => '2026-08-10 00:00:00',
            'trackID' => 'WPA26080110', 'orderID' => 'O110', 'orderIDShow' => 'WPC/110',
            'customerFullname' => 'PRINT CUSTOMER', 'customerTel' => '0000000000',
            'customerTel2' => '027619999', 'customerEmail' => 'print@example.invalid',
            'detailAgent' => '1', 'detailTypeId' => 1, 'detailBrandId' => 1,
            'detailDatePurchase' => '0000-00-00 00:00:00', 'detailSKUName' => 'BAG <SPORT>',
            'detailNumberWaranty' => 'WRT-123',
            'detailCondition' => '1|2', 'detailConditionOther' => 'EXTRA HANDLE CRACK',
            'detailEstimatePrice' => '2', 'detailFixed' => '1',
            'detailEquipment' => 'CHARGER AND STRAP', 'detailNote' => 'PRINT NOTE',
            'detailImage' => str_repeat('e', 32) . '.png|legacy.jpg',
            'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 3,
            'create_by_user' => '9002',
        ]);

        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/92010/print');
        $print->assertStatus(200);
        $body = $print->getBody();

        // AC-1: CI3 labels and the URGENT banner (detailAgent=1). Thai text is asserted through the DOM
        // parser (assertSee) because the rendered body carries Thai as numeric HTML entities.
        $print->assertSee('TRACK ID/เลขติดตาม');
        $print->assertSee('URGENT/ซ่อมด่วน');
        self::assertStringContainsString('WPA26080110', $body);
        // Master-resolved brand/type names plus a condition label from the catalogue.
        self::assertStringContainsString('BRAND A', $body);
        self::assertStringContainsString('TYPE A', $body);
        self::assertStringContainsString('CONDITION ONE', $body);

        // AC-2: pipe-separated ids 1 and 2 are checked, id 3 is not, and the "other" free text shows.
        self::assertStringContainsString('id="condition[]" name="condition[]" value="1" disabled checked', $body);
        self::assertStringContainsString('id="condition[]" name="condition[]" value="2" disabled checked', $body);
        self::assertStringContainsString('value="3" disabled>', $body);
        self::assertStringContainsString('EXTRA HANDLE CRACK', $body);

        // CI3 print_order.php renders every pipe-delimited stored name under uploads/.
        self::assertStringContainsString('src="http://example.invalid/uploads/' . str_repeat('e', 32) . '.png"', $body);
        self::assertStringContainsString('src="http://example.invalid/uploads/legacy.jpg"', $body);

        // AC-1: the CI3 form never printed a status label, so the CI4 view must not either.
        self::assertStringNotContainsString('Status', $body);

        // CI3 leaves the third cell of the TRACK/ORDER/REQUEST row empty; its request date is
        // computed but never output. Keep that observable legacy defect until a disposition exists.
        self::assertStringContainsString('00/00/0000', $body);
        self::assertStringContainsString('10/08/2026', $body);

        // Block 15 reads detailNumberWaranty directly (not warantyType).
        $print->assertSee('มี WRT-123');

        // AC-5: DB values pass through esc(); the A4 page and self-hiding print button are present.
        self::assertStringContainsString('BAG &lt;SPORT&gt;', $body);
        self::assertStringNotContainsString('BAG <SPORT>', $body);
        self::assertStringContainsString('size: A4', $body);
        self::assertStringContainsString('class="sheet padding-10mm"', $body);
        self::assertStringContainsString('class="size-print"', $body);
        self::assertStringContainsString('src="http://example.invalid/assets/images/print-logo.jpg"', $body);
        self::assertStringContainsString('function printpr()', $body);
        self::assertStringContainsString('onclick="JavaScript:this.style.display=', $body);
        self::assertStringContainsString('window.print()', $body);
    }

    public function testOrderImageServesPngForAuthedUserAndRejectsAdversarialNames(): void
    {
        $directory = WRITEPATH . 'uploads/orders';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $name = str_repeat('a', 32) . '.png';
        $path = $directory . '/' . $name;
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $session = $this->session(2, 2, 1);

        try {
            // AC-4: an authenticated read serves the PNG bytes.
            $served = $this->withSession($session)->get('/order-image/' . $name);
            $served->assertStatus(200);
            $served->assertHeader('Content-Type', 'image/png');

            // AC-3 adversarial rows 2-5: wrong extension, wrong length, uppercase, and trailing-byte
            // smuggling are all rejected by the anchored [a-f0-9]{32}\.png regex before any file read.
            foreach ([
                str_repeat('a', 32) . '.php',
                str_repeat('a', 31) . '.png',
                str_repeat('a', 33) . '.png',
                str_repeat('A', 32) . '.png',
                str_repeat('a', 32) . '.png.jpg',
            ] as $bad) {
                $this->withSession($session)->get('/order-image/' . $bad)->assertStatus(404);
            }

            // AC-3 adversarial row 1: a slash-bearing traversal never matches (:segment); the router
            // answers 404 before the controller (assertStatus response or PageNotFoundException).
            try {
                $this->withSession($session)->get('/order-image/..%2f..%2fetc%2fpasswd')->assertStatus(404);
            } catch (PageNotFoundException $exception) {
                self::assertSame(404, $exception->getCode());
            }

            // AC-3: a well-formed name with no file on disk is still 404.
            $this->withSession($session)->get('/order-image/' . str_repeat('b', 32) . '.png')->assertStatus(404);

            // AC-3 adversarial row 6 + gate is load-bearing: an unauthenticated request (empty session)
            // is answered by web-auth with 401 even though the PNG exists on disk (never reaches file).
            $this->withSession([])->get('/order-image/' . $name)->assertStatus(401);
        } finally {
            @unlink($path);
        }
    }

    public function testCreateOrderStoresMultipleImagesConvertedToPngAndPrintsEachOne(): void
    {
        $png = $this->imageFixture('png');
        $jpg = $this->imageFixture('jpeg');
        $gif = $this->imageFixture('gif');
        $this->setUploads([
            ['tmp' => $png, 'name' => 'a.png', 'type' => 'image/png'],
            ['tmp' => $jpg, 'name' => 'b.jpg', 'type' => 'image/jpeg'],
            ['tmp' => $gif, 'name' => 'c.gif', 'type' => 'image/gif'],
        ]);
        $payload = [
            'submission_id' => str_repeat('7', 32), 'number_id' => '6001', 'order_id' => 'ORDER-6001',
            'book_id' => '1', 'customer_name' => 'IMAGE CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'img@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $this->postOrder($payload, false)->assertRedirect();

        $order = $this->db->table('request_order')->where('customerFullname', 'IMAGE CUSTOMER')->get()->getRowArray();
        self::assertNotNull($order);
        // AC-1: three pipe-joined 32hex.png names in the order they were attached.
        $names = explode('|', (string) $order['detailImage']);
        self::assertCount(3, $names);
        try {
            foreach ($names as $name) {
                self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $name);
                $path = WRITEPATH . 'uploads/orders/' . $name;
                self::assertFileExists($path);
                // AC-1 + mutation 2: every stored file is a real PNG, not the original jpeg/gif bytes.
                $info = getimagesize($path);
                self::assertIsArray($info);
                self::assertSame('image/png', $info['mime']);
            }

            // AC-2: print emits one /order-image/<name> img per stored file and nothing else.
            $print = $this->withSession($this->session(2, 2, 1))->get('/orders/' . (int) $order['request_id'] . '/print');
            $print->assertStatus(200);
            $body = $print->getBody();
            foreach ($names as $name) {
                self::assertStringContainsString('src="http://example.invalid/uploads/' . $name . '"', $body);
            }
            self::assertSame(3, substr_count($body, 'src="http://example.invalid/uploads/'));
        } finally {
            foreach ($names as $name) {
                @unlink(WRITEPATH . 'uploads/orders/' . $name);
            }
            @unlink($png);
            @unlink($jpg);
            @unlink($gif);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreateOrderAcceptsImageByContentIgnoringClientExtension(): void
    {
        // Adversarial row 2: a real PNG announced as text/.txt is accepted on its bytes, not its name.
        $png = $this->imageFixture('png');
        $this->setUploads([['tmp' => $png, 'name' => 'note.txt', 'type' => 'text/plain']]);
        $payload = [
            'submission_id' => str_repeat('8', 32), 'number_id' => '6101', 'order_id' => 'ORDER-6101',
            'book_id' => '1', 'customer_name' => 'EXT CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'ext@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $stored = null;
        try {
            $this->postOrder($payload, false)->assertRedirect();
            $order = $this->db->table('request_order')->where('customerFullname', 'EXT CUSTOMER')->get()->getRowArray();
            self::assertNotNull($order);
            $stored = (string) $order['detailImage'];
            self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $stored);
            self::assertFileExists(WRITEPATH . 'uploads/orders/' . $stored);
        } finally {
            if ($stored !== null) {
                @unlink(WRITEPATH . 'uploads/orders/' . $stored);
            }
            @unlink($png);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreateOrderRemovesEarlierImagesWhenALaterFileFails(): void
    {
        // AC-3: two files store successfully, the third fails conversion; both stored files are removed
        // (mutation 1: a removeAll that only deletes the first name leaves the second on disk).
        $png1    = $this->imageFixture('png');
        $png2    = $this->imageFixture('png');
        $corrupt = $this->corruptPngFixture();
        $this->setUploads([
            ['tmp' => $png1, 'name' => 'a.png', 'type' => 'image/png'],
            ['tmp' => $png2, 'name' => 'b.png', 'type' => 'image/png'],
            ['tmp' => $corrupt, 'name' => 'c.png', 'type' => 'image/png'],
        ]);
        $payload = [
            'submission_id' => str_repeat('9', 32), 'number_id' => '6201', 'order_id' => 'ORDER-6201',
            'book_id' => '1', 'customer_name' => 'ROLLBACK CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'rollback@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $before = $this->orderImagesOnDisk();
        try {
            $this->postOrder($payload, false)->assertStatus(422);
            $this->assertCreateCounts(8, 0, 0);
            $after = $this->orderImagesOnDisk();
            sort($before);
            sort($after);
            self::assertSame($before, $after);
        } finally {
            @unlink($png1);
            @unlink($png2);
            @unlink($corrupt);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreateOrderRemovesAllImagesWhenWorkflowRejectsAfterUpload(): void
    {
        // AC-4: three valid images store in Phase A, then the workflow rejects the payload (a required
        // T1 field is missing); every stored file is removed and no order row is written.
        $png = $this->imageFixture('png');
        $jpg = $this->imageFixture('jpeg');
        $gif = $this->imageFixture('gif');
        $this->setUploads([
            ['tmp' => $png, 'name' => 'a.png', 'type' => 'image/png'],
            ['tmp' => $jpg, 'name' => 'b.jpg', 'type' => 'image/jpeg'],
            ['tmp' => $gif, 'name' => 'c.gif', 'type' => 'image/gif'],
        ]);
        $payload = [
            'submission_id' => str_repeat('0', 32), 'number_id' => '6301', 'order_id' => 'ORDER-6301',
            'book_id' => '1', 'customer_name' => 'WORKFLOW FAIL CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'wf@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        // detail_sku_name intentionally omitted so the workflow throws after the images are on disk.
        $before = $this->orderImagesOnDisk();
        try {
            $this->postOrder($payload, false)->assertStatus(422);
            $this->assertCreateCounts(8, 0, 0);
            $after = $this->orderImagesOnDisk();
            sort($before);
            sort($after);
            self::assertSame($before, $after);
        } finally {
            @unlink($png);
            @unlink($jpg);
            @unlink($gif);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreateOrderRejectsAdversarialImageUploadsLeavingDiskClean(): void
    {
        $base = [
            'submission_id' => str_repeat('a', 32), 'number_id' => '7000', 'order_id' => 'ORDER-7000',
            'book_id' => '1', 'customer_name' => 'ADV IMG CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'advimg@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
            'detail_sku_name' => 'BAG SPORT', 'create_by_user' => 'RECEIVER NAME',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $text = tempnam(sys_get_temp_dir(), 'wp04d-text-');
        self::assertIsString($text);
        file_put_contents($text, 'this is plain text, not an image');
        $corrupt = $this->corruptPngFixture();
        $big     = $this->imageFixture('png');
        $wide    = $this->imageFixture('png', 5000, 100);
        $six     = [];
        for ($i = 0; $i < 6; $i++) {
            $six[] = $this->imageFixture('png');
        }
        $webp = tempnam(sys_get_temp_dir(), 'wp04d-webp-');
        self::assertIsString($webp);
        file_put_contents($webp, "RIFF\x1a\x00\x00\x00WEBPVP8 " . str_repeat("\x00", 10));
        $bmp = tempnam(sys_get_temp_dir(), 'wp04d-bmp-');
        self::assertIsString($bmp);
        file_put_contents($bmp, 'BM' . str_repeat("\x00", 62));
        $svg = tempnam(sys_get_temp_dir(), 'wp04d-svg-');
        self::assertIsString($svg);
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');

        // Every filename claims .png/image-png so only the file content can drive the rejection.
        $cases = [
            'text bytes named png (row 1)'  => [['tmp' => $text, 'name' => 'x.png', 'type' => 'image/png']],
            'corrupt png body (row 3)'      => [['tmp' => $corrupt, 'name' => 'x.png', 'type' => 'image/png']],
            'over 2MB (row 4)'              => [['tmp' => $big, 'name' => 'x.png', 'type' => 'image/png', 'size' => 2_097_153]],
            'oversized dimensions (row 5)'  => [['tmp' => $wide, 'name' => 'x.png', 'type' => 'image/png']],
            'sixth file over the cap (row 6)' => array_map(
                static fn (string $p): array => ['tmp' => $p, 'name' => 'x.png', 'type' => 'image/png'],
                $six,
            ),
            'webp disguised as png (row 7)' => [['tmp' => $webp, 'name' => 'x.png', 'type' => 'image/png']],
            'bmp disguised as png (row 7)'  => [['tmp' => $bmp, 'name' => 'x.png', 'type' => 'image/png']],
            'svg disguised as png (row 7)'  => [['tmp' => $svg, 'name' => 'x.png', 'type' => 'image/png']],
        ];
        try {
            $index = 0;
            foreach ($cases as $label => $specs) {
                $this->setUploads($specs);
                $payload                  = $base;
                $payload['submission_id'] = md5($label);
                $payload['number_id']     = '72' . $index;
                $before                   = $this->orderImagesOnDisk();
                // AC-5 (row 6) + AC-6: every disguised or oversized upload is 422 with no file left behind.
                $this->postOrder($payload, false)->assertStatus(422);
                $this->assertCreateCounts(8, 0, 0);
                $after = $this->orderImagesOnDisk();
                sort($before);
                sort($after);
                self::assertSame($before, $after, $label);
                $index++;
            }
        } finally {
            foreach ([$text, $corrupt, $big, $wide, $webp, $bmp, $svg, ...$six] as $tmp) {
                @unlink($tmp);
            }
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testCreatedOrderReceiptFillsEveryDataDrivenBlockIncludingImage(): void
    {
        // AC-1 (closes the WP): one create->print roundtrip with every field and an image populated
        // must leave no receipt block blank by accident.
        $png = $this->imageFixture('png');
        $this->setUploads([['tmp' => $png, 'name' => 'a.png', 'type' => 'image/png']]);
        $payload = [
            'submission_id' => str_repeat('2', 32), 'number_id' => '8001', 'order_id' => 'ORDER-8001',
            'book_id' => '1', 'customer_name' => 'RECEIPT CUSTOMER', 'customer_tel' => '0000000000',
            'customer_tel2' => '027619999', 'customer_email' => 'receipt@example.invalid',
            'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => 'RECEIPT NOTE',
            'detail_agent' => '1', 'detail_date_purchase' => '15/01/2026',
            'detail_sku_name' => 'CABIN SPINNER', 'waranty_type' => '1', 'detail_number_waranty' => 'WRT-777',
            'condition' => ['1', '2'], 'condition_other' => 'HANDLE CRACK',
            'estimateprice' => ['2'], 'estimateprice_other' => 'PRICE NOTE',
            'fixed' => ['1'], 'fixed_other' => 'FIXED NOTE',
            'detail_equipment' => 'CHARGER AND STRAP', 'create_by_user' => 'RECEIVER NAME',
        ];
        try {
            $this->postOrder($payload, false)->assertRedirect();
            $order = $this->db->table('request_order')->where('customerFullname', 'RECEIPT CUSTOMER')->get()->getRowArray();
            self::assertNotNull($order);
            $image = (string) $order['detailImage'];
            self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $image);

            $print = $this->withSession($this->session(2, 2, 1))->get('/orders/' . (int) $order['request_id'] . '/print');
            $print->assertStatus(200);
            $body = $print->getBody();
            // Always-present blocks (2, 3, 4, 5, 6, 7, 10, 11, 18, 21) driven by the row + master data.
            self::assertStringContainsString((string) $order['trackID'], $body);         // block 2
            self::assertStringContainsString('ABC/8001', $body);                          // block 3 (orderIDShow)
            self::assertStringContainsString('RECEIPT CUSTOMER', $body);                  // block 5
            self::assertStringContainsString('receipt@example.invalid', $body);           // block 6
            self::assertStringContainsString('0000000000', $body);                        // block 7
            self::assertStringContainsString('TYPE A', $body);                            // block 10
            self::assertStringContainsString('BRAND A', $body);                           // block 11
            self::assertStringContainsString('RECEIPT NOTE', $body);                      // block 18
            self::assertStringContainsString('BRANCH A', $body);                          // block 21 (branch header, session branch 1)
            // Blocks the WP set out to un-blank (1, 8, 9, 12, 13, 14, 15, 16, 17, 19, 20).
            $print->assertSee('URGENT/ซ่อมด่วน');                                        // block 1
            self::assertStringContainsString('027619999', $body);                        // block 8
            self::assertStringContainsString('15/01/2026', $body);                       // block 9
            self::assertStringContainsString('CABIN SPINNER', $body);                    // block 12
            $print->assertSee('มี WRT-777');                                             // block 13
            self::assertStringContainsString('value="1" disabled checked', $body);       // block 14
            self::assertStringContainsString('HANDLE CRACK', $body);                      // block 14 (other)
            self::assertStringContainsString('PRICE NOTE', $body);                        // block 15
            self::assertStringContainsString('FIXED NOTE', $body);                        // block 16
            self::assertStringContainsString('CHARGER AND STRAP', $body);                // block 17
            self::assertStringContainsString('RECEIVER NAME', $body);                    // block 19
            self::assertStringContainsString('src="http://example.invalid/uploads/' . $image . '"', $body); // block 20
        } finally {
            if (isset($order['detailImage'])) {
                @unlink(WRITEPATH . 'uploads/orders/' . $order['detailImage']);
            }
            @unlink($png);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testEditReplacesEveryEditableFieldAndPrintReflectsNewValues(): void
    {
        // AC-2: a second type/brand so the edit can move both off their seeded values.
        $this->db->table('type')->insert(['type_id' => 2, 'type_details' => 'TYPE B']);
        $this->db->table('brand')->insert(['brand_id' => 2, 'brand_details' => 'BRAND B']);
        $this->db->table('condition')->insert(['condition_id' => 4, 'condition_details' => 'CONDITION FOUR']);
        $this->seedFullOrder(93001);

        $this->postEdit(93001, $this->editPayload([
            'customer_name' => 'ROUNDTRIP CUSTOMER', 'customer_tel' => '0000000000',
            'customer_tel2' => '027619999', 'customer_email' => 'roundtrip@example.invalid',
            'type_id' => '2', 'brand_id' => '2', 'note' => 'ROUNDTRIP NOTE',
            'detail_agent' => '1', 'detail_date_purchase' => '15/01/2026',
            'detail_sku_name' => 'ROUNDTRIP SKU', 'waranty_type' => '1', 'detail_number_waranty' => 'WRT-999',
            'condition' => ['2', '4'], 'condition_other' => 'ROUNDTRIP CONDITION OTHER',
            'estimateprice' => ['2'], 'estimateprice_other' => 'ROUNDTRIP ESTIMATE OTHER',
            'fixed' => ['2'], 'fixed_other' => 'ROUNDTRIP FIXED OTHER',
            'detail_equipment' => 'ROUNDTRIP EQUIPMENT', 'create_by_user' => 'ROUNDTRIP RECEIVER',
        ]))->assertRedirectTo('/orders?status=1');

        $order = $this->db->table('request_order')->where('request_id', 93001)->get()->getRowArray();
        self::assertSame('ROUNDTRIP CUSTOMER', (string) $order['customerFullname']);
        self::assertSame('0000000000', (string) $order['customerTel']);
        self::assertSame('027619999', (string) $order['customerTel2']);
        self::assertSame(2, (int) $order['detailTypeId']);
        self::assertSame(2, (int) $order['detailBrandId']);
        self::assertSame(1, (int) $order['detailAgent']);
        self::assertSame('2026-01-15 00:00:00', (string) $order['detailDatePurchase']);
        self::assertSame('ROUNDTRIP SKU', (string) $order['detailSKUName']);
        self::assertSame(1, (int) $order['warantyType']);
        self::assertSame('WRT-999', (string) $order['detailNumberWaranty']);
        self::assertSame('2|4', (string) $order['detailCondition']);
        self::assertSame('2', (string) $order['detailEstimatePrice']);
        self::assertSame('2', (string) $order['detailFixed']);
        self::assertSame('ROUNDTRIP EQUIPMENT', (string) $order['detailEquipment']);
        self::assertSame('ROUNDTRIP RECEIVER', (string) $order['create_by_user']);
        // Immutable columns stay put (design §6): branch and status are never rewritten by edit.
        self::assertSame(1, (int) $order['branchID']);
        self::assertSame(1, (int) $order['action_status']);

        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/93001/print');
        $print->assertStatus(200);
        $body = $print->getBody();
        self::assertStringContainsString('ROUNDTRIP CUSTOMER', $body);
        self::assertStringContainsString('TYPE B', $body);
        self::assertStringContainsString('BRAND B', $body);
        self::assertStringContainsString('ROUNDTRIP SKU', $body);
        self::assertStringContainsString('15/01/2026', $body);
        $print->assertSee('มี WRT-999');
        self::assertStringContainsString('CONDITION FOUR', $body);
        self::assertStringContainsString('ROUNDTRIP EQUIPMENT', $body);
        self::assertStringNotContainsString('SEED CUSTOMER', $body);
    }

    public function testEditWithoutUploadKeepsExistingImage(): void
    {
        // AC-3: an edit that attaches no file must leave detailImage exactly as it was on disk and in
        // the column (mutation 1 nulls it out -> this assertion goes red).
        $created = $this->createOrderWithImage(str_repeat('3', 32));
        $existing = (string) $created['detailImage'];
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $existing);
        self::assertFileExists(WRITEPATH . 'uploads/orders/' . $existing);
        try {
            $form = $this->withSession($this->session(2, 2, 1))->get('/orders/' . (int) $created['request_id']);
            $form->assertStatus(200);
            self::assertStringContainsString('src="http://example.invalid/uploads/' . $existing . '"', $form->getBody());

            $this->postEdit((int) $created['request_id'], $this->editPayload(['customer_name' => 'IMG KEEP CUSTOMER']))
                ->assertRedirectTo('/orders?status=1');

            $order = $this->db->table('request_order')->where('request_id', (int) $created['request_id'])->get()->getRowArray();
            self::assertSame('IMG KEEP CUSTOMER', (string) $order['customerFullname']);
            self::assertSame($existing, (string) $order['detailImage']);
            self::assertFileExists(WRITEPATH . 'uploads/orders/' . $existing);
        } finally {
            @unlink(WRITEPATH . 'uploads/orders/' . $existing);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testEditWithNewImagesReplacesTheWholeSetAndKeepsOldFileOnDisk(): void
    {
        // AC-4: attaching new files replaces the whole detailImage set; the old name leaves the column
        // (mutation 2 appends instead -> count stays 2 and old-name-absent both go red) but its file
        // stays on disk (design §6: never delete the previous file).
        $created = $this->createOrderWithImage(str_repeat('4', 32));
        $oldName = (string) $created['detailImage'];
        self::assertFileExists(WRITEPATH . 'uploads/orders/' . $oldName);

        $png = $this->imageFixture('png');
        $jpg = $this->imageFixture('jpeg');
        $this->setUploads([
            ['tmp' => $png, 'name' => 'x.png', 'type' => 'image/png'],
            ['tmp' => $jpg, 'name' => 'y.jpg', 'type' => 'image/jpeg'],
        ]);
        $newNames = [];
        try {
            $this->postEdit((int) $created['request_id'], $this->editPayload(['customer_name' => 'IMG SWAP CUSTOMER']), false)
                ->assertRedirectTo('/orders?status=1');

            $order = $this->db->table('request_order')->where('request_id', (int) $created['request_id'])->get()->getRowArray();
            $newNames = explode('|', (string) $order['detailImage']);
            self::assertCount(2, $newNames);
            self::assertNotContains($oldName, $newNames);
            foreach ($newNames as $name) {
                self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $name);
                self::assertFileExists(WRITEPATH . 'uploads/orders/' . $name);
            }
            // The replaced file is intentionally left on disk.
            self::assertFileExists(WRITEPATH . 'uploads/orders/' . $oldName);
        } finally {
            @unlink(WRITEPATH . 'uploads/orders/' . $oldName);
            foreach ($newNames as $name) {
                @unlink(WRITEPATH . 'uploads/orders/' . $name);
            }
            @unlink($png);
            @unlink($jpg);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testEditImageFailureLeavesRowAndDiskUntouched(): void
    {
        // AC-5: a file failing mid-upload returns 422, removes the images already stored this request,
        // and leaves the existing detailImage (and every other column) exactly as it was.
        $created = $this->createOrderWithImage(str_repeat('5', 32));
        $oldName = (string) $created['detailImage'];
        self::assertFileExists(WRITEPATH . 'uploads/orders/' . $oldName);

        $png     = $this->imageFixture('png');
        $corrupt = $this->corruptPngFixture();
        $this->setUploads([
            ['tmp' => $png, 'name' => 'a.png', 'type' => 'image/png'],
            ['tmp' => $corrupt, 'name' => 'b.png', 'type' => 'image/png'],
        ]);
        $before = $this->orderImagesOnDisk();
        try {
            $this->postEdit((int) $created['request_id'], $this->editPayload(['customer_name' => 'SHOULD NOT APPLY']), false)
                ->assertStatus(422);

            $order = $this->db->table('request_order')->where('request_id', (int) $created['request_id'])->get()->getRowArray();
            // The whole edit rolled back: detailImage and the customer name keep their pre-edit values.
            self::assertSame($oldName, (string) $order['detailImage']);
            self::assertSame('SEED CUSTOMER', (string) $order['customerFullname']);
            self::assertFileExists(WRITEPATH . 'uploads/orders/' . $oldName);
            $after = $this->orderImagesOnDisk();
            sort($before);
            sort($after);
            self::assertSame($before, $after);
        } finally {
            @unlink(WRITEPATH . 'uploads/orders/' . $oldName);
            @unlink($png);
            @unlink($corrupt);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testEditDatabaseFailureKeepsPriorAssociationAndFileAndRemovesOnlyNewFiles(): void
    {
        $created = $this->createOrderWithImage(str_repeat('6', 32));
        $oldName = (string) $created['detailImage'];
        $oldPath = WRITEPATH . 'uploads/orders/' . $oldName;
        self::assertFileExists($oldPath);

        $png = $this->imageFixture('png');
        $this->setUploads([['tmp' => $png, 'name' => 'replacement.png', 'type' => 'image/png']]);
        $before = $this->orderImagesOnDisk();
        $trigger = $this->db->escapeIdentifiers($this->db->prefixTable('fail_order_edit'));
        $orders = $this->db->escapeIdentifiers($this->db->prefixTable('request_order'));
        $this->db->query("CREATE TRIGGER {$trigger} BEFORE UPDATE ON {$orders} BEGIN SELECT RAISE(FAIL, 'edit unavailable'); END");

        try {
            $this->postEdit(
                (int) $created['request_id'],
                $this->editPayload(['customer_name' => 'SHOULD NOT APPLY']),
                false,
            )->assertStatus(503);

            $row = $this->db->table('request_order')->where('request_id', (int) $created['request_id'])->get()->getRowArray();
            self::assertSame($oldName, (string) $row['detailImage']);
            self::assertSame('SEED CUSTOMER', (string) $row['customerFullname']);
            self::assertFileExists($oldPath);
            $after = $this->orderImagesOnDisk();
            sort($before);
            sort($after);
            self::assertSame($before, $after);
        } finally {
            $this->db->query("DROP TRIGGER IF EXISTS {$trigger}");
            @unlink($oldPath);
            @unlink($png);
            service('superglobals')->setFilesArray([]);
        }
    }

    public function testEditRejectsAdversarialFieldPayloadsWithoutChangingRow(): void
    {
        // AC-7: the same validation surface as create rejects bad edits with 422 and no row change.
        $this->seedFullOrder(93007);
        foreach ([
            'catalogue unknown id' => ['condition' => ['99999']],
            'catalogue empty'      => ['condition' => []],
            'catalogue missing'    => ['condition' => null],
            'purchase impossible day' => ['detail_date_purchase' => '31/02/2026'],
            'tel2 with dashes'     => ['customer_tel2' => '08-1234-5678'],
            'sku empty'            => ['detail_sku_name' => ''],
            'waranty out of set'   => ['waranty_type' => '2'],
            'create_by empty'      => ['create_by_user' => ''],
        ] as $label => $override) {
            $payload = $this->editPayload(['customer_name' => 'ADV EDIT CUSTOMER']);
            foreach ($override as $key => $value) {
                if ($value === null) {
                    unset($payload[$key]);
                } else {
                    $payload[$key] = $value;
                }
            }
            $this->postEdit(93007, $payload)->assertStatus(422);
            self::assertSame('SEED CUSTOMER', (string) $this->db->table('request_order')
                ->where('request_id', 93007)->get()->getRow('customerFullname'), $label);
        }
    }

    /**
     * Seed a status-1 order in branch 1 with every column populated so an edit has real values to
     * change; overrides win over the defaults.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function seedFullOrder(int $id, array $overrides = []): array
    {
        $this->db->table('request_order')->insert(array_merge([
            'request_id' => $id, 'requestDate' => '2026-08-20 00:00:00',
            'trackID' => 'WPA2608' . str_pad((string) ($id % 10000), 4, '0', STR_PAD_LEFT),
            'numberID' => 'N' . $id, 'orderID' => 'O' . $id, 'orderIDShow' => 'WPA/' . $id, 'bookID' => 'WPA',
            'customerFullname' => 'SEED CUSTOMER', 'customerTel' => '0000000000', 'customerTel2' => '',
            'customerEmail' => 'seed@example.invalid', 'detailTypeId' => 1, 'detailBrandId' => 1,
            'detailAgent' => '0', 'detailSKUName' => 'SEED BAG', 'warantyType' => 0,
            'detailNumberWaranty' => '', 'detailDatePurchase' => '0000-00-00 00:00:00',
            'detailCondition' => '1', 'detailConditionOther' => '', 'detailEstimatePrice' => '1',
            'detailEstimatePriceOther' => '', 'detailFixed' => '1', 'detailFixedOther' => '',
            'detailEquipment' => '', 'create_by_user' => 'SEED RECEIVER', 'detailNote' => 'SEED NOTE',
            'detailImage' => null, 'branchID' => 1, 'branch_type_id' => 1, 'UserID' => 9002, 'action_status' => 1,
        ], $overrides));

        return $this->db->table('request_order')->where('request_id', $id)->get()->getRowArray() ?? [];
    }

    /** POST-create a branch-1 order carrying exactly one stored image; returns the persisted row. */
    private function createOrderWithImage(string $submission): array
    {
        $png = $this->imageFixture('png');
        $this->setUploads([['tmp' => $png, 'name' => 'seed.png', 'type' => 'image/png']]);
        $numberId = '9' . substr($submission, 0, 8);
        $payload = [
            'submission_id' => $submission, 'number_id' => $numberId,
            'order_id' => 'ORDER-' . substr($submission, 0, 8), 'book_id' => '1',
            'customer_name' => 'SEED CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'seedimg@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'SEED NOTE',
            'detail_sku_name' => 'SEED BAG', 'create_by_user' => 'SEED RECEIVER',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ];
        $this->postOrder($payload, false)->assertRedirect();
        @unlink($png);
        service('superglobals')->setFilesArray([]);

        return $this->db->table('request_order')->where('numberID', $numberId)->get()->getRowArray() ?? [];
    }

    /**
     * A full editable payload for POST /orders/{id}; overrides win, so a test only spells out the
     * fields it wants to move.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'EDITED NAME', 'customer_tel' => '0000000000',
            'customer_email' => 'edited@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'note' => 'Edited note', 'detail_sku_name' => 'EDITED BAG', 'create_by_user' => 'EDITED RECEIVER',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ], $overrides);
    }

    /** @param array<string, mixed> $payload */
    private function postEdit(int $id, array $payload, bool $clearFiles = true)
    {
        if ($clearFiles) {
            service('superglobals')->setFilesArray([]);
        }
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session(2, 2, 1))->post('/orders/' . $id, $payload);
    }

    /** A real image of the requested type written to a temp file via gd. */
    private function imageFixture(string $type, int $width = 8, int $height = 8): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp04d-img-');
        self::assertIsString($path);
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 12, 34, 56));
        $written = match ($type) {
            'png'  => imagepng($image, $path),
            'jpeg' => imagejpeg($image, $path),
            'gif'  => imagegif($image, $path),
            default => self::fail('unknown fixture image type: ' . $type),
        };
        imagedestroy($image);
        self::assertTrue($written);

        return $path;
    }

    /** A file with an intact PNG signature and IHDR but truncated image data (imagecreatefrompng fails). */
    private function corruptPngFixture(): string
    {
        $valid = $this->imageFixture('png', 16, 16);
        $bytes = (string) file_get_contents($valid);
        @unlink($valid);
        $path = tempnam(sys_get_temp_dir(), 'wp04d-corrupt-');
        self::assertIsString($path);
        file_put_contents($path, substr($bytes, 0, 40));

        return $path;
    }

    private function setPreviewUpload(string $tmp, string $name, string $type): void
    {
        service('superglobals')->setFilesArray(['upl' => [
            'name' => $name,
            'type' => $type,
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => is_file($tmp) ? (int) filesize($tmp) : 0,
        ]]);
    }

    /**
     * Register uploads in the multi-file $_FILES shape the detail_image[] form produces.
     *
     * @param list<array<string, mixed>> $specs each carries tmp (path) plus optional name/type/size/error
     */
    private function setUploads(array $specs): void
    {
        if ($specs === []) {
            service('superglobals')->setFilesArray([]);

            return;
        }
        $files = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($specs as $spec) {
            $tmp                 = (string) $spec['tmp'];
            $files['name'][]     = (string) ($spec['name'] ?? 'upload.bin');
            $files['type'][]     = (string) ($spec['type'] ?? 'application/octet-stream');
            $files['tmp_name'][] = $tmp;
            $files['error'][]    = (int) ($spec['error'] ?? UPLOAD_ERR_OK);
            $files['size'][]     = (int) ($spec['size'] ?? (is_file($tmp) ? (int) filesize($tmp) : 0));
        }
        service('superglobals')->setFilesArray(['detail_image' => $files]);
    }

    /** @return list<string> absolute paths of every stored order image currently on disk */
    private function orderImagesOnDisk(): array
    {
        $found = glob(WRITEPATH . 'uploads/orders/*');

        return $found === false ? [] : $found;
    }

    /** @param array<string, mixed> $payload */
    /** @param array<string, int|bool|null>|null $session admin (branchless) actor by default */
    private function postTransition(string $path, array $payload, ?array $session = null)
    {
        service('superglobals')->setFilesArray([]);
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($session ?? $this->session(1, 1, null))->post($path, $payload);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'submission_id' => str_repeat('9', 32), 'number_id' => '9001', 'book_id' => '1',
            'customer_name' => 'VALID CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'valid@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair', 'detail_sku_name' => 'BAG SPORT',
            'create_by_user' => 'RECEIVER NAME', 'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ], $overrides);
    }

    /** @param array<string, mixed> $payload */
    private function postOrder(array $payload, bool $clearFiles = true)
    {
        return $this->postOrderAs($payload, $this->session(2, 2, 1), $clearFiles);
    }

    /** @param array<string, mixed> $payload @param array<string, int|bool|null> $session */
    private function postOrderAs(array $payload, array $session, bool $clearFiles = true)
    {
        if ($clearFiles) {
            service('superglobals')->setFilesArray([]);
        }
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($session)->post('/orders/new', $payload);
    }

    private function assertCreateCounts(int $orders, int $logs, int $intents): void
    {
        self::assertSame($orders, $this->db->table('request_order')->countAllResults());
        self::assertSame($logs, $this->db->table('status_log')->countAllResults());
        self::assertSame($intents, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testC3LabelParityOrderFormsAndReportHeaders(): void
    {
        $admin = $this->session(1, 1, null);

        // order_new (add): CI3 tracking/add_order.php labels (AC-4).
        $new = $this->withSession($admin)->get('/orders/new');
        $new->assertStatus(200);
        $new->assertSee('number ID/เลขที');            // CI3 typo (missing tone mark) preserved
        $new->assertSee('book Short/เล่มที่');
        $new->assertSee('customer Fullname/ชื่อลูกค้า');
        $new->assertSee('MOBILE TEL/เบอร์มือถือลูกค้า');
        $new->assertSee('Note/หมายเหตุ');
        $new->assertDontSee('>number_id</label>');      // AC-1
        $new->assertDontSee('>customer_tel</label>');
        self::assertStringContainsString('type="reset"', $new->getBody()); // t5 AC-6

        // order_edit: customer_tel uses a different CI3 string than the add page (AC-4).
        $edit = $this->withSession($admin)->get('/orders/91002');
        $edit->assertStatus(200);
        $edit->assertSee('customer Fullname/ชื่อลูกค้า');
        $edit->assertSee('customer Tel/เบอร์โทรลูกค้า');
        $edit->assertDontSee('MOBILE TEL/เบอร์มือถือลูกค้า'); // add-page tel text absent here
        self::assertStringContainsString('type="reset"', $edit->getBody()); // t5 AC-6
    }

    public function testCi3OrderFormCarriesRequestDateBranchTypeAndBranchShort(): void
    {
        $admin = $this->session(1, 1, null);

        // CI3 tracking/add_order.php ships these three labels in mixed case; the uppercase look
        // on screen comes from CSS text-transform, so the literal text stays as CI3 wrote it.
        $new = $this->withSession($admin)->get('/orders/new');
        $new->assertStatus(200);
        $new->assertSee('request Date/วันที่ส่งซ่อม');
        $new->assertSee('Branch Type/ประเภทของสาขา');
        $new->assertSee('branch short/ตัวย่อสาขา');
        $new->assertSee('Select Branch Type');
        $new->assertSee('TYPE ONE');
        $body = $new->getBody();
        // CI3 Order::add() prefills date('d/m/Y') and keeps the field readonly.
        self::assertStringContainsString('value="' . date('d/m/Y') . '"', $body);
        self::assertMatchesRegularExpression('#<input[^>]+name="requestDate"[^>]+readonly#', $body);
        // branch short is derived from the branch, never typed.
        self::assertMatchesRegularExpression('#<input[^>]+name="branchshort"[^>]+readonly#', $body);
        // CI3 populates branches and branch short through AJAX; no native data-* catalogue is emitted.
        self::assertStringNotContainsString('data-branch-type=', $body);
        self::assertStringNotContainsString('data-branch-short=', $body);
        self::assertStringContainsString('user/get_list_branch/', $body);
        self::assertStringContainsString('user/get_list_branchshort/', $body);

        // The edit form shows the stored request date and selected branch type/branch.
        $edit = $this->withSession($admin)->get('/orders/91002');
        $edit->assertStatus(200);
        $edit->assertSee('request Date/วันที่ส่งซ่อม');
        $edit->assertSee('Branch Type/ประเภทของสาขา');
        $editBody = (string) $edit->getBody();
        self::assertMatchesRegularExpression('/<option value="1"[^>]*selected[^>]*>\s*TYPE ONE\s*<\/option>/s', $editBody);
        self::assertMatchesRegularExpression('/<option value="1"[^>]*selected[^>]*>\s*BRANCH A\s*<\/option>/s', $editBody);
    }

    /** @return array<string, int|bool|null> */
    private function session(int $id, int $role, ?int $branch): array
    {
        return [
            'userId' => $id, 'role' => $role, 'GroupID' => $role === 1 ? 1 : 4, 'BranchID' => $branch,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
    }
}
