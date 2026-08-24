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

    public function testNonRatingReportsDefaultToLastMonthWhenDatesOmitted(): void
    {
        // Bucket 3 (status 5 + date_complete) is empty in the base fixture, so these two rows are isolated.
        $near = $this->order(201, 1, 5, 1, 1);
        $near['requestDate'] = (new \DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d H:i:s');
        $near['date_complete'] = $near['requestDate'];
        $near['trackID'] = 'WP06A-NEAR';
        $this->db->table('request_order')->insert($near);

        $far = $this->order(202, 1, 5, 1, 1);
        $far['requestDate'] = (new \DateTimeImmutable('today'))->modify('-40 days')->format('Y-m-d H:i:s');
        $far['date_complete'] = $far['requestDate'];
        $far['trackID'] = 'WP06A-FAR';
        $this->db->table('request_order')->insert($far);

        $response = $this->withSession($this->session(2, 2, 1))->get('/user/report_total_job_pending');
        $response->assertStatus(200);
        // The 1-day-old row is within the default one-month window, the 40-day-old row is not: bucket 3 counts 1.
        self::assertStringContainsString(
            '<td data-col="Detail">Pending for customer to pick up</td><td data-col="Job">1</td>',
            $response->getBody(),
        );
        // The defaulted range is echoed back into the filter form.
        $today = new \DateTimeImmutable('today');
        self::assertStringContainsString('value="' . $today->modify('-1 month')->format('d/m/Y') . '"', $response->getBody());
        self::assertStringContainsString('value="' . $today->format('d/m/Y') . '"', $response->getBody());
    }

    public function testRatingsDefaultsToLastMonthAndPrefillsForm(): void
    {
        // AC-1: no dates -> window today-1month..today. add_id 6 gets one rating inside, one outside.
        $today = new \DateTimeImmutable('today');
        $this->db->table('rating')->insertBatch([
            ['add_id' => 6, 'rating' => 5, 'order_id' => 'WP06R-IN', 'branchID' => 1, 'cdate' => $today->format('Y-m-d H:i:s')],
            ['add_id' => 6, 'rating' => 5, 'order_id' => 'WP06R-OUT', 'branchID' => 1, 'cdate' => $today->modify('-40 days')->format('Y-m-d H:i:s')],
        ]);

        $response = $this->withSession($this->session(2, 2, 1))->get('/user/report');
        $response->assertStatus(200);
        // Only the in-window rating counts -> question 6 total 1 (not the whole-table 2).
        self::assertStringContainsString('data-question="6" data-total="1"', $response->getBody());
        // The defaulted range is echoed back into the filter form as dd/mm/yyyy.
        self::assertStringContainsString('value="' . $today->modify('-1 month')->format('d/m/Y') . '"', $response->getBody());
        self::assertStringContainsString('value="' . $today->format('d/m/Y') . '"', $response->getBody());
    }

    public function testRatingsDefaultsEachDateFieldIndependently(): void
    {
        // AC-2: each omitted field defaults on its own (start -> today-1month, end -> today), matching CI3.
        $today = new \DateTimeImmutable('today');
        // Case A: only start_date sent -> end defaults to today, so a future rating is excluded.
        $this->db->table('rating')->insertBatch([
            ['add_id' => 7, 'rating' => 5, 'order_id' => 'WP06R-A-IN', 'branchID' => 1, 'cdate' => $today->modify('-1 day')->format('Y-m-d H:i:s')],
            ['add_id' => 7, 'rating' => 5, 'order_id' => 'WP06R-A-FUTURE', 'branchID' => 1, 'cdate' => $today->modify('+5 days')->format('Y-m-d H:i:s')],
            ['add_id' => 7, 'rating' => 5, 'order_id' => 'WP06R-A-OLD', 'branchID' => 1, 'cdate' => $today->modify('-10 days')->format('Y-m-d H:i:s')],
        ]);
        $onlyStart = $this->withSession($this->session(2, 2, 1))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => $today->modify('-3 days')->format('d/m/Y'), 'end_date' => '',
        ]);
        $onlyStart->assertStatus(200);
        // start = given (-3d) drops the -10d rating; end defaulted to today drops the +5d future rating -> total 1.
        self::assertStringContainsString('data-question="7" data-total="1"', $onlyStart->getBody());

        // Case B: only end_date sent -> start defaults to today-1month, so a 40-day-old rating is excluded.
        $this->db->table('rating')->insertBatch([
            ['add_id' => 8, 'rating' => 5, 'order_id' => 'WP06R-B-IN', 'branchID' => 1, 'cdate' => $today->modify('-5 days')->format('Y-m-d H:i:s')],
            ['add_id' => 8, 'rating' => 5, 'order_id' => 'WP06R-B-OLD', 'branchID' => 1, 'cdate' => $today->modify('-40 days')->format('Y-m-d H:i:s')],
        ]);
        $onlyEnd = $this->withSession($this->session(2, 2, 1))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '', 'end_date' => $today->format('d/m/Y'),
        ]);
        $onlyEnd->assertStatus(200);
        // start defaulted to today-1month drops the 40-day-old rating -> total 1.
        self::assertStringContainsString('data-question="8" data-total="1"', $onlyEnd->getBody());
    }

    public function testRatingsExportUsesDefaultedRangeNotWholeTable(): void
    {
        // AC-3: export with no dates yields the same one-month window as the page, not the whole table.
        $today = new \DateTimeImmutable('today');
        $this->db->table('rating')->insertBatch([
            ['add_id' => 6, 'rating' => 5, 'order_id' => 'WP06R-EXP-IN', 'branchID' => 1, 'cdate' => $today->format('Y-m-d H:i:s')],
            ['add_id' => 6, 'rating' => 5, 'order_id' => 'WP06R-EXP-OUT', 'branchID' => 1, 'cdate' => $today->modify('-40 days')->format('Y-m-d H:i:s')],
        ]);

        $export = $this->withSession($this->session(2, 2, 1))->get('/reports/ratings/export');
        $export->assertStatus(200);
        // Question 6 aggregates only the in-window rating (total 1); the whole-table total (2) must not appear.
        self::assertStringContainsString('<td>6</td><td>1</td>', $export->getBody());
        self::assertStringNotContainsString('<td>6</td><td>2</td>', $export->getBody());
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

    public function testExportRaisesMemoryLimitToConfiguredCeiling(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $response = $this->withSession($this->session(2, 2, 1))->get('/reports/tracking/export');
            $response->assertStatus(200);
            self::assertSame('8048M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
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

    public function testInProgressAverageRouteRendersGenericViewWithThaiBucketsAndTwoDecimalPercent(): void
    {
        // Branch 1 fixture: status 1=1, status 2=2 (order id 9 shares status 2), 3=1, 4=1, 5=0; total 5.
        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_in_progress_average', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $response->assertStatus(200);
        // The view emits Thai labels as numeric HTML entities; decode so assertions read as real Thai.
        $body = html_entity_decode($response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Generic view emits each bucket as data-col cells with the CI3 Thai label and number_format job count.
        self::assertStringContainsString(
            '<td data-col="Detail">เปิดงานซ่อม รอศูนย์บริการมารับ</td><td data-col="Job">1</td>',
            $body,
        );
        // Order id 9 (branch 1, status 2) is counted: this kind has no INNER JOIN, so status 2 = 2 jobs.
        self::assertStringContainsString(
            '<td data-col="Detail">สินค้าจัดส่งเข้าศูนย์บริการ</td><td data-col="Job">2</td>',
            $body,
        );
        // Percent is number_format(p, 2): 40.00% (not round's "40"); the TOTAL row sums to 100.00%.
        self::assertStringContainsString('40.00%', $body);
        self::assertStringContainsString('100.00%', $body);
    }

    public function testInProgressStatusFilterAcceptsArrayAndCsvIdentically(): void
    {
        // status 2 -> order id 2, status 4 -> order id 4 (both branch 1, date_complete NULL).
        $array = $this->withSession($this->session(1, 1, null))->post('/user/report_in_progress_job', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026', 'status_id' => ['2', '4'],
        ]);
        $csv = $this->withSession($this->session(1, 1, null))->post('/user/report_in_progress_job', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026', 'status_id' => '2,4',
        ]);

        foreach ([$array, $csv] as $response) {
            $response->assertStatus(200);
            $response->assertSee('WP00C-REPORT-002');
            $response->assertSee('WP00C-REPORT-004');
            $response->assertDontSee('WP00C-REPORT-001');
            $response->assertDontSee('WP00C-REPORT-003');
            $response->assertDontSee('WP00C-REPORT-005');
        }
    }

    public function testInProgressExportLinkCarriesStatusIdAndExportMatchesScreen(): void
    {
        $page = $this->withSession($this->session(1, 1, null))->post('/user/report_in_progress_job', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026', 'status_id' => ['2', '4'],
        ]);
        $page->assertStatus(200);
        // Export link carries the active status filter (comma percent-encoded by http_build_query).
        self::assertStringContainsString('/reports/in-progress/export', $page->getBody());
        self::assertStringContainsString('status_id=2%2C4', $page->getBody());

        // Export returns the same filtered rows as the screen, with screen column names as XLS headers.
        $export = $this->withSession($this->session(1, 1, null))
            ->get('/reports/in-progress/export?start_date=01/08/2026&end_date=31/08/2026&status_id=2,4');
        $export->assertStatus(200);
        $export->assertSee('WP00C-REPORT-002');
        $export->assertSee('WP00C-REPORT-004');
        $export->assertDontSee('WP00C-REPORT-001');
        $export->assertDontSee('WP00C-REPORT-003');
        self::assertStringContainsString('Request Date', $export->getBody());
    }

    public function testJobsByDayRouteRendersGridWithScopedBucketColumns(): void
    {
        // Base fixture is all waranty_cmg 'IN' (excluded); add one completed UNW job in branch 1,
        // brand 1 x type 1, diff 31 days (date_repair -> date_complete) -> the 31-45 column.
        $job = $this->order(300, 1, 1, 1, 1);
        $job['trackID'] = 'WP06A-BYDAY';
        $job['requestDate'] = '2026-08-10 10:00:00';
        $job['waranty_cmg'] = 'UNW';
        $job['date_repair'] = '2026-08-01 00:00:00';
        $job['date_repair_waranty'] = null;
        $job['date_complete'] = '2026-09-01 00:00:00';
        $this->db->table('request_order')->insert($job);

        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_byday', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $response->assertStatus(200);
        // Generic view emits the CI3 bucket columns as data-col cells; the single job counts in 31-45.
        self::assertStringContainsString('data-col="31-45"', $response->getBody());
        self::assertStringContainsString('<td data-col="31-45">1</td>', $response->getBody());
        $response->assertDontSee('WP00C-REPORT-005');
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
