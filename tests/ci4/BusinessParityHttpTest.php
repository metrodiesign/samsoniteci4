<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Authentication\LegacyUserImporter;
use App\Reporting\TrackingReport;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

final class BusinessParityHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLegacyTables();
        $this->db->table('ci4_users')->truncate();
        $this->seedLegacyUsers();
        (new LegacyUserImporter($this->db))->import();
        $this->seedReportMatrix();
    }

    public function testBusinessEntryRoutesAreExplicitAndProtected(): void
    {
        $this->get('/login')->assertStatus(200);
        $this->get('/dashboard')->assertRedirectTo('/login');
        $this->get('/Order/ReportTrackingListing')->assertRedirectTo('/login');
    }

    public function testLegacyTestRouteIsAbsent(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);

        $this->get('/Order/ReportTrackingListingTest');
    }

    public function testStaleWebSessionReturnsLoginWithoutRedirectLoop(): void
    {
        $this->withSession([
            'userId'         => 999999,
            'role'           => 1,
            'BranchID'       => null,
            'sessionVersion' => 1,
            'isLoggedIn'     => true,
        ])->get('/dashboard')->assertRedirectTo('/login');

        $this->get('/login')->assertStatus(200);
    }

    public function testValidLoginPreservesSessionContractAndWritesHistory(): void
    {
        $result = $this->postWithCsrf('/loginMe', [
            'username' => 'wp00c-admin',
            'password' => 'Synthetic login passphrase',
        ]);

        $result->assertRedirectTo('/dashboard');

        $session = service('session');
        self::assertSame(9001, $session->get('userId'));
        self::assertSame(1, $session->get('role'));
        self::assertSame(1, $session->get('GroupID'));
        self::assertNull($session->get('BranchID'));
        self::assertSame('Admin', $session->get('roleText'));
        self::assertSame('SYNTHETIC ADMIN', $session->get('name'));
        self::assertIsString($session->get('lastLogin'));
        self::assertTrue($session->get('isLoggedIn'));
        self::assertSame(1, $session->get('sessionVersion'));
        self::assertSame(1, $this->db->table('tbl_last_login')->countAllResults());
        self::assertSame(1, $this->db->table('ci4_users')->where('id', 9001)->countAllResults());
    }

    public function testInvalidUnknownAndDeletedLoginsUseGenericFailureWithoutHistory(): void
    {
        $attempts = [
            ['username' => 'wp00c-admin', 'password' => 'wrong-password'],
            ['username' => 'unknown-user', 'password' => 'Synthetic login passphrase'],
            ['username' => 'wp00c-deleted', 'password' => 'Synthetic login passphrase'],
        ];

        foreach ($attempts as $attempt) {
            $this->postWithCsrf('/loginMe', $attempt)->assertRedirectTo('/login');
            self::assertFalse(service('session')->get('isLoggedIn') === true);
        }

        self::assertSame(0, $this->db->table('tbl_last_login')->countAllResults());
    }

    public function testLogoutDestroysSessionAndProtectedRouteReturnsLogin(): void
    {
        $this->withSession([
            'userId'         => 9001,
            'role'           => 1,
            'BranchID'       => null,
            'sessionVersion' => 1,
            'isLoggedIn'     => true,
        ]);

        $this->postWithCsrf('/logout', [])->assertRedirectTo('/login');
    }

    public function testLoginUsesCi4OwnedPasswordAfterReset(): void
    {
        $users = new ShadowUserStore($this->db);
        self::assertTrue($users->replacePasswordAndRevokeSessions(
            9001,
            password_hash('Synthetic replacement passphrase', PASSWORD_DEFAULT),
        ));
        (new LegacyUserImporter($this->db))->import();
        self::assertFalse($users->verifyPassword(9001, 'Synthetic login passphrase'));
        self::assertTrue($users->verifyPassword(9001, 'Synthetic replacement passphrase'));

        $oldPassword = $this->postWithCsrf('/loginMe', [
            'username' => 'wp00c-admin',
            'password' => 'Synthetic login passphrase',
        ]);
        $oldPassword->assertRedirectTo('/login');

        $newPassword = $this->postWithCsrf('/loginMe', [
            'username' => 'wp00c-admin',
            'password' => 'Synthetic replacement passphrase',
        ]);
        $newPassword->assertRedirectTo('/dashboard');
        self::assertSame(2, service('session')->get('sessionVersion'));
    }

    public function testLegacyIdentityImportRollsBackOnInvalidCredentialRow(): void
    {
        $this->db->table('ci4_users')->truncate();
        $this->db->table('tbl_users')->insert([
            'userId'    => 9005,
            'email'     => 'invalid-hash@example.invalid',
            'username'  => 'invalid-hash',
            'password'  => 'not-a-password-hash',
            'name'      => 'SYNTHETIC INVALID HASH',
            'group_id'  => 1,
            'roleId'    => 1,
            'branch_id' => null,
            'isDeleted' => 0,
        ]);

        try {
            (new LegacyUserImporter($this->db))->import();
            self::fail('Expected invalid legacy credential row.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $this->db->table('ci4_users')->countAllResults());
        }
    }

    public function testLoginThrottleCannotBeBypassedByRotatingUsernames(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postWithCsrf('/loginMe', [
                'username' => "unknown-{$attempt}",
                'password' => 'Synthetic invalid passphrase',
            ])->assertRedirectTo('/login');
        }

        $limited = $this->postWithCsrf('/loginMe', [
            'username' => 'another-unknown-user',
            'password' => 'Synthetic invalid passphrase',
        ]);
        $limited->assertStatus(429);
        self::assertSame(0, $this->db->table('tbl_last_login')->countAllResults());
    }

    public function testLoginWithoutCsrfIsRejectedBeforeCredentialCheck(): void
    {
        try {
            $this->post('/loginMe', [
                'username' => 'wp00c-admin',
                'password' => 'Synthetic login passphrase',
            ]);
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException $exception) {
            self::assertSame(403, $exception->getCode());
        }

        self::assertSame(0, $this->db->table('tbl_last_login')->countAllResults());
    }

    public function testC3ReportTrackingHeadersMatchCi3(): void
    {
        // reports/tracking.php: 8 column headers become CI3 tracking/report_tracking_test.php text (AC-6).
        $adminId = (new ShadowUserStore($this->db))->create(
            'report-header-admin@example.invalid',
            password_hash('Synthetic report passphrase', PASSWORD_DEFAULT),
            1,
            null,
        );

        $report = $this->withSession($this->sessionFor($adminId, 1, null))
            ->get('/Order/ReportTrackingListing');
        $report->assertStatus(200);
        $report->assertSee('เลขที่ CMG');
        $report->assertSee('รับเข้า');
        $report->assertSee('อัพเดทล่าสุด');
        $report->assertSee('ศูนย์ส่งคืนสาขา');
        $report->assertSee('ลูกค้ามารับคืน');
        $report->assertSee('Logistics');
        $report->assertSee('ราคาซ่อม');
        $report->assertSee('Warannty');                 // CI3 typo preserved
        $report->assertDontSee('<th>CMG No.</th>');
        $report->assertDontSee('<th>Warranty</th>');
        // trackID/orderID stay raw on purpose (already CI3-parity, out of scope)
        $report->assertSee('<th>trackID</th>');
    }

    public function testTrackingReportConsolidatesStatusFiltersAndSearch(): void
    {
        $adminId = (new ShadowUserStore($this->db))->create(
            'report-admin@example.invalid',
            password_hash('Synthetic report passphrase', PASSWORD_DEFAULT),
            1,
            null,
        );

        $all = $this->withSession($this->sessionFor($adminId, 1, null))
            ->post('/Order/ReportTrackingListing', [
                'csrf_test_name' => service('security')->getHash(),
                'sdate' => '01/08/2026', 'edate' => '31/08/2026',
            ]);
        $all->assertStatus(200);
        $all->assertSeeInOrder([
            'WP00C-TRACK-009',
            'WP00C-TRACK-007',
            'WP00C-TRACK-003',
            'WP00C-TRACK-002',
        ]);
        $all->assertDontSee('ReportTrackingListingTest');

        $filtered = $this->withSession($this->sessionFor($adminId, 1, null))
            ->post('/Order/ReportTrackingListing', [
                'csrf_test_name' => service('security')->getHash(),
                'status_id'      => '2,3',
                'searchText'     => 'WP00C-TRACK',
                'sdate'          => '01/08/2026',
                'edate'          => '31/08/2026',
            ]);
        $filtered->assertStatus(200);
        $filtered->assertSeeInOrder([
            'WP00C-TRACK-009',
            'WP00C-TRACK-003',
            'WP00C-TRACK-002',
        ]);
        $filtered->assertDontSee('WP00C-TRACK-007');

        $malformed = $this->withSession($this->sessionFor($adminId, 1, null))
            ->post('/Order/ReportTrackingListing', [
                'csrf_test_name' => service('security')->getHash(),
                'status_id'      => '2) OR 1=1 --',
                'sdate'          => '01/08/2026',
                'edate'          => '31/08/2026',
            ]);
        $malformed->assertStatus(200);
        self::assertStringContainsString('WP00C-TRACK-007', (string) $malformed->getBody());
    }

    public function testTrackingReportEnforcesSessionBranch(): void
    {
        $operatorId = (new ShadowUserStore($this->db))->create(
            'report-operator@example.invalid',
            password_hash('Synthetic report passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $ownBranch = $this->withSession($this->sessionFor($operatorId, 2, 1))
            ->post('/Order/ReportTrackingListing', [
                'csrf_test_name' => service('security')->getHash(),
                'sdate' => '01/08/2026', 'edate' => '31/08/2026',
            ]);
        $ownBranch->assertStatus(200);
        self::assertStringContainsString('WP00C-TRACK-003', (string) $ownBranch->getBody());
        self::assertStringNotContainsString('WP00C-TRACK-007', (string) $ownBranch->getBody());

    }

    public function testTrackingReportDeniesCrossBranchRoute(): void
    {
        $operatorId = (new ShadowUserStore($this->db))->create(
            'cross-branch-operator@example.invalid',
            password_hash('Synthetic report passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);

        $this->withSession($this->sessionFor($operatorId, 2, 1))
            ->get('/Order/ReportTrackingListing/0/2');
    }

    public function testTrackingReportPreservesRouteNumbering(): void
    {
        $adminId = (new ShadowUserStore($this->db))->create(
            'numbering-admin@example.invalid',
            password_hash('Synthetic report passphrase', PASSWORD_DEFAULT),
            1,
            null,
        );

        $numbered = $this->withSession($this->sessionFor($adminId, 1, null))
            ->post('/Order/ReportTrackingListing/25/2', [
                'csrf_test_name' => service('security')->getHash(),
                'sdate' => '01/08/2026', 'edate' => '31/08/2026',
            ]);
        $numbered->assertStatus(200);
        // CI3 report_tracking_test.php renders the route offset as the first table cell.
        self::assertMatchesRegularExpression('/<tr>\s*<td>\s*26\s*<\/td>/s', (string) $numbered->getBody());
        self::assertStringContainsString('WP00C-TRACK-009', (string) $numbered->getBody());
        self::assertStringNotContainsString('WP00C-TRACK-003', (string) $numbered->getBody());
    }

    public function testTrackingDayCountPreservesNegativeChronology(): void
    {
        $this->db->table('request_order')->insert(
            $this->order(
                91010,
                '2026-08-10 00:00:00',
                'WP00C-TRACK-010',
                1,
                7,
                '2026-08-08 00:00:00',
            ),
        );

        $rows = (new TrackingReport($this->db))->rows(
            'WP00C-TRACK-010',
            null,
            null,
            null,
            1,
        );

        self::assertCount(1, $rows);
        // Report query returns raw MySQL DATEDIFF; the CI3 view applies its own +1 for display.
        self::assertSame(-2, $rows[0]['TotalDay']);
    }

    public function testTrackingDayCountTruncatesTimeOfDayLikeDatediff(): void
    {
        // AC-4/AC-5: a sub-24h span across a date boundary counts as one calendar day (mirrors MySQL DATEDIFF).
        $row                        = $this->order(91011, '2026-01-01 15:00:00', 'WP00C-TRACK-011', 1, 7, '2026-01-02 09:00:00');
        $row['waranty_cmg']         = 'OUT';
        $row['date_repair_waranty'] = '2026-01-01 15:00:00';
        $this->db->table('request_order')->insert($row);

        $rows = (new TrackingReport($this->db))->rows('WP00C-TRACK-011', null, null, null, 1);

        self::assertCount(1, $rows);
        // requestDate -> date_complete: raw MySQL DATEDIFF is 1; the CI3 view displays +1.
        self::assertSame(1, $rows[0]['TotalDay']);
        // date_repair_waranty -> date_complete (OUT): DATEDIFF 1, no inclusive = 1.
        self::assertSame(1, $rows[0]['CMGTotalDay']);
    }

    public function testTrackingDayCountKeepsNullCompletionAndUnmatchedWarrantyZero(): void
    {
        // AC-6: no completion date keeps TotalDay null; a warranty flag outside the CMG set keeps CMGTotalDay 0.
        $row = $this->order(91012, '2026-01-01 15:00:00', 'WP00C-TRACK-012', 1, 2, null);
        // order() defaults waranty_cmg 'IN', which is outside {OUT, UNW, ''}.
        $this->db->table('request_order')->insert($row);

        $rows = (new TrackingReport($this->db))->rows('WP00C-TRACK-012', null, null, null, 1);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]['TotalDay']);
        self::assertSame(0, $rows[0]['CMGTotalDay']);
    }

    /** @param array<string, string> $payload */
    private function postWithCsrf(string $path, array $payload)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->post($path, $payload);
    }

    /** @return array<string, int|bool|null> */
    private function sessionFor(int $userId, int $role, ?int $branchId): array
    {
        return [
            'userId'        => $userId,
            'role'          => $role,
            'BranchID'      => $branchId,
            'sessionVersion' => 1,
            'isLoggedIn'    => true,
        ];
    }

    private function createLegacyTables(): void
    {
        $tables = [
            'tbl_last_login' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, userId INTEGER NOT NULL, sessionData VARCHAR(2048) NOT NULL, machineIp VARCHAR(1024) NOT NULL, userAgent VARCHAR(128) NOT NULL, agentString VARCHAR(1024) NOT NULL, platform VARCHAR(128) NOT NULL, createdDtm DATETIME NOT NULL',
            'tbl_roles'      => 'roleId INTEGER PRIMARY KEY, role VARCHAR(64) NOT NULL',
            'tbl_users'      => 'userId INTEGER PRIMARY KEY, email VARCHAR(128) NOT NULL, username VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(128), group_id INTEGER, roleId INTEGER NOT NULL, branch_id INTEGER, isDeleted INTEGER NOT NULL DEFAULT 0',
            'branch'         => 'branch_id INTEGER PRIMARY KEY, branch_user_name VARCHAR(128), branch_name VARCHAR(128) NOT NULL',
            'statusaction'   => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(128) NOT NULL, status_name_th VARCHAR(128)',
            'request_order'  => 'request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderID VARCHAR(100) NOT NULL, orderIDShow VARCHAR(100), number_cmg VARCHAR(100), detailAgent INTEGER, customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailSKUName VARCHAR(100), branchID INTEGER, action_status INTEGER, date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_deliver DATETIME, date_complete DATETIME, provider_id INTEGER, logistics_etc_detail TEXT, RepairPrice DECIMAL(8,2), waranty_cmg VARCHAR(100)',
        ];

        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("CREATE TABLE IF NOT EXISTS {$name} ({$definition})");
            $this->db->table($table)->truncate();
        }
    }

    private function seedLegacyUsers(): void
    {
        $this->db->table('tbl_roles')->insertBatch([
            ['roleId' => 1, 'role' => 'Admin'],
            ['roleId' => 2, 'role' => 'Operator'],
        ]);
        $this->db->table('tbl_users')->insertBatch([
            [
                'userId'    => 9001,
                'email'     => 'wp00c-admin@example.invalid',
                'username'  => 'wp00c-admin',
                'password'  => password_hash('Synthetic login passphrase', PASSWORD_DEFAULT),
                'name'      => 'SYNTHETIC ADMIN',
                'group_id'  => 1,
                'roleId'    => 1,
                'branch_id' => null,
                'isDeleted' => 0,
            ],
            [
                'userId'    => 9004,
                'email'     => 'wp00c-deleted@example.invalid',
                'username'  => 'wp00c-deleted',
                'password'  => password_hash('Synthetic login passphrase', PASSWORD_DEFAULT),
                'name'      => 'SYNTHETIC DELETED',
                'group_id'  => 4,
                'roleId'    => 2,
                'branch_id' => 1,
                'isDeleted' => 1,
            ],
        ]);
    }

    private function seedReportMatrix(): void
    {
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_user_name' => 'wp00c-a', 'branch_name' => 'SYNTHETIC BRANCH A'],
            ['branch_id' => 2, 'branch_user_name' => 'wp00c-b', 'branch_name' => 'SYNTHETIC BRANCH B'],
        ]);
        $this->db->table('statusaction')->insertBatch([
            ['status_id' => 2, 'status_name' => 'SYNTHETIC REQUEST', 'status_name_th' => 'สถานะทดสอบ 2'],
            ['status_id' => 3, 'status_name' => 'SYNTHETIC REPAIR', 'status_name_th' => 'สถานะทดสอบ 3'],
            ['status_id' => 7, 'status_name' => 'SYNTHETIC COMPLETED', 'status_name_th' => 'สถานะทดสอบ 7'],
        ]);
        $this->db->table('request_order')->insertBatch([
            $this->order(91002, '2026-08-02 00:00:00', 'WP00C-TRACK-002', 1, 2),
            $this->order(91003, '2026-08-03 00:00:00', 'WP00C-TRACK-003', 1, 3),
            $this->order(91007, '2026-08-07 00:00:00', 'WP00C-TRACK-007', 2, 7, '2026-08-08 00:00:00'),
            $this->order(91009, '2026-08-09 00:00:00', 'WP00C-TRACK-009', 2, 2),
        ]);
    }

    /** @return array<string, int|string|null> */
    private function order(
        int $id,
        string $requestDate,
        string $trackId,
        int $branchId,
        int $statusId,
        ?string $completedAt = null,
    ): array {
        return [
            'request_id'      => $id,
            'requestDate'     => $requestDate,
            'trackID'         => $trackId,
            'orderID'         => "WP00C-ORDER-{$id}",
            'orderIDShow'     => "WPC/{$id}",
            'number_cmg'      => "WP00C-CMG-{$id}",
            'customerFullname' => "SYNTHETIC CUSTOMER {$id}",
            'customerTel'     => '0000000000',
            'customerEmail'   => "wp00c-{$id}@example.invalid",
            'branchID'        => $branchId,
            'action_status'   => $statusId,
            'date_complete'   => $completedAt,
            'RepairPrice'     => '100.00',
            'waranty_cmg'     => 'IN',
        ];
    }
}
