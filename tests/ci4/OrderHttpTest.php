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
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL UNIQUE, bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerTel2 VARCHAR(100), customerEmail VARCHAR(100), detailAgent VARCHAR(10), detailTypeId INTEGER, detailBrandId INTEGER, detailDatePurchase DATETIME, detailSKUName VARCHAR(250), detailNumberWaranty VARCHAR(250), detailCondition VARCHAR(250), detailConditionOther VARCHAR(250), detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250), detailFixed VARCHAR(250), detailFixedOther VARCHAR(250), detailEquipment VARCHAR(250), warantyType INTEGER, detailNote TEXT, detailImage VARCHAR(500), branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, provider_id INTEGER, logistics_etc_detail TEXT, date_create DATETIME, date_repair DATETIME, date_repair_complete DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, action_status INTEGER, RepairPrice DECIMAL(8,2), number_cmg VARCHAR(100), create_by_user VARCHAR(250), CONSTRAINT uq_request_order_order_show_tel UNIQUE (orderIDShow, customerTel)',
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
        $listing = $this->withSession($session)->get('/ordersListing');
        $listing->assertStatus(200);
        $listingBody = $listing->getBody();
        self::assertStringContainsString('action="/sendorderUpdate"', $listingBody);
        self::assertStringContainsString('type="checkbox" name="select_list_id[]"', $listingBody);
        self::assertStringContainsString('id="selectall_tracking"', $listingBody);
        self::assertStringContainsString('name="provider_id"', $listingBody);
        $listing->assertSee('Send');
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

    public function testEveryListingShowsCi3DateAndDetailFilters(): void
    {
        $admin = $this->session(1, 1, null);
        foreach (range(1, 8) as $status) {
            $response = $this->withSession($admin)->get('/orders?status=' . $status);
            $response->assertStatus(200);
            $body = $response->getBody();
            self::assertStringContainsString('<label for="order-date">from Date :</label>', $body);
            self::assertStringContainsString('name="sdate" value="" placeholder="Date"', $body);
            self::assertStringContainsString('<label for="order-search">Detail : </label>', $body);
            self::assertStringContainsString('name="search" value="" maxlength="128" placeholder="Search"', $body);
            // CI3 exposes the To Date field on queue 1 only; every other queue has one date box.
            self::assertSame(
                $status === 1,
                str_contains($body, '<label for="order-date-to">To Date : </label>'),
                'To Date belongs to queue 1 only, status ' . $status,
            );
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
            self::assertStringContainsString('>' . $title, $body, 'title, status ' . $status);
            if ($subtitle !== '') {
                self::assertStringContainsString($subtitle, $body, 'subtitle, status ' . $status);
            }
            self::assertSame(
                $listTitle !== '',
                str_contains($body, '<h3 class="box-title">' . $listTitle . '</h3>'),
                'list title, status ' . $status,
            );
            foreach ($headers as $header) {
                self::assertStringContainsString('<th>' . $header . '</th>', $body, $header . ', status ' . $status);
            }
        }
    }

    public function testQueueOneCarriesAddNewAndRowActionsWhileOtherQueuesDoNot(): void
    {
        $admin = $this->session(1, 1, null);

        $queueOne = (string) $this->withSession($admin)->get('/orders?status=1')->getBody();
        self::assertStringContainsString('>Add New<', $queueOne);
        self::assertStringContainsString('title="Edit"', $queueOne);
        self::assertStringContainsString('title="Delete"', $queueOne);
        self::assertStringContainsString('title="Print"', $queueOne);
        self::assertStringContainsString('id="order-delete-csrf"', $queueOne);

        // CI3 leaves queues 2-4 with checkboxes only: no Add New and no per-row controls.
        foreach ([2, 3, 4] as $status) {
            $body = (string) $this->withSession($admin)->get('/orders?status=' . $status)->getBody();
            self::assertStringNotContainsString('>Add New<', $body, 'status ' . $status);
            self::assertStringNotContainsString('title="Edit"', $body, 'status ' . $status);
            self::assertStringNotContainsString('title="Print"', $body, 'status ' . $status);
            self::assertStringContainsString('Select ALL tracking', $body, 'status ' . $status);
        }
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

    public function testListingDateFilterMatchesExactDayPersistsAndIgnoresMalformedInput(): void
    {
        $admin = $this->session(1, 1, null);

        $match = $this->withSession($admin)->get('/TrackingListing?sdate=' . rawurlencode('02/08/2026'));
        $match->assertStatus(200);
        $match->assertSee('WP00C-TRACK-002');
        self::assertStringContainsString('name="sdate" value="02/08/2026" placeholder="Date"', $match->getBody());

        $miss = $this->withSession($admin)->get('/TrackingListing?sdate=' . rawurlencode('03/08/2026'));
        $miss->assertStatus(200);
        $miss->assertDontSee('WP00C-TRACK-002');
        self::assertStringContainsString('name="sdate" value="03/08/2026" placeholder="Date"', $miss->getBody());

        $malformed = $this->withSession($admin)->get('/TrackingListing?sdate=abc');
        $malformed->assertStatus(200);
        $malformed->assertSee('WP00C-TRACK-002');
        self::assertStringContainsString('name="sdate" value="abc" placeholder="Date"', $malformed->getBody());
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
        self::assertStringContainsString(
            'href="/orders?status=1&amp;page=2&amp;sdate=02%2F08%2F2026&amp;search=needle">Next</a>',
            $response->getBody(),
        );

        $withoutFilters = $this->withSession($this->session(1, 1, null))->get('/orders?status=1');
        self::assertStringContainsString(
            'href="/orders?status=1&amp;page=2">Next</a>',
            $withoutFilters->getBody(),
        );
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

    public function testNewOrderFormScopesCanonicalBooksAndDefinesLocalPreviewContract(): void
    {
        $admin = $this->withSession($this->session(1, 1, null))->get('/orders/new');
        $admin->assertStatus(200);
        $adminBody = $admin->getBody();
        self::assertStringContainsString('name="book_id" required', $adminBody);
        self::assertStringContainsString('value="1" data-book-detail="ABC" data-branch-id="1"', $adminBody);
        self::assertStringContainsString('value="3" data-book-detail="XYZ" data-branch-id="2"', $adminBody);
        self::assertStringContainsString('value="4" data-book-detail="ABC" data-branch-id="2"', $adminBody);
        self::assertStringNotContainsString('OLD', $adminBody);
        self::assertStringNotContainsString('name="order_id"', $adminBody);
        self::assertStringContainsString('inputmode="numeric" pattern="[0-9]+" maxlength="96" required', $adminBody);
        self::assertStringContainsString('<output id="order-id-preview"', $adminBody);
        self::assertStringContainsString("form.addEventListener('reset'", $adminBody);
        self::assertStringNotContainsString('fetch(', $adminBody);
        self::assertStringNotContainsString('XMLHttpRequest', $adminBody);

        foreach ([2, 3] as $role) {
            $branchBody = $this->withSession($this->session($role, $role, 1))->get('/orders/new')->getBody();
            self::assertStringContainsString('value="1" data-book-detail="ABC" data-branch-id="1"', $branchBody);
            self::assertStringNotContainsString('data-branch-id="2"', $branchBody);
            self::assertStringNotContainsString('XYZ', $branchBody);
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

    public function testNewOrderFormExposesCatalogueCheckboxesAndCi4FieldNames(): void
    {
        $body = $this->withSession($this->session(2, 2, 1))->get('/orders/new')->getBody();
        // The controller wires the three catalogues into the form; names are the CI4 contract normalize() reads.
        self::assertStringContainsString('name="condition[]"', $body);
        self::assertStringContainsString('name="estimateprice[]"', $body);
        self::assertStringContainsString('name="fixed[]"', $body);
        self::assertStringContainsString('CONDITION ONE', $body);
        self::assertStringContainsString('name="detail_sku_name"', $body);
        self::assertStringContainsString('name="waranty_type"', $body);
        self::assertStringContainsString('name="create_by_user"', $body);
        // T2: the repair-image input takes several files under the array name normalize() expects.
        self::assertStringContainsString('name="detail_image[]"', $body);
        self::assertStringContainsString('multiple', $body);
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

    public function testCompleteListingExposesBulkCompleteFormAlongsideRatingButton(): void
    {
        $body = $this->withSession($this->session(2, 2, 1))->get('/TrackingcompleteListing')->getBody();
        self::assertStringContainsString('action="/sendorderUpdateStatus"', $body);
        self::assertStringContainsString('type="checkbox" name="select_list_id[]"', $body);
        self::assertStringContainsString('<option value="7"', $body);
        // The per-row rating button stays on the same page as the bulk form.
        self::assertStringContainsString('class="rate-open"', $body);
        // AC-6: the rating modal partial no longer carries an inline <style> block (its rules
        // moved to admin.css), so a page that renders the modal has no <style in the document.
        self::assertStringNotContainsString('<style', $body);
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
        self::assertStringContainsString('value="1" disabled checked', $body);
        self::assertStringContainsString('value="2" disabled checked', $body);
        self::assertStringContainsString('value="3" disabled>', $body);
        self::assertStringContainsString('EXTRA HANDLE CRACK', $body);

        // AC-1: only the 32hex .png image is emitted; the legacy name is skipped, not rendered broken.
        self::assertStringContainsString('src="/order-image/' . str_repeat('e', 32) . '.png"', $body);
        self::assertStringNotContainsString('legacy.jpg', $body);

        // AC-1: the CI3 form never printed a status label, so the CI4 view must not either.
        self::assertStringNotContainsString('Status', $body);

        // AC-3: 0000-00-00 purchase date blanks out; the valid request date renders dd/mm/YYYY (CE).
        self::assertStringNotContainsString('00/00/0000', $body);
        self::assertStringContainsString('10/08/2026', $body);

        // Block 15 reads detailNumberWaranty directly (not warantyType).
        $print->assertSee('มี WRT-123');

        // AC-5: DB values pass through esc(); the A4 page and self-hiding print button are present.
        self::assertStringContainsString('BAG &lt;SPORT&gt;', $body);
        self::assertStringNotContainsString('BAG <SPORT>', $body);
        self::assertStringContainsString('size: A4', $body);
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
                self::assertStringContainsString('src="/order-image/' . $name . '"', $body);
            }
            self::assertSame(3, substr_count($body, 'src="/order-image/'));
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
            self::assertStringContainsString('src="/order-image/' . $image . '"', $body); // block 20
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
        self::assertMatchesRegularExpression('#<input[^>]+name="request_date"[^>]+readonly#', $body);
        // branch short is derived from the branch, never typed.
        self::assertMatchesRegularExpression('#<input[^>]+name="branch_short"[^>]+readonly#', $body);
        // The branch options carry the two data attributes the cascade reads.
        self::assertStringContainsString('data-branch-type="1"', $body);
        self::assertStringContainsString('data-branch-short="WPA"', $body);

        // The edit form shows the stored request date, its branch type and short, all locked.
        $edit = $this->withSession($admin)->get('/orders/91002');
        $edit->assertStatus(200);
        $edit->assertSee('request Date/วันที่ส่งซ่อม');
        $edit->assertSee('Branch Type/ประเภทของสาขา');
        $edit->assertSee('branch short/ตัวย่อสาขา');
        self::assertStringContainsString('value="WPA"', $edit->getBody());
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
