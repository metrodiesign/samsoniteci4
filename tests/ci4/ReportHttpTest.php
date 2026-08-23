<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ReportHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $branchUserId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'branch_type' => 'branch_type_id INTEGER PRIMARY KEY, branch_type_details VARCHAR(250), branch_type_image VARCHAR(250)',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER, branch_user_name VARCHAR(100), branch_name VARCHAR(250)',
            'statusaction' => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(250), status_name_th VARCHAR(250)',
            'brand' => 'brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250)',
            'type' => 'type_id INTEGER PRIMARY KEY, type_details VARCHAR(250)',
            'request_order' => 'request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderID VARCHAR(100), orderIDShow VARCHAR(100), number_cmg VARCHAR(100), detailAgent INTEGER, customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailSKUName VARCHAR(100), detailBrandId INTEGER, detailTypeId INTEGER, branchID INTEGER, action_status INTEGER, date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, provider_id INTEGER, logistics_etc_detail TEXT, RepairPrice DECIMAL(8,2), waranty_cmg VARCHAR(100)',
            'rating' => 'rating_id INTEGER PRIMARY KEY AUTOINCREMENT, add_id INTEGER, rating INTEGER, order_id VARCHAR(100), branchID INTEGER, cdate DATETIME',
            'rating_comment' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, track_id VARCHAR(100), branch_id INTEGER, comment TEXT, created_at DATETIME',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->table('ci4_users')->truncate();
        $this->db->resetDataCache();
        $this->db->table('branch_type')->insertBatch([
            ['branch_type_id' => 1, 'branch_type_details' => 'TYPE A', 'branch_type_image' => 'branch-a.png'],
            ['branch_type_id' => 2, 'branch_type_details' => 'TYPE B', 'branch_type_image' => 'branch-b.png'],
        ]);
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'branch-a', 'branch_name' => 'BRANCH A'],
            ['branch_id' => 2, 'branch_type' => 2, 'branch_user_name' => 'branch-b', 'branch_name' => 'BRANCH B'],
        ]);
        $this->db->table('brand')->insertBatch([
            ['brand_id' => 1, 'brand_details' => 'BRAND A'],
            ['brand_id' => 2, 'brand_details' => 'BRAND B'],
        ]);
        $this->db->table('type')->insertBatch([
            ['type_id' => 1, 'type_details' => 'TYPE A'],
            ['type_id' => 2, 'type_details' => 'TYPE B'],
        ]);
        for ($status = 1; $status <= 8; $status++) {
            $this->db->table('statusaction')->insert([
                'status_id' => $status, 'status_name' => 'STATUS ' . $status, 'status_name_th' => 'สถานะ ' . $status,
            ]);
            $this->db->table('request_order')->insert($this->order(
                $status,
                $status <= 4 ? 1 : 2,
                $status,
                $status % 2 === 0 ? 2 : 1,
                $status % 2 === 0 ? 2 : 1,
            ));
        }
        $missing = $this->order(9, 1, 2, 999, 999);
        $missing['trackID'] = 'WP00C-REPORT-MISSING';
        $this->db->table('request_order')->insert($missing);
        $this->db->table('rating')->insertBatch([
            ['add_id' => 1, 'rating' => 5, 'order_id' => 'WP00C-REPORT-001', 'branchID' => 1, 'cdate' => '2026-08-01 12:00:00'],
            ['add_id' => 1, 'rating' => 3, 'order_id' => 'WP00C-REPORT-002', 'branchID' => 1, 'cdate' => '2026-08-02 12:00:00'],
            ['add_id' => 1, 'rating' => 1, 'order_id' => 'WP00C-REPORT-005', 'branchID' => 2, 'cdate' => '2026-08-05 12:00:00'],
            ['add_id' => 2, 'rating' => 4, 'order_id' => 'WP00C-REPORT-003', 'branchID' => 1, 'cdate' => '2026-08-03 12:00:00'],
        ]);
        $users = new ShadowUserStore($this->db);
        $this->adminId = $users->create('report-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null);
        $this->branchUserId = $users->create('report-branch@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1);
    }

    public function testDashboardTotalsUseSessionBranchAndBranchTypeBackground(): void
    {
        $admin = $this->withSession($this->session(1, 1, null))->get('/dashboard');
        $admin->assertStatus(200);
        self::assertStringContainsString('data-status="2" data-count="2"', $admin->getBody());
        self::assertStringContainsString('data-status="5" data-count="1"', $admin->getBody());

        $branch = $this->withSession($this->session(2, 2, 1))->get('/dashboard');
        $branch->assertStatus(200);
        $branch->assertSee('BRANCH A');
        self::assertStringContainsString('data-background="branch-a.png"', $branch->getBody());
        self::assertStringContainsString('data-status="2" data-count="2"', $branch->getBody());
        self::assertStringContainsString('data-status="5" data-count="0"', $branch->getBody());
    }

    public function testAllLegacyHtmlReportRoutesUseExactScopedMatrixTotals(): void
    {
        $routes = [
            '/user/report' => 'Ratings', '/user/report_job_byday' => 'Jobs by day',
            '/user/report_job_pending' => 'Pending jobs', '/user/report_total_job_pending' => 'Pending totals',
            '/user/report_in_progress_average' => 'In-progress average',
            '/user/report_in_progress_job' => 'In-progress jobs',
        ];
        foreach ($routes as $route => $heading) {
            $response = $this->withSession($this->session(2, 2, 1))->get($route);
            $response->assertStatus(200);
            $response->assertSee($heading);
            $response->assertDontSee('WP00C-REPORT-005');
        }
        $ratings = $this->withSession($this->session(2, 2, 1))->get('/user/report');
        self::assertStringContainsString('data-question="1" data-total="2"', $ratings->getBody());
        self::assertStringContainsString('/reports/ratings/export', $ratings->getBody());
        $ratings->assertSee('50.00%');

        $inProgress = $this->withSession($this->session(2, 2, 1))->get('/user/report_in_progress_job');
        self::assertStringContainsString('/reports/in-progress/export', $inProgress->getBody());

        $filtered = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        self::assertStringContainsString('data-question="1" data-total="1"', $filtered->getBody());
        $filtered->assertSee('100.00%');
    }

    public function testSummaryOmitsMissingMasterAndAppliesBranchStatusBrandTypeFilters(): void
    {
        $all = $this->withSession($this->session(1, 1, null))->get('/reportsummary');
        $all->assertStatus(200);
        self::assertStringContainsString('8 matching order(s)', $all->getBody());
        foreach (['searchText', 'sdate', 'edate', 'status_id', 'detailBrandId', 'detailTypeId', 'branch_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $all->getBody());
        }
        self::assertStringContainsString('/reports/summary/export', $all->getBody());
        $all->assertDontSee('WP00C-REPORT-MISSING');

        $filtered = $this->withSession($this->session(1, 1, null))->post('/reportsummary', [
            'csrf_test_name' => service('security')->getHash(), 'status_id' => '2',
            'detailBrandId' => '2', 'detailTypeId' => '2', 'sdate' => '01/08/2026', 'edate' => '31/08/2026',
        ]);
        $filtered->assertStatus(200);
        $filtered->assertSee('WP00C-REPORT-002');
        $filtered->assertDontSee('WP00C-REPORT-004');

        $branch = $this->withSession($this->session(2, 2, 1))->get('/reportsummary');
        $branch->assertSee('4 matching order(s)');
        $branch->assertDontSee('WP00C-REPORT-005');
    }

    public function testTrackingSummaryRatingsAndInProgressExportsHaveStableXlsContract(): void
    {
        $trackingPage = $this->withSession($this->session(2, 2, 1))->get('/ReportTrackingListing');
        self::assertStringContainsString('/reports/tracking/export', $trackingPage->getBody());

        foreach (['tracking', 'summary', 'ratings', 'in-progress'] as $type) {
            try {
                $response = $this->withSession($this->session(2, 2, 1))->get('/reports/' . $type . '/export');
            } catch (PageNotFoundException) {
                self::fail('Export route failed: ' . $type);
            }
            $response->assertStatus(200);
            self::assertSame('application/vnd.ms-excel; charset=UTF-8', $response->response()->getHeaderLine('Content-Type'));
            self::assertMatchesRegularExpression('/attachment; filename="[a-z-]+-report\.xls"/', $response->response()->getHeaderLine('Content-Disposition'));
            self::assertStringContainsString('<table', $response->getBody());
            self::assertStringNotContainsString('WP00C-REPORT-005', $response->getBody());
        }
        foreach (['/user/excel_ratings', '/user/excel_in_progress_job', '/Order/excel_report', '/Order/excel_report_sum'] as $path) {
            $legacy = $this->withSession($this->session(2, 2, 1))->get($path);
            $legacy->assertStatus(200);
            self::assertStringContainsString('attachment; filename=', $legacy->response()->getHeaderLine('Content-Disposition'));
        }
    }

    public function testReportEdgesRejectBadDatesLargeSearchAndCrossBranchWithoutDataLeak(): void
    {
        $invalidDate = $this->withSession($this->session(1, 1, null))->post('/reportsummary', [
            'csrf_test_name' => service('security')->getHash(), 'sdate' => '31/02/2026', 'edate' => '01/03/2026',
        ]);
        $invalidDate->assertStatus(422);
        $invalidDate->assertDontSee('WP00C-REPORT-001');

        $large = $this->withSession($this->session(1, 1, null))->post('/reportsummary', [
            'csrf_test_name' => service('security')->getHash(), 'searchText' => str_repeat('x', 129),
        ]);
        $large->assertStatus(422);

        try {
            $this->withSession($this->session(2, 2, 1))->get('/reportsummary/0/2');
            self::fail('Expected cross-branch report denial.');
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
        }
        $empty = $this->withSession($this->session(1, 1, null))->post('/reportsummary', [
            'csrf_test_name' => service('security')->getHash(), 'sdate' => '', 'edate' => '',
        ]);
        $empty->assertStatus(200);
        $empty->assertSee('8 matching order(s)');
    }

    public function testSummaryLimitsEachLegacyPageToOneHundredRows(): void
    {
        for ($id = 10; $id <= 110; $id++) {
            $row = $this->order($id, 1, 2, 1, 1);
            $row['trackID'] = sprintf('WP00C-PAGE-%03d', $id);
            $this->db->table('request_order')->insert($row);
        }

        $first = $this->withSession($this->session(1, 1, null))->get('/reportsummary');
        $first->assertStatus(200);
        $first->assertSee('100 matching order(s)');
        $first->assertSee('WP00C-PAGE-110');
        $first->assertDontSee('WP00C-PAGE-010');

        $second = $this->withSession($this->session(1, 1, null))->get('/reportsummary/100');
        $second->assertStatus(200);
        $second->assertSee('9 matching order(s)');
        $second->assertSee('WP00C-PAGE-010');
        $second->assertDontSee('WP00C-PAGE-110');

        $filteredFirst = $this->withSession($this->session(1, 1, null))->get('/reportsummary?searchText=WP00C-PAGE');
        self::assertStringContainsString('/reportsummary/100?searchText=WP00C-PAGE', $filteredFirst->getBody());
        $filteredSecond = $this->withSession($this->session(1, 1, null))->get('/reportsummary/100?searchText=WP00C-PAGE');
        $filteredSecond->assertSee('1 matching order(s)');
        $filteredSecond->assertSee('WP00C-PAGE-010');
        $filteredSecond->assertDontSee('WP00C-PAGE-110');
    }

    /** @return array<string, int|string|null> */
    private function order(int $id, int $branch, int $status, int $brand, int $type): array
    {
        $date = sprintf('2026-08-%02d 00:00:00', min($id, 28));

        return [
            'request_id' => $id, 'requestDate' => $date, 'trackID' => sprintf('WP00C-REPORT-%03d', $id),
            'orderID' => 'ORDER-' . $id, 'orderIDShow' => 'WPC/' . $id, 'number_cmg' => 'CMG-' . $id,
            'detailAgent' => 0, 'customerFullname' => 'CUSTOMER ' . $id, 'customerTel' => '0000000000',
            'customerEmail' => 'customer-' . $id . '@example.invalid', 'detailSKUName' => 'SKU-' . $id,
            'detailBrandId' => $brand, 'detailTypeId' => $type, 'branchID' => $branch, 'action_status' => $status,
            'date_repair' => $date, 'date_repair_waranty' => null, 'date_update_status' => $date,
            'date_deliver' => $status >= 5 ? $date : null, 'date_complete' => $status === 7 ? $date : null,
            'provider_id' => 1, 'logistics_etc_detail' => 'PROVIDER', 'RepairPrice' => '100.00', 'waranty_cmg' => 'IN',
        ];
    }

    /** @return array<string, int|bool|null> */
    private function session(int $id, int $role, ?int $branch): array
    {
        $id = $role === 1 ? $this->adminId : $this->branchUserId;

        return [
            'userId' => $id, 'role' => $role, 'GroupID' => $role === 1 ? 1 : 4, 'BranchID' => $branch,
            'sessionVersion' => 1, 'isLoggedIn' => true, 'name' => $role === 1 ? 'ADMIN' : 'BRANCH USER',
        ];
    }
}
