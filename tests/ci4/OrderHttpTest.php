<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Orders\OrderSequence;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
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
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL UNIQUE, bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailTypeId INTEGER, detailBrandId INTEGER, detailCondition VARCHAR(250), detailEstimatePrice VARCHAR(250), detailFixed VARCHAR(250), detailNote TEXT, detailImage VARCHAR(500), branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, provider_id INTEGER, logistics_etc_detail TEXT, date_create DATETIME, date_repair DATETIME, date_repair_complete DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, action_status INTEGER, RepairPrice DECIMAL(8,2), number_cmg VARCHAR(100), create_by_user VARCHAR(250)',
            'status_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
            'statusaction' => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(250) NOT NULL, status_name_th VARCHAR(250)',
            'provider' => 'provider_id INTEGER PRIMARY KEY, provider_name VARCHAR(250) NOT NULL',
            'brand' => 'brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250) NOT NULL',
            'type' => 'type_id INTEGER PRIMARY KEY, type_details VARCHAR(250) NOT NULL',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER NOT NULL, branch_user_name VARCHAR(100), branch_name VARCHAR(250) NOT NULL, default_suffix VARCHAR(10), book_order VARCHAR(10), customer_ref VARCHAR(50)',
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
        $this->db->table('brand')->insert(['brand_id' => 1, 'brand_details' => 'BRAND A']);
        $this->db->table('type')->insert(['type_id' => 1, 'type_details' => 'TYPE A']);
        $this->db->table('provider')->insert(['provider_id' => 1, 'provider_name' => 'PROVIDER A']);
        for ($status = 1; $status <= 8; $status++) {
            $this->db->table('statusaction')->insert(['status_id' => $status, 'status_name' => 'STATUS ' . $status, 'status_name_th' => 'สถานะ ' . $status]);
            $this->db->table('request_order')->insert([
                'request_id' => 91000 + $status, 'requestDate' => sprintf('2026-08-%02d 00:00:00', $status),
                'trackID' => 'WP00C-TRACK-00' . $status, 'numberID' => 'N' . $status,
                'orderID' => 'O' . $status, 'orderIDShow' => 'WPC/' . $status,
                'customerFullname' => 'CUSTOMER ' . $status, 'customerTel' => '0000000000',
                'branchID' => $status <= 6 ? 1 : 2, 'branch_type_id' => $status <= 6 ? 1 : 2,
                'UserID' => $status <= 6 ? 9002 : 9003, 'action_status' => $status,
            ]);
        }
        $users = new ShadowUserStore($this->db);
        $users->create('order-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null);
        $users->create('order-branch@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1);
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

    public function testLifecycleQueuesExposeBrowserFormsForNormalTransitions(): void
    {
        $session = $this->session(2, 2, 1);

        // status 1 is a bulk provider form (T7): one form over the table, provider dropdown in the
        // footer, no per-row provider form left.
        $listing = $this->withSession($session)->get('/ordersListing');
        $listing->assertStatus(200);
        $listingBody = $listing->getBody();
        self::assertStringContainsString('action="/sendorderUpdate"', $listingBody);
        self::assertStringContainsString('type="checkbox" name="select_list_id[]"', $listingBody);
        self::assertStringContainsString('id="selectall_tracking"', $listingBody);
        self::assertStringContainsString('name="provider_id"', $listingBody);
        $listing->assertSee('Send');
        self::assertStringNotContainsString('Send to provider', $listingBody);

        foreach ([
            '/TrackingListing' => ['/sendorderUpdateStatus', 'status_id', 'Send', [3, 4]],
            '/TrackingcloseListing' => ['/sendorderUpdateStatus', 'status_id', 'Send', [4]],
            '/TrackingreturnListing' => ['/sendorder_deliver', 'status_id', 'Send', [5]],
        ] as $route => [$action, $field, $button, $optionValues]) {
            $response = $this->withSession($session)->get($route);
            $response->assertStatus(200);
            $body = $response->getBody();
            self::assertStringContainsString('action="' . $action . '"', $body);
            self::assertStringContainsString('name="select_list_id[]"', $body);
            self::assertStringContainsString('name="' . $field . '"', $body);
            $response->assertSee($button);
            // Discriminating anchors: the JS also mentions select_list_id and selectall_tracking,
            // so match the actual row checkbox and the header input, not the bare names.
            self::assertStringContainsString('type="checkbox" name="select_list_id[]"', $body);
            self::assertStringContainsString('id="selectall_tracking"', $body);
            foreach ($optionValues as $value) {
                self::assertStringContainsString('<option value="' . $value . '"', $body);
            }
            self::assertSame(count($optionValues), substr_count($body, '<option'));
        }
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
        self::assertStringContainsString('<dialog', $body);
        self::assertStringContainsString("fetch('/rating'", $body);
        for ($question = 1; $question <= 8; $question++) {
            self::assertStringContainsString('name="rating_' . $question . '"', $body);
        }
        self::assertStringContainsString('name="rating_comment"', $body);

        $completed = $this->withSession($admin)->get('/TrackingCompletedListing');
        $completed->assertStatus(200);
        self::assertStringNotContainsString('<dialog', $completed->getBody());
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
        $response->assertSee('TRANSPORTING');
        $response->assertSee('O2');
        $response->assertSee('STATUS 2');
        $response->assertSee('02/08/2026');
        $response->assertSee('ซ่อมเสร็จแล้ว');
        $response->assertDontSee('อยู่ระหว่างซ่อม');
        self::assertSame(1, $uploadQueries, 'listing must batch Status Update into one uploadstaus query per page');
    }

    public function testListingDateFilterMatchesExactDayAndIgnoresMalformedInput(): void
    {
        $admin = $this->session(1, 1, null);

        $match = $this->withSession($admin)->get('/TrackingListing?sdate=' . rawurlencode('02/08/2026'));
        $match->assertStatus(200);
        $match->assertSee('WP00C-TRACK-002');

        $miss = $this->withSession($admin)->get('/TrackingListing?sdate=' . rawurlencode('03/08/2026'));
        $miss->assertStatus(200);
        $miss->assertDontSee('WP00C-TRACK-002');

        $malformed = $this->withSession($admin)->get('/TrackingListing?sdate=abc');
        $malformed->assertStatus(200);
        $malformed->assertSee('WP00C-TRACK-002');
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

    public function testCreateOrderWritesOrderLogEncryptedSmsIntentAndSafeImageAtomically(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'wp00c-order-');
        self::assertIsString($png);
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        service('superglobals')->setFilesArray(['detail_image' => [
            'name' => 'repair.png', 'type' => 'image/png', 'tmp_name' => $png,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($png),
        ]]);
        $payload = [
            'submission_id' => str_repeat('a', 32), 'number_id' => '1001', 'order_id' => 'ORDER-1001',
            'book_id' => 'WPA', 'customer_name' => 'NEW CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'customer@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
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
        service('superglobals')->setFilesArray([]);
    }

    public function testCreateOrderRejectsInvalidUploadReplayDuplicateAndCrossBranchWithoutPartialWrites(): void
    {
        $valid = [
            'submission_id' => str_repeat('b', 32), 'number_id' => '2001', 'order_id' => 'ORDER-2001',
            'book_id' => 'WPA', 'customer_name' => 'VALID CUSTOMER', 'customer_tel' => '0000000000',
            'customer_email' => 'customer@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'branch_id' => '1', 'note' => 'Synthetic repair',
        ];
        $this->postOrder([...$valid, 'brand_id' => '999'])->assertStatus(422);
        $this->assertCreateCounts(8, 0, 0);

        $bad = tempnam(sys_get_temp_dir(), 'wp00c-bad-order-');
        self::assertIsString($bad);
        file_put_contents($bad, '<?php echo "bad";');
        service('superglobals')->setFilesArray(['detail_image' => [
            'name' => 'repair.png', 'type' => 'image/png', 'tmp_name' => $bad,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($bad),
        ]]);
        $this->postOrder($valid, false)->assertStatus(422);
        $this->assertCreateCounts(8, 0, 0);

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

    public function testNormalLifecycleWritesExactProviderDatesStatusesAndLogs(): void
    {
        $this->postTransition('/sendorderUpdate', ['select_list_id' => ['91001'], 'provider_id' => '1'])
            ->assertRedirectTo('/sendorderListing');
        $status2 = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame(2, (int) $status2['action_status']);
        self::assertSame(1, (int) $status2['provider_id']);
        self::assertNotEmpty($status2['date_create']);
        self::assertNotEmpty($status2['date_update_status']);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91002'], 'status_id' => '3'])
            ->assertRedirectTo('/ReportTrackingListing');
        $status3 = $this->db->table('request_order')->where('request_id', 91002)->get()->getRowArray();
        self::assertSame(3, (int) $status3['action_status']);
        self::assertNotEmpty($status3['date_update_status']);

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91003'], 'status_id' => '4'])
            ->assertRedirectTo('/ReportTrackingListing');
        self::assertSame(4, (int) $this->db->table('request_order')->where('request_id', 91003)->get()->getRow('action_status'));

        $this->postTransition('/sendorder_deliver', ['select_list_id' => ['91004'], 'status_id' => '5'])
            ->assertRedirectTo('/TrackingreturnListing');
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
            'customerFullname' => 'DELIVER CUSTOMER', 'customerTel' => '0812345678',
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
        self::assertStringNotContainsString('0812345678', (string) $intent['payload_ciphertext']);
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
            'customerFullname' => 'STATUS MODE CUSTOMER', 'customerTel' => '0812345678',
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
            'customerFullname' => 'COMPLETE CUSTOMER', 'customerTel' => '0812345678',
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
        self::assertStringNotContainsString('0812345678', (string) $intent['payload_ciphertext']);
        $payload = json_decode(service('encrypter')->decrypt(base64_decode((string) $intent['payload_ciphertext'], true)), true);
        self::assertStringContainsString('ขอบคุณที่ใช้บริการกับ Samsonite  แสดงความคิดเห็น', $payload['message']);
        self::assertStringContainsString('/rating/WPA26080057', $payload['message']);
    }

    public function testCompleteListingExposesBulkCompleteFormAlongsideRatingButton(): void
    {
        $body = $this->withSession($this->session(2, 2, 1))->get('/TrackingcompleteListing')->getBody();
        self::assertStringContainsString('action="/sendorderUpdateStatus"', $body);
        self::assertStringContainsString('type="checkbox" name="select_list_id[]"', $body);
        self::assertStringContainsString('<option value="7"', $body);
        // The per-row rating button stays on the same page as the bulk form.
        self::assertStringContainsString('class="rate-open"', $body);
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

        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => ['91007'], 'status_id' => '4'])
            ->assertStatus(404);
        $this->postTransition('/sendorderUpdateStatus', ['select_list_id' => [], 'status_id' => '3'])
            ->assertStatus(422);
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testPrintEditAndSoftDeletePreserveStatusLogAndDenyCrossBranch(): void
    {
        $print = $this->withSession($this->session(2, 2, 1))->get('/orders/91001/print');
        $print->assertStatus(200);
        $print->assertSee('WP00C-TRACK-001');
        $print->assertSee('CUSTOMER 1');

        $edit = [
            'customer_name' => 'EDITED CUSTOMER', 'customer_tel' => '1111111111',
            'customer_email' => 'edited@example.invalid', 'type_id' => '1', 'brand_id' => '1',
            'note' => 'Edited note', 'action_status' => '7',
        ];
        $edit['csrf_test_name'] = service('security')->getHash();
        $this->withSession($this->session(2, 2, 1))->post('/orders/91001', $edit)
            ->assertRedirectTo('/orders?status=1');
        $row = $this->db->table('request_order')->where('request_id', 91001)->get()->getRowArray();
        self::assertSame('EDITED CUSTOMER', $row['customerFullname']);
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

        $this->postTransition('/orders/91001/delete', [])->assertStatus(204);
        self::assertSame(8, (int) $this->db->table('request_order')->where('request_id', 91001)->get()->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    /** @param array<string, mixed> $payload */
    private function postTransition(string $path, array $payload)
    {
        service('superglobals')->setFilesArray([]);
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session(2, 2, 1))->post($path, $payload);
    }

    /** @param array<string, string> $payload */
    private function postOrder(array $payload, bool $clearFiles = true)
    {
        if ($clearFiles) {
            service('superglobals')->setFilesArray([]);
        }
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session(2, 2, 1))->post('/orders/new', $payload);
    }

    private function assertCreateCounts(int $orders, int $logs, int $intents): void
    {
        self::assertSame($orders, $this->db->table('request_order')->countAllResults());
        self::assertSame($logs, $this->db->table('status_log')->countAllResults());
        self::assertSame($intents, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
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
