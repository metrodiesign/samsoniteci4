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

    /** @var list<array{userId: int, role: int, branchId: int|null}> */
    private array $dashboardActors;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'branch_type' => 'branch_type_id INTEGER PRIMARY KEY, branch_type_details VARCHAR(250), branch_type_image VARCHAR(250)',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER, branch_user_name VARCHAR(100), branch_name VARCHAR(250)',
            'statusaction' => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(250), status_name_th VARCHAR(250)',
            'brand' => 'brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250)',
            'type' => 'type_id INTEGER PRIMARY KEY, type_details VARCHAR(250)',
            'condition' => 'condition_id INTEGER PRIMARY KEY, condition_details VARCHAR(250)',
            'estimateprice' => 'estimateprice_id INTEGER PRIMARY KEY, estimateprice_details VARCHAR(250)',
            'fixed' => 'fixed_id INTEGER PRIMARY KEY, fixed_details VARCHAR(250)',
            'request_order' => 'request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderID VARCHAR(100), orderIDShow VARCHAR(100), number_cmg VARCHAR(100), detailAgent INTEGER, customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailSKUName VARCHAR(100), detailBrandId INTEGER, detailTypeId INTEGER, branchID INTEGER, action_status INTEGER, date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, provider_id INTEGER, logistics_etc_detail TEXT, RepairPrice DECIMAL(8,2), waranty_cmg VARCHAR(100), detailNumberWaranty VARCHAR(100), detailEquipment TEXT, detailNote TEXT, detailCondition VARCHAR(250), detailConditionOther VARCHAR(250), detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250), detailFixed VARCHAR(250), detailFixedOther VARCHAR(250)',
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
        $hash = password_hash('pass', PASSWORD_DEFAULT);
        $this->adminId = $users->create('report-admin@example.invalid', $hash, 1, null);
        $this->branchUserId = $users->create('report-branch@example.invalid', $hash, 2, 1);
        $this->dashboardActors = [
            ['userId' => $this->adminId, 'role' => 1, 'branchId' => null],
            ['userId' => $users->create('report-admin-branch@example.invalid', $hash, 1, 1), 'role' => 1, 'branchId' => 1],
            ['userId' => $this->branchUserId, 'role' => 2, 'branchId' => 1],
            ['userId' => $users->create('report-branch-two@example.invalid', $hash, 2, 2), 'role' => 2, 'branchId' => 2],
            ['userId' => $users->create('report-viewer@example.invalid', $hash, 3, 1), 'role' => 3, 'branchId' => 1],
            ['userId' => $users->create('report-viewer-two@example.invalid', $hash, 3, 2), 'role' => 3, 'branchId' => 2],
        ];
    }

    public function testDashboardTotalsUseSessionBranchAndBranchTypeBackground(): void
    {
        // Keep the legacy method name because the WP00C closure catalog points at it.
        $reports = [['label' => 'REPORTS', 'href' => '/ReportTrackingListing']];
        $cases = [
            ['groupId' => 3, 'includeGroup' => true, 'expected' => [
                ['label' => 'UPLOAD STATUS', 'href' => '/UploadexcelListing'],
                ['label' => 'UPLOAD CMG DATA', 'href' => '/UploadneworderexcelListing'],
                ...$reports,
            ]],
            ['groupId' => 4, 'includeGroup' => true, 'expected' => [
                ['label' => '1. NEW REQUEST REPAIR', 'href' => '/ordersListing'],
                ['label' => '2. LOGISTICS', 'href' => '/sendorderListing'],
                ['label' => '3. DELIVER TO CUSTOMER', 'href' => '/TrackingreturnListing'],
                ['label' => '4. COMPLETE FEEDBACK', 'href' => '/TrackingcompleteListing'],
                ...$reports,
            ]],
            ['groupId' => 1, 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => 2, 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => null, 'includeGroup' => false, 'expected' => $reports],
            ['groupId' => null, 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => 0, 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => -1, 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => 'malformed', 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => ['3'], 'includeGroup' => true, 'expected' => $reports],
            ['groupId' => PHP_INT_MAX, 'includeGroup' => true, 'expected' => $reports],
        ];

        foreach ($this->dashboardActors as $actor) {
            foreach ($cases as $case) {
                $session = $this->dashboardSession($actor, $case['groupId'], $case['includeGroup']);
                $response = $this->withSession($session)->get('/dashboard');
                $response->assertStatus(200);
                self::assertSame($case['expected'], $this->dashboardTiles($response->getBody()));
                self::assertStringNotContainsString('data-status=', $response->getBody());
                self::assertStringNotContainsString('data-count=', $response->getBody());
                self::assertStringNotContainsString('data-background=', $response->getBody());
            }
        }
    }

    public function testDashboardQueriesOnlyTheCi3BranchScopedStaleNewOrderIndicator(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Dashboard.php');
        $store = (string) file_get_contents(APPPATH . 'Orders/OrderStore.php');

        self::assertStringContainsString('OrderStore', $controller);
        self::assertStringContainsString('$groupId === 4', $controller);
        self::assertStringContainsString('$branchId !== null', $controller);
        self::assertStringContainsString('staleNewOrderCount', $store);
        self::assertStringContainsString("->where('branchID', \$branchId)", $store);
        self::assertStringContainsString("->where('action_status', 1)", $store);
        self::assertStringNotContainsString('TrackingReport', $controller);
        self::assertStringNotContainsString('background', $controller);
    }

    public function testDashboardEscapesTheCi3ModalFlashMessage(): void
    {
        $body = view('dashboard', [
            'GroupID' => 4,
            'day_job_newover' => 1,
            'successMessage' => '<img src=x onerror=alert(1)>',
        ]);

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
        self::assertStringNotContainsString('<img src=x', $body);
    }

    public function testDashboardUsesCi3SmallBoxMarkupAndDefinedRoutes(): void
    {
        $response = $this->withSession($this->dashboardSession($this->dashboardActors[2], 4, true))->get('/dashboard');
        $response->assertStatus(200);
        $body = $response->getBody();
        self::assertStringContainsString('<div class="content-dashbord">', $body);
        self::assertSame(1, substr_count($body, '<div class="content-wrapper">'));
        self::assertSame(5, substr_count($body, 'class="small-box-footer"'));
        self::assertSame(5, substr_count($body, '<div class="small-box '));
        self::assertStringContainsString('class="ion ion-bag"', $body);
        self::assertStringContainsString('class="ion ion-stats-bars"', $body);
        self::assertSame(2, substr_count($body, 'class="ion ion-pie-graph"'));

        foreach (array_column($this->dashboardTiles($body), 'href') as $href) {
            self::assertTrue($this->routeIsDefined($href), 'Dashboard route is missing: ' . $href);
        }
    }

    public function testJobsByDayRedirectsAnonymousRequestsLikeCi3(): void
    {
        $get = $this->withSession([])->get('/user/report_job_byday');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/user/report_job_byday', [
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');
    }

    public function testPendingRedirectsAnonymousRequestsLikeCi3(): void
    {
        $get = $this->withSession([])->get('/user/report_job_pending');
        $get->assertStatus(307);
        $get->assertRedirectTo('/login');

        $post = $this->withSession([])->post('/user/report_job_pending', [
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $post->assertStatus(303);
        $post->assertRedirectTo('/login');
    }

    public function testRatingReportUsesItsDedicatedByteIdenticalCi3Target(): void
    {
        $target = APPPATH . 'Views/ci3/report.php';
        self::assertFileExists($target);
        // application/views/report.php at the pinned CI3 authority ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6.
        self::assertSame(
            '10607fcc11dc40572b3eed08cbcce4182457bd8ce54a9c2eee6c698c8d75049b',
            hash_file('sha256', $target),
        );

        $response = $this->withSession($this->session(1, 1, null))->get('/user/report');
        $response->assertStatus(200);
        $body = $response->getBody();
        self::assertStringContainsString('class="content-table-form container-content"', $body);
        self::assertStringContainsString('class="x_panel tile fixed_height_320"', $body);
        self::assertStringContainsString('action="http://example.invalid/user/report"', $body);
        self::assertStringNotContainsString('data-question=', $body);
    }

    public function testAllLegacyHtmlReportRoutesUseExactScopedMatrixTotals(): void
    {
        $routes = [
            // Headings are CI3's own wording; `KPI` covers three of the six pages there.
            '/user/report' => 'Rating Report', '/user/report_job_byday' => 'KPI',
            '/user/report_job_pending' => 'KPI', '/user/report_total_job_pending' => 'KPI',
            '/user/report_in_progress_average' => 'In Progress Report',
            '/user/report_in_progress_job' => 'In Progress Report',
        ];
        foreach ($routes as $route => $heading) {
            $response = $this->withSession($this->session(2, 2, 1))->get($route);
            $response->assertStatus(200);
            $response->assertSee($heading);
            $response->assertDontSee('WP00C-REPORT-005');
        }
        $ratings = $this->withSession($this->session(2, 2, 1))->get('/user/report');
        self::assertSame([2], $this->ratingTotals($ratings->getBody()));
        self::assertStringContainsString('/user/excel_ratings/1/', $ratings->getBody());
        $ratings->assertSee('50.00%');

        $inProgress = $this->withSession($this->session(2, 2, 1))->get('/user/report_in_progress_job');
        self::assertStringContainsString('/user/excel_in_progress_job?branchId=1', $inProgress->getBody());

        $filtered = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        self::assertSame(1, $this->ratingTotals($filtered->getBody())[0]);
        $filtered->assertSee('100.00%');
    }

    public function testEveryMatrixReportShowsTheActorAppropriateBranchControl(): void
    {
        $routes = [
            '/user/report', '/user/report_job_byday', '/user/report_job_pending',
            '/user/report_total_job_pending', '/user/report_in_progress_average',
            '/user/report_in_progress_job',
        ];
        foreach ($routes as $route) {
            $central = $this->withSession($this->session(1, 1, null))->get($route);
            $central->assertStatus(200);
            $body = $central->getBody();
            self::assertMatchesRegularExpression('/<select(?=[^>]*id="branch_id")(?=[^>]*name="branch_id")[^>]*>/s', $body);
            self::assertMatchesRegularExpression('/<option value="0">\s*ALL\s*<\/option>/s', $body);
            self::assertMatchesRegularExpression('/<option value="1"[^>]*>\s*BRANCH A,branch-a\s*<\/option>/s', $body);
            self::assertMatchesRegularExpression('/<option value="2"[^>]*>\s*BRANCH B,branch-b\s*<\/option>/s', $body);

            $branch = $this->withSession($this->session(2, 2, 1))->get($route);
            $branch->assertStatus(200);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*type="hidden")(?=[^>]*name="branch_id")(?=[^>]*value="1")[^>]*>/s',
                (string) $branch->getBody(),
            );
            self::assertDoesNotMatchRegularExpression(
                '/<select(?=[^>]*id="branch_id")(?=[^>]*name="branch_id")[^>]*>/s',
                (string) $branch->getBody(),
            );
        }
    }

    public function testMatrixBranchSelectionPersistsFiltersDataAndFlowsIntoExports(): void
    {
        $ratings = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $ratings->assertStatus(200);
        self::assertStringContainsString('<option value="2" selected>BRANCH B,branch-b</option>', $ratings->getBody());
        self::assertSame(1, $this->ratingTotals($ratings->getBody())[0]);
        self::assertStringContainsString('/user/excel_ratings/2/01-08-2026/31-08-2026', $ratings->getBody());

        $inProgress = $this->withSession($this->session(1, 1, null))->post('/user/report_in_progress_job', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026', 'status_id' => ['5'],
        ]);
        $inProgress->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<option value="2"[^>]*selected[^>]*>\s*BRANCH B,branch-b\s*<\/option>/s',
            (string) $inProgress->getBody(),
        );
        $inProgress->assertSee('WP00C-REPORT-005');
        $inProgress->assertDontSee('WP00C-REPORT-001');
        // CI3 report_in_progress_job.php builds this query from the session BranchID;
        // a central actor therefore keeps branchId empty even after selecting branch 2.
        self::assertStringContainsString('/user/excel_in_progress_job?branchId=', $inProgress->getBody());
        self::assertStringNotContainsString('/user/excel_in_progress_job?branchId=2', $inProgress->getBody());
        self::assertStringContainsString('status=5', $inProgress->getBody());
    }

    public function testBranchUserCannotRequestAnotherBranchInProtectedMatrixOrExport(): void
    {
        // report_job_pending is deliberately excluded: CI3 trusts its posted branch_id,
        // which is locked by testPendingHonoursTamperedBranchLikeCi3().
        foreach ([
            '/user/report', '/user/report_job_byday', '/user/report_total_job_pending',
            '/user/report_in_progress_average', '/user/report_in_progress_job',
        ] as $route) {
            try {
                $this->withSession($this->session(2, 2, 1))->post($route, [
                    'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
                ]);
                self::fail('Expected cross-branch matrix denial: ' . $route);
            } catch (PageNotFoundException $exception) {
                self::assertSame(404, $exception->getCode());
            }
        }
        foreach (['ratings', 'in-progress'] as $type) {
            try {
                $this->withSession($this->session(2, 2, 1))->get('/reports/' . $type . '/export?branch_id=2');
                self::fail('Expected cross-branch export denial: ' . $type);
            } catch (PageNotFoundException $exception) {
                self::assertSame(404, $exception->getCode());
            }
        }
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
        self::assertMatchesRegularExpression(
            '/<td>\s*Pending for customer to pick up\s*<\/td>\s*<td align="right">\s*1\s*<\/td>/s',
            (string) $response->getBody(),
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

        $response = $this->withSession($this->session(1, 1, null))->get('/user/report');
        $response->assertStatus(200);
        // Only the in-window rating counts -> question 6 total 1 (not the whole-table 2).
        self::assertSame(1, $this->ratingTotals($response->getBody())[5]);
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
        $onlyStart = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => $today->modify('-3 days')->format('d/m/Y'), 'end_date' => '',
        ]);
        $onlyStart->assertStatus(200);
        // start = given (-3d) drops the -10d rating; end defaulted to today drops the +5d future rating -> total 1.
        self::assertSame(1, $this->ratingTotals($onlyStart->getBody())[6]);

        // Case B: only end_date sent -> start defaults to today-1month, so a 40-day-old rating is excluded.
        $this->db->table('rating')->insertBatch([
            ['add_id' => 8, 'rating' => 5, 'order_id' => 'WP06R-B-IN', 'branchID' => 1, 'cdate' => $today->modify('-5 days')->format('Y-m-d H:i:s')],
            ['add_id' => 8, 'rating' => 5, 'order_id' => 'WP06R-B-OLD', 'branchID' => 1, 'cdate' => $today->modify('-40 days')->format('Y-m-d H:i:s')],
        ]);
        $onlyEnd = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(),
            'start_date' => '', 'end_date' => $today->format('d/m/Y'),
        ]);
        $onlyEnd->assertStatus(200);
        // start defaulted to today-1month drops the 40-day-old rating -> total 1.
        self::assertSame(1, $this->ratingTotals($onlyEnd->getBody())[7]);
    }

    public function testLegacyRatingsExportUsesSingleSlashCallerAndCi3Headers(): void
    {
        $page = $this->withSession($this->session(1, 1, null))->post('/user/report', [
            'csrf_test_name' => service('security')->getHash(),
            'branch_id' => '0', 'start_date' => '30/07/2026', 'end_date' => '30/08/2026',
        ]);
        $page->assertStatus(200);
        self::assertStringContainsString(
            '/user/excel_ratings/0/30-07-2026/30-08-2026',
            $page->getBody(),
        );
        self::assertStringNotContainsString('/user/excel_ratings//', $page->getBody());

        $export = $this->withSession($this->session(1, 1, null))
            ->get('/user/excel_ratings/0/30-07-2026/30-08-2026');
        $export->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/\Aapplication\/x-msexcel; name="Rating_Report_[0-9]+\.xls"\z/',
            $export->response()->getHeaderLine('Content-Type'),
        );
        self::assertMatchesRegularExpression(
            '/\Ainline; filename="Rating_Report_[0-9]+\.xls"\z/',
            $export->response()->getHeaderLine('Content-Disposition'),
        );
        self::assertSame('no-cache', $export->response()->getHeaderLine('Pragma'));
        $export->assertSee('Rating Report');
        $export->assertSee('WP00C-REPORT-001');
        self::assertStringContainsString(
            '<table x:str',
            $export->getBody(),
        );
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
        self::assertSame(8, $this->summaryRowCount((string) $all->getBody()));
        foreach (['searchText', 'sdate', 'edate', 'status_id', 'detailBrandId', 'detailTypeId'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $all->getBody());
        }
        self::assertStringContainsString('/order/excel_report/', $all->getBody());
        $all->assertDontSee('WP00C-REPORT-MISSING');

        $filtered = $this->withSession($this->session(1, 1, null))->post('/reportsummary', [
            'csrf_test_name' => service('security')->getHash(), 'status_id' => '2',
            'detailBrandId' => '2', 'detailTypeId' => '2', 'sdate' => '01/08/2026', 'edate' => '31/08/2026',
        ]);
        $filtered->assertStatus(200);
        self::assertStringContainsString('WP00C-REPORT-002', (string) $filtered->getBody());
        self::assertStringNotContainsString('WP00C-REPORT-004', (string) $filtered->getBody());

        $branch = $this->withSession($this->session(2, 2, 1))->get('/reportsummary');
        self::assertSame(4, $this->summaryRowCount((string) $branch->getBody()));
        self::assertStringNotContainsString('WP00C-REPORT-005', (string) $branch->getBody());
    }

    public function testTrackingSummaryRatingsAndInProgressExportsHaveStableXlsContract(): void
    {
        $trackingPage = $this->withSession($this->session(2, 2, 1))->get('/ReportTrackingListing');
        self::assertStringContainsString('/order/excel_report/', $trackingPage->getBody());

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
            $expectedDisposition = $path === '/user/excel_ratings'
                ? 'inline; filename="Rating_Report_'
                : 'attachment; filename=';
            self::assertStringContainsString(
                $expectedDisposition,
                $legacy->response()->getHeaderLine('Content-Disposition'),
            );
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
        self::assertSame(8, $this->summaryRowCount((string) $empty->getBody()));
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
        self::assertSame(100, $this->summaryRowCount((string) $first->getBody()));
        self::assertStringContainsString('WP00C-PAGE-110', (string) $first->getBody());
        self::assertStringNotContainsString('WP00C-PAGE-010', (string) $first->getBody());

        $second = $this->withSession($this->session(1, 1, null))->get('/reportsummary/100');
        $second->assertStatus(200);
        self::assertSame(9, $this->summaryRowCount((string) $second->getBody()));
        self::assertStringContainsString('WP00C-PAGE-010', (string) $second->getBody());
        self::assertStringNotContainsString('WP00C-PAGE-110', (string) $second->getBody());

        $filteredFirst = $this->withSession($this->session(1, 1, null))->get('/reportsummary?searchText=WP00C-PAGE');
        self::assertSame(100, $this->summaryRowCount((string) $filteredFirst->getBody()));
        self::assertStringContainsString('baseURL + "reportsummary/" + value', (string) $filteredFirst->getBody());
        $filteredSecond = $this->withSession($this->session(1, 1, null))->get('/reportsummary/100?searchText=WP00C-PAGE');
        self::assertSame(1, $this->summaryRowCount((string) $filteredSecond->getBody()));
        self::assertStringContainsString('WP00C-PAGE-010', (string) $filteredSecond->getBody());
        self::assertStringNotContainsString('WP00C-PAGE-110', (string) $filteredSecond->getBody());
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
        self::assertMatchesRegularExpression(
            '/<td align="left">\s*เปิดงานซ่อม รอศูนย์บริการมารับ\s*<\/td>\s*<td align="right">\s*1\s*<\/td>/s',
            $body,
        );
        // Order id 9 (branch 1, status 2) is counted: this kind has no INNER JOIN, so status 2 = 2 jobs.
        self::assertMatchesRegularExpression(
            '/<td align="left">\s*สินค้าจัดส่งเข้าศูนย์บริการ\s*<\/td>\s*<td align="right">\s*2\s*<\/td>/s',
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
        self::assertStringContainsString('/user/excel_in_progress_job?', $page->getBody());
        self::assertStringContainsString('status=2%2C4', $page->getBody());

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

    public function testJobsByDayReplicatesCi3Diff31BranchGap(): void
    {
        // CI3 counts diff 31 in 31-45 for ALL branches, but its branch-scoped query uses > 31
        // and therefore drops the same job from every bucket.
        $job = $this->order(300, 1, 1, 1, 1);
        $job['trackID'] = 'WP06A-BYDAY-DIFF31';
        $job['requestDate'] = '2026-08-10 00:00:00';
        $job['waranty_cmg'] = 'UNW';
        $job['date_repair'] = '2026-08-01 00:00:00';
        $job['date_repair_waranty'] = null;
        $job['date_complete'] = '2026-09-01 00:00:00';
        $this->db->table('request_order')->insert($job);

        $allBranches = $this->withSession($this->session(1, 1, null))->post('/user/report_job_byday', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '0',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $allBranches->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<td>\s*BRAND A\s*<\/td>\s*<td>\s*TYPE A\s*<\/td>'
                . '\s*<td>\s*0\s*<\/td>\s*<td>\s*0\s*<\/td>\s*<td>\s*0\s*<\/td>'
                . '\s*<td>\s*1\s*<\/td>\s*<td>\s*0\s*<\/td>/s',
            (string) $allBranches->getBody(),
        );

        $branch = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_byday', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '1',
            'start_date' => '01/08/2026', 'end_date' => '31/08/2026',
        ]);
        $branch->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<td>\s*BRAND A\s*<\/td>\s*<td>\s*TYPE A\s*<\/td>'
                . '(?:\s*<td>\s*0\s*<\/td>){5}/s',
            (string) $branch->getBody(),
        );
    }

    public function testJobsByDayReplicatesCi3EndDateMidnightCutoff(): void
    {
        foreach ([301 => '2026-08-31 00:00:00', 302 => '2026-08-31 12:00:00'] as $id => $requestDate) {
            $job = $this->order($id, 1, 1, 1, 1);
            $job['trackID'] = 'WP06A-BYDAY-END-' . $id;
            $job['requestDate'] = $requestDate;
            $job['waranty_cmg'] = 'UNW';
            $job['date_repair'] = '2026-08-01 00:00:00';
            $job['date_repair_waranty'] = null;
            $job['date_complete'] = '2026-08-02 00:00:00';
            $this->db->table('request_order')->insert($job);
        }

        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_byday', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '1',
            'start_date' => '31/08/2026', 'end_date' => '31/08/2026',
        ]);

        $response->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<td>\s*BRAND A\s*<\/td>\s*<td>\s*TYPE A\s*<\/td>'
                . '\s*<td>\s*0\s*<\/td>\s*<td>\s*1\s*<\/td>'
                . '(?:\s*<td>\s*0\s*<\/td>){3}/s',
            (string) $response->getBody(),
        );
    }

    public function testJobsByDayRejectsLeadingWarrantyWhitespaceLikeCi3(): void
    {
        foreach ([303 => 'OUT', 304 => ' OUT'] as $id => $warranty) {
            $job = $this->order($id, 1, 1, 1, 1);
            $job['trackID'] = 'WP06A-BYDAY-WARRANTY-' . $id;
            $job['requestDate'] = '2026-08-15 00:00:00';
            $job['waranty_cmg'] = $warranty;
            $job['date_repair'] = '2026-01-01 00:00:00';
            $job['date_repair_waranty'] = '2026-08-01 00:00:00';
            $job['date_complete'] = '2026-08-03 00:00:00';
            $this->db->table('request_order')->insert($job);
        }

        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_byday', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '1',
            'start_date' => '15/08/2026', 'end_date' => '15/08/2026',
        ]);

        $response->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<td>\s*BRAND A\s*<\/td>\s*<td>\s*TYPE A\s*<\/td>'
                . '\s*<td>\s*0\s*<\/td>\s*<td>\s*1\s*<\/td>'
                . '(?:\s*<td>\s*0\s*<\/td>){3}/s',
            (string) $response->getBody(),
        );
    }

    public function testPendingIgnoresGetFiltersAndUsesCi3DefaultRange(): void
    {
        $today = new \DateTimeImmutable('today');
        $job = $this->order(307, 1, 2, 1, 1);
        $job['trackID'] = 'WP06A-PENDING-GET-IGNORED';
        $job['date_repair'] = $today->modify('-5 days')->format('Y-m-d 00:00:00');
        $job['date_complete'] = null;
        $this->db->table('request_order')->insert($job);

        $query = http_build_query([
            'branch_id' => '2',
            'start_date' => $today->modify('-2 months')->format('d/m/Y'),
            'end_date' => $today->modify('-2 months')->format('d/m/Y'),
        ]);
        $response = $this->withSession($this->session(1, 1, null))
            ->get('/user/report_job_pending?' . $query);

        $response->assertStatus(200);
        $response->assertSee('WP06A-PENDING-GET-IGNORED');
        self::assertMatchesRegularExpression(
            '/name="start_date" value="' . preg_quote($today->modify('-1 month')->format('d/m/Y'), '/') . '"/',
            (string) $response->getBody(),
        );
        self::assertMatchesRegularExpression(
            '/name="end_date" value="' . preg_quote($today->format('d/m/Y'), '/') . '"/',
            (string) $response->getBody(),
        );
    }

    public function testPendingReturnsEmptyReportForMalformedDatesLikeCi3(): void
    {
        $response = $this->withSession($this->session(1, 1, null))->post('/user/report_job_pending', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '0',
            'start_date' => 'not-a-date', 'end_date' => 'not-a-date',
        ]);

        $response->assertStatus(200);
        self::assertMatchesRegularExpression(
            '/<td>\s*TOTAL\s*<\/td>\s*<td>\s*0\s*<\/td>/s',
            (string) $response->getBody(),
        );
        $response->assertDontSee('WP00C-REPORT-001');
    }

    public function testPendingHonoursTamperedBranchLikeCi3(): void
    {
        $job = $this->order(308, 2, 2, 1, 1);
        $job['trackID'] = 'WP06A-PENDING-CROSS-BRANCH';
        $job['date_repair'] = '2026-08-15 00:00:00';
        $job['date_complete'] = null;
        $this->db->table('request_order')->insert($job);

        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_pending', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '2',
            'start_date' => '15/08/2026', 'end_date' => '15/08/2026',
        ]);

        $response->assertStatus(200);
        $response->assertSee('WP06A-PENDING-CROSS-BRANCH');
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*type="hidden")(?=[^>]*name="branch_id")(?=[^>]*value="1")[^>]*>/s',
            (string) $response->getBody(),
        );
    }

    public function testPendingReplicatesCi3EndDateMidnightCutoff(): void
    {
        foreach ([305 => '2026-08-31 00:00:00', 306 => '2026-08-31 12:00:00'] as $id => $repairDate) {
            $job = $this->order($id, 1, 2, 1, 1);
            $job['trackID'] = 'WP06A-PENDING-END-' . $id;
            $job['date_repair'] = $repairDate;
            $job['date_complete'] = null;
            $this->db->table('request_order')->insert($job);
        }

        $response = $this->withSession($this->session(2, 2, 1))->post('/user/report_job_pending', [
            'csrf_test_name' => service('security')->getHash(), 'branch_id' => '1',
            'start_date' => '31/08/2026', 'end_date' => '31/08/2026',
        ]);

        $response->assertStatus(200);
        $response->assertSee('WP06A-PENDING-END-305');
        $response->assertDontSee('WP06A-PENDING-END-306');
    }

    public function testTrackingCmgColumnShownOnlyForCentralActor(): void
    {
        $central = $this->withSession($this->session(1, 1, null))->get('/ReportTrackingListing');
        $central->assertStatus(200);
        $central->assertSee('CMG TotalDay');

        $branch = $this->withSession($this->session(2, 2, 1))->get('/ReportTrackingListing/0/1');
        $branch->assertStatus(200);
        $branch->assertDontSee('CMG TotalDay');
    }

    /**
     * @param array{userId: int, role: int, branchId: int|null} $actor
     * @return array<string, mixed>
     */
    private function dashboardSession(array $actor, mixed $groupId, bool $includeGroup): array
    {
        $session = [
            'userId' => $actor['userId'], 'role' => $actor['role'], 'BranchID' => $actor['branchId'],
            'sessionVersion' => 1, 'isLoggedIn' => true, 'name' => 'DASHBOARD ACTOR',
        ];
        if ($includeGroup) {
            $session['GroupID'] = $groupId;
        }

        return $session;
    }

    /** @return list<array{label: string, href: string}> */
    private function dashboardTiles(string $body): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        self::assertTrue($document->loadHTML($body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $anchors = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " small-box-footer ")]');
        self::assertNotFalse($anchors);
        self::assertGreaterThan(0, $anchors->length);

        $tiles = [];
        foreach ($anchors as $anchor) {
            self::assertInstanceOf(\DOMElement::class, $anchor);
            $headings = $xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " small-box ")]/div[@class="inner"]/h2', $anchor);
            self::assertNotFalse($headings);
            self::assertSame(1, $headings->length, 'Dashboard tile hierarchy differs from CI3.');
            $href = html_entity_decode($anchor->getAttribute('href'));
            $path = parse_url($href, PHP_URL_PATH);
            $tiles[] = [
                'label' => trim((string) $headings->item(0)?->textContent),
                'href' => is_string($path) ? $path : $href,
            ];
        }

        return $tiles;
    }

    private function routeIsDefined(string $path): bool
    {
        $collection = service('routes');
        $router = new \CodeIgniter\Router\Router($collection, service('request'));
        $collection->setHTTPVerb('GET');
        try {
            $router->handle(ltrim($path, '/'));

            return true;
        } catch (PageNotFoundException) {
            return false;
        }
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

    private function summaryRowCount(string $html): int
    {
        return preg_match_all('/<td>\s*WP00C-(?:REPORT|PAGE)-[^<]+\s*<\/td>/s', $html);
    }

    /** @return list<int> */
    private function ratingTotals(string $html): array
    {
        preg_match_all('/<h5>Total ([0-9,]+)<\\/h5>/', $html, $matches);

        return array_map(static fn (string $total): int => (int) str_replace(',', '', $total), $matches[1]);
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
