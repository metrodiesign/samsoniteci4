<?php

namespace Tests\Ci4;

use App\Reporting\ReportMatrix;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ReportMatrixTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $name = $this->db->escapeIdentifiers($this->db->prefixTable('request_order'));
        $this->db->query("DROP TABLE IF EXISTS {$name}");
        $this->db->query("CREATE TABLE {$name} (request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), detailBrandId INTEGER, detailTypeId INTEGER, branchID INTEGER, action_status INTEGER, date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_complete DATETIME, waranty_cmg VARCHAR(100))");
        $status = $this->db->escapeIdentifiers($this->db->prefixTable('statusaction'));
        $this->db->query("DROP TABLE IF EXISTS {$status}");
        $this->db->query("CREATE TABLE {$status} (status_id INTEGER PRIMARY KEY, status_name VARCHAR(250), status_name_th VARCHAR(250))");
        $branch = $this->db->escapeIdentifiers($this->db->prefixTable('branch'));
        $this->db->query("DROP TABLE IF EXISTS {$branch}");
        $this->db->query("CREATE TABLE {$branch} (branch_id INTEGER PRIMARY KEY, branch_name VARCHAR(250))");
        $this->db->resetDataCache();
        for ($id = 1; $id <= 8; $id++) {
            $this->db->table('statusaction')->insert([
                'status_id' => $id, 'status_name' => 'STATUS ' . $id, 'status_name_th' => 'สถานะ ' . $id,
            ]);
        }
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_name' => 'BRANCH A'],
            ['branch_id' => 2, 'branch_name' => 'BRANCH B'],
        ]);
    }

    public function testPendingTotalReturnsThreeBucketsAndTotalRow(): void
    {
        // bucket 1 (status 1) = 2, bucket 2 (status 2-4) = 3, bucket 3 (status 5 + date_complete) = 1.
        $this->insertOrder(1, 1, '2026-08-05 10:00:00');
        $this->insertOrder(2, 1, '2026-08-06 10:00:00');
        $this->insertOrder(3, 2, '2026-08-07 10:00:00');
        $this->insertOrder(4, 3, '2026-08-08 10:00:00');
        $this->insertOrder(5, 4, '2026-08-09 10:00:00');
        $this->insertOrder(6, 5, '2026-08-10 10:00:00', '2026-08-12 10:00:00');
        // status 5 with no date_complete must NOT land in bucket 3 (also drives the AC-7 mutation gate).
        $this->insertOrder(7, 5, '2026-08-11 10:00:00', null);

        $rows = (new ReportMatrix($this->db))->matrix('pending-total', '01/08/2026', '31/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'Waiting for CMG to pick up', 'Job' => 2, 'Average (Percent)' => '33.33%'],
            ['No' => 2, 'Detail' => 'Working in process - CMG', 'Job' => 3, 'Average (Percent)' => '50%'],
            ['No' => 3, 'Detail' => 'Pending for customer to pick up', 'Job' => 1, 'Average (Percent)' => '16.67%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '6', 'Average (Percent)' => '100%'],
        ], $rows);
    }

    public function testPendingTotalIgnoresStatusZeroAndSix(): void
    {
        $this->insertOrder(1, 1, '2026-08-05 10:00:00');
        $this->insertOrder(2, 1, '2026-08-06 10:00:00');
        $this->insertOrder(3, 2, '2026-08-07 10:00:00');
        $this->insertOrder(4, 3, '2026-08-08 10:00:00');
        $this->insertOrder(5, 4, '2026-08-09 10:00:00');
        $this->insertOrder(6, 5, '2026-08-10 10:00:00', '2026-08-12 10:00:00');
        // action_status 0 and 6 are outside every bucket and must not move any total.
        $this->insertOrder(10, 0, '2026-08-05 10:00:00');
        $this->insertOrder(11, 6, '2026-08-06 10:00:00', '2026-08-12 10:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('pending-total', '01/08/2026', '31/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'Waiting for CMG to pick up', 'Job' => 2, 'Average (Percent)' => '33.33%'],
            ['No' => 2, 'Detail' => 'Working in process - CMG', 'Job' => 3, 'Average (Percent)' => '50%'],
            ['No' => 3, 'Detail' => 'Pending for customer to pick up', 'Job' => 1, 'Average (Percent)' => '16.67%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '6', 'Average (Percent)' => '100%'],
        ], $rows);
    }

    public function testPendingTotalCountsLastDayInclusiveByEndOfDay(): void
    {
        // 23:59:59 of the last day is inside the range; 00:00:00 of the next day is not.
        $this->insertOrder(1, 1, '2026-08-15 23:59:59');
        $this->insertOrder(2, 1, '2026-08-16 00:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('pending-total', '01/08/2026', '15/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'Waiting for CMG to pick up', 'Job' => 1, 'Average (Percent)' => '100%'],
            ['No' => 2, 'Detail' => 'Working in process - CMG', 'Job' => 0, 'Average (Percent)' => '0%'],
            ['No' => 3, 'Detail' => 'Pending for customer to pick up', 'Job' => 0, 'Average (Percent)' => '0%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '1', 'Average (Percent)' => '100%'],
        ], $rows);
    }

    public function testInProgressAverageReturnsFiveThaiBucketsWithTwoDecimalPercent(): void
    {
        // status 1..5 present: 5, 2, 1, 1, 1 of 10 -> 50.00%, 20.00%, 10.00%, 10.00%, 10.00%.
        $this->insertOrder(1, 1, '2026-08-05 10:00:00');
        $this->insertOrder(2, 1, '2026-08-05 10:00:00');
        $this->insertOrder(3, 1, '2026-08-05 10:00:00');
        $this->insertOrder(4, 1, '2026-08-05 10:00:00');
        $this->insertOrder(5, 1, '2026-08-05 10:00:00');
        $this->insertOrder(6, 2, '2026-08-06 10:00:00');
        $this->insertOrder(7, 2, '2026-08-06 10:00:00');
        $this->insertOrder(8, 3, '2026-08-07 10:00:00');
        $this->insertOrder(9, 4, '2026-08-08 10:00:00');
        $this->insertOrder(10, 5, '2026-08-09 10:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('in-progress-average', '01/08/2026', '31/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'เปิดงานซ่อม รอศูนย์บริการมารับ', 'Job' => '5', 'Average (Percent)' => '50.00%'],
            ['No' => 2, 'Detail' => 'สินค้าจัดส่งเข้าศูนย์บริการ', 'Job' => '2', 'Average (Percent)' => '20.00%'],
            ['No' => 3, 'Detail' => 'อยู่ระหว่างดำเนินการซ่อมสินค้า', 'Job' => '1', 'Average (Percent)' => '10.00%'],
            ['No' => 4, 'Detail' => 'ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ', 'Job' => '1', 'Average (Percent)' => '10.00%'],
            ['No' => 5, 'Detail' => 'สินค้าถึงจุดรับบริการ รอลูกค้ามารับ', 'Job' => '1', 'Average (Percent)' => '10.00%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '10', 'Average (Percent)' => '100.00%'],
        ], $rows);
    }

    public function testInProgressAverageSumsFloatPercentBeforeRounding(): void
    {
        // Three equal buckets each 33.33...%; summing floats before rounding yields 100.00%, not 99.99%.
        $this->insertOrder(1, 1, '2026-08-05 10:00:00');
        $this->insertOrder(2, 2, '2026-08-06 10:00:00');
        $this->insertOrder(3, 3, '2026-08-07 10:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('in-progress-average', '01/08/2026', '31/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'เปิดงานซ่อม รอศูนย์บริการมารับ', 'Job' => '1', 'Average (Percent)' => '33.33%'],
            ['No' => 2, 'Detail' => 'สินค้าจัดส่งเข้าศูนย์บริการ', 'Job' => '1', 'Average (Percent)' => '33.33%'],
            ['No' => 3, 'Detail' => 'อยู่ระหว่างดำเนินการซ่อมสินค้า', 'Job' => '1', 'Average (Percent)' => '33.33%'],
            ['No' => 4, 'Detail' => 'ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 5, 'Detail' => 'สินค้าถึงจุดรับบริการ รอลูกค้ามารับ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '3', 'Average (Percent)' => '100.00%'],
        ], $rows);
    }

    public function testInProgressAverageZeroesEveryBucketWhenScopeEmpty(): void
    {
        // No rows in scope: every bucket 0 and percent guarded to '0.00%' (no divide-by-zero).
        $rows = (new ReportMatrix($this->db))->matrix('in-progress-average', '01/08/2026', '31/08/2026', null);

        self::assertSame([
            ['No' => 1, 'Detail' => 'เปิดงานซ่อม รอศูนย์บริการมารับ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 2, 'Detail' => 'สินค้าจัดส่งเข้าศูนย์บริการ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 3, 'Detail' => 'อยู่ระหว่างดำเนินการซ่อมสินค้า', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 4, 'Detail' => 'ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 5, 'Detail' => 'สินค้าถึงจุดรับบริการ รอลูกค้ามารับ', 'Job' => '0', 'Average (Percent)' => '0.00%'],
            ['No' => 'TOTAL', 'Detail' => '', 'Job' => '0', 'Average (Percent)' => '0.00%'],
        ], $rows);
    }

    public function testPendingReturnsJobListWithTotalRowOfDaySum(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        // One open repair: date_repair five days ago (12:00) with status 2.
        $repair = $today->modify('-5 days')->setTime(12, 0);
        $this->insertPendingOrder(1, 2, $repair->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('pending', $start, $end, null);

        // TOTAL trackID cell is number_format(sum of Day, 0) per CI3 view, not the row count.
        self::assertSame([
            ['No' => 1, 'trackID' => 'WPC-1', 'Status' => 'สถานะ 2', 'เล่มที่/เลขที่' => 'WPC/1',
                'เบอร์มือถือลูกค้า' => '0000000000', 'วันที่ส่งซ่อม' => $repair->format('d/m/Y'), 'Day' => 5],
            ['No' => 'TOTAL', 'trackID' => '5', 'Status' => '', 'เล่มที่/เลขที่' => '',
                'เบอร์มือถือลูกค้า' => '', 'วันที่ส่งซ่อม' => '', 'Day' => ''],
        ], $rows);
    }

    public function testPendingFiltersOnDateRepairAndDropsFromPendingTotal(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        // date_repair is inside the window; requestDate is 60 days ago, outside it.
        $repair = $today->modify('-5 days')->setTime(9, 0);
        $this->insertPendingOrder(1, 2, $repair->format('Y-m-d H:i:s'), null, $today->modify('-60 days')->format('Y-m-d H:i:s'));

        $pending = (new ReportMatrix($this->db))->matrix('pending', $start, $end, null);
        // Filtering on date_repair keeps the row (would vanish if it filtered requestDate).
        self::assertSame([
            ['No' => 1, 'trackID' => 'WPC-1', 'Status' => 'สถานะ 2', 'เล่มที่/เลขที่' => 'WPC/1',
                'เบอร์มือถือลูกค้า' => '0000000000', 'วันที่ส่งซ่อม' => $repair->format('d/m/Y'), 'Day' => 5],
            ['No' => 'TOTAL', 'trackID' => '5', 'Status' => '', 'เล่มที่/เลขที่' => '',
                'เบอร์มือถือลูกค้า' => '', 'วันที่ส่งซ่อม' => '', 'Day' => ''],
        ], $pending);

        // The same row filters on requestDate for pending-total and is out of range there.
        $pendingTotal = (new ReportMatrix($this->db))->matrix('pending-total', $start, $end, null);
        self::assertSame(['No' => 2, 'Detail' => 'Working in process - CMG', 'Job' => 0, 'Average (Percent)' => '0%'], $pendingTotal[1]);
    }

    public function testPendingExcludesCompletedJobsAndOrdersByDateRepairThenRequestId(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        $this->insertPendingOrder(1, 2, $today->modify('-3 days')->format('Y-m-d H:i:s'));
        // id 2 and 3 share the same date_repair: request_id breaks the tie (2 before 3).
        $this->insertPendingOrder(2, 2, $today->modify('-10 days')->format('Y-m-d H:i:s'));
        $this->insertPendingOrder(3, 2, $today->modify('-10 days')->format('Y-m-d H:i:s'));
        // Completed job (date_complete set) must not appear.
        $this->insertPendingOrder(4, 2, $today->modify('-1 days')->format('Y-m-d H:i:s'), $today->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('pending', $start, $end, null);

        self::assertSame(['WPC-2', 'WPC-3', 'WPC-1', number_format(10 + 10 + 3, 0)], [
            $rows[0]['trackID'], $rows[1]['trackID'], $rows[2]['trackID'], $rows[3]['trackID'],
        ]);
        self::assertSame([1, 2, 3, 'TOTAL'], [$rows[0]['No'], $rows[1]['No'], $rows[2]['No'], $rows[3]['No']]);
        self::assertCount(4, $rows);
    }

    public function testPendingDropsOrdersWhoseStatusIsMissingFromStatusaction(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        $this->insertPendingOrder(1, 2, $today->modify('-5 days')->format('Y-m-d H:i:s'));
        // action_status 0 has no statusaction row: INNER JOIN drops it.
        $this->insertPendingOrder(2, 0, $today->modify('-5 days')->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('pending', $start, $end, null);

        self::assertCount(2, $rows);
        self::assertSame('WPC-1', $rows[0]['trackID']);
    }

    public function testPendingDayTruncatesTimeOfDay(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        // date_repair five days ago at 14:00:00; dropping the time keeps Day at 5, not 4.
        $this->insertPendingOrder(1, 2, $today->modify('-5 days')->setTime(14, 0)->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('pending', $start, $end, null);

        self::assertSame(5, $rows[0]['Day']);
    }

    public function testInProgressMultiStatusFilterHonoursCsvAndIgnoresGarbage(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        foreach ([1, 2, 3, 4, 5] as $status) {
            $this->insertInProgressOrder($status, $status, $today->modify('-5 days')->format('Y-m-d H:i:s'));
        }
        $matrix = new ReportMatrix($this->db);

        // '' -> no status filter at all (all five statuses land, not a default of 1-5).
        $none = $matrix->matrix('in-progress', $start, $end, null, '');
        self::assertSame(['สถานะ 1', 'สถานะ 2', 'สถานะ 3', 'สถานะ 4', 'สถานะ 5'], array_column($none, 'Status'));

        // '2,4' CSV -> only statuses 2 and 4 (this assertion is the AC-8 mutation gate for whereIn).
        $csv = $matrix->matrix('in-progress', $start, $end, null, '2,4');
        self::assertSame(['สถานะ 2', 'สถานะ 4'], array_column($csv, 'Status'));

        // 'abc' -> parseStatusIds rejects it and falls back to no filter.
        $garbage = $matrix->matrix('in-progress', $start, $end, null, 'abc');
        self::assertSame(['สถานะ 1', 'สถานะ 2', 'สถานะ 3', 'สถานะ 4', 'สถานะ 5'], array_column($garbage, 'Status'));
    }

    public function testInProgressDropsOrdersWhoseStatusIsMissingFromStatusaction(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        $this->insertInProgressOrder(1, 2, $today->modify('-5 days')->format('Y-m-d H:i:s'));
        // action_status 0 has no statusaction row: INNER JOIN drops it.
        $this->insertInProgressOrder(2, 0, $today->modify('-5 days')->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('in-progress', $start, $end, null, '');

        self::assertCount(1, $rows);
        self::assertSame('WPC-1', $rows[0]['Track Id']);
    }

    public function testInProgressRowShapeHasNumberFormatDayAndNoTotalRow(): void
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify('-30 days')->format('d/m/Y');
        $end = $today->format('d/m/Y');
        // requestDate 21 days ago at 14:00: dropping the time keeps Day at 21 as a number_format string.
        $request = $today->modify('-21 days')->setTime(14, 0);
        $this->insertInProgressOrder(1, 2, $request->format('Y-m-d H:i:s'));

        $rows = (new ReportMatrix($this->db))->matrix('in-progress', $start, $end, null, '');

        // Exactly one row (no TOTAL), Day is the number_format string '21', keys in CI3 order.
        self::assertSame([
            ['No' => 1, 'Status' => 'สถานะ 2', 'Track Id' => 'WPC-1', 'Order Id' => 'WPC/1',
                'Branch Name' => 'BRANCH A', 'Full Name' => 'CUSTOMER 1', 'Tel' => '0000000000',
                'Request Date' => $request->format('d/m/Y'), 'Day' => '21'],
        ], $rows);
    }

    public function testInProgressReturnsEmptyListWhenNoRowsInScope(): void
    {
        // No open orders: empty list with no TOTAL row (the generic view then renders an empty table).
        $rows = (new ReportMatrix($this->db))->matrix('in-progress', '01/08/2026', '31/08/2026', null, '');

        self::assertSame([], $rows);
    }

    private function insertInProgressOrder(int $id, int $status, string $requestDate, ?string $dateComplete = null, int $branch = 1): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => $id,
            'requestDate' => $requestDate,
            'trackID' => 'WPC-' . $id,
            'orderIDShow' => 'WPC/' . $id,
            'customerFullname' => 'CUSTOMER ' . $id,
            'customerTel' => '0000000000',
            'branchID' => $branch,
            'action_status' => $status,
            'date_complete' => $dateComplete,
        ]);
    }

    private function insertPendingOrder(
        int $id,
        int $status,
        string $dateRepair,
        ?string $dateComplete = null,
        string $requestDate = '2026-08-01 00:00:00',
        int $branch = 1,
    ): void {
        $this->db->table('request_order')->insert([
            'request_id' => $id,
            'requestDate' => $requestDate,
            'trackID' => 'WPC-' . $id,
            'orderIDShow' => 'WPC/' . $id,
            'customerTel' => '0000000000',
            'branchID' => $branch,
            'action_status' => $status,
            'date_repair' => $dateRepair,
            'date_complete' => $dateComplete,
        ]);
    }

    private function insertOrder(int $id, int $status, string $requestDate, ?string $dateComplete = null, int $branch = 1): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => $id,
            'requestDate' => $requestDate,
            'trackID' => 'WP06A-' . $id,
            'branchID' => $branch,
            'action_status' => $status,
            'date_complete' => $dateComplete,
        ]);
    }
}
