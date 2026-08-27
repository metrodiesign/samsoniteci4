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
        $this->db->query("CREATE TABLE {$name} (request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailSKUName VARCHAR(250), detailBrandId INTEGER, detailTypeId INTEGER, branchID INTEGER, action_status INTEGER, RepairPrice DECIMAL(8,2), date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_complete DATETIME, waranty_cmg VARCHAR(100), detailAgent INTEGER, detailNumberWaranty VARCHAR(100), detailEquipment TEXT, detailNote TEXT, detailCondition VARCHAR(250), detailConditionOther VARCHAR(250), detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250), detailFixed VARCHAR(250), detailFixedOther VARCHAR(250), date_deliver DATETIME)");
        $status = $this->db->escapeIdentifiers($this->db->prefixTable('statusaction'));
        $this->db->query("DROP TABLE IF EXISTS {$status}");
        $this->db->query("CREATE TABLE {$status} (status_id INTEGER PRIMARY KEY, status_name VARCHAR(250), status_name_th VARCHAR(250))");
        $branch = $this->db->escapeIdentifiers($this->db->prefixTable('branch'));
        $this->db->query("DROP TABLE IF EXISTS {$branch}");
        $this->db->query("CREATE TABLE {$branch} (branch_id INTEGER PRIMARY KEY, branch_name VARCHAR(250), branch_user_name VARCHAR(100))");
        $brand = $this->db->escapeIdentifiers($this->db->prefixTable('brand'));
        $this->db->query("DROP TABLE IF EXISTS {$brand}");
        $this->db->query("CREATE TABLE {$brand} (brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250))");
        $type = $this->db->escapeIdentifiers($this->db->prefixTable('type'));
        $this->db->query("DROP TABLE IF EXISTS {$type}");
        $this->db->query("CREATE TABLE {$type} (type_id INTEGER PRIMARY KEY, type_details VARCHAR(250))");
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
        $this->db->table('brand')->insertBatch([
            ['brand_id' => 1, 'brand_details' => 'BRAND A'],
            ['brand_id' => 2, 'brand_details' => 'BRAND B'],
        ]);
        $this->db->table('type')->insertBatch([
            ['type_id' => 1, 'type_details' => 'TYPE A'],
            ['type_id' => 2, 'type_details' => 'TYPE B'],
        ]);
    }

    public function testSummarySearchMatchesEveryParityField(): void
    {
        $this->db->table('request_order')->insert([
            'request_id' => 900, 'requestDate' => '2026-08-10 09:00:00',
            'trackID' => 'STK900', 'orderID' => 'SORD900', 'orderIDShow' => 'SSHOW900',
            'customerFullname' => 'SFULL900', 'customerTel' => '027619999',
            'customerEmail' => 'semail900@example.invalid', 'detailSKUName' => 'SSKU900',
            'detailBrandId' => 1, 'detailTypeId' => 1, 'branchID' => 1, 'action_status' => 2,
        ]);
        $matrix = new ReportMatrix($this->db);
        $terms = [
            'STK900', 'SORD900', 'SFULL900', 'SSKU900', 'SSHOW900',
            'BRANCH A', '027619999', 'semail900@example.invalid', 'STATUS 2',
        ];
        foreach ($terms as $term) {
            $rows = $matrix->summary($term, '01/08/2026', '31/08/2026', null, null, null, null);
            self::assertCount(1, $rows, $term);
            self::assertSame(900, (int) $rows[0]['request_id'], $term);
        }
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

    public function testJobsByDayBoundaryDiffsLandInExpectedColumns(): void
    {
        // Seven diffs 0,7,8,30,31,45,46 in brand1 x type1 -> columns 0,1-7,8-30,8-30,31-45,31-45,> 45.
        foreach ([
            [1, '2026-08-01 00:00:00'], // diff 0
            [2, '2026-08-08 00:00:00'], // diff 7
            [3, '2026-08-09 00:00:00'], // diff 8
            [4, '2026-08-31 00:00:00'], // diff 30
            [5, '2026-09-01 00:00:00'], // diff 31
            [6, '2026-09-15 00:00:00'], // diff 45
            [7, '2026-09-16 00:00:00'], // diff 46
        ] as [$id, $complete]) {
            $this->insertJobOrder($id, 1, 1, 'UNW', '2026-08-01 00:00:00', null, $complete);
        }

        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        // brand1 x type1 aggregates: 0->1, 1-7->1, 8-30->2 (diff 8,30), 31-45->2 (diff 31,45), > 45->1 (diff 46).
        self::assertSame(
            ['Brand' => 'BRAND A', 'Product Type' => 'TYPE A', '0' => 1, '1-7' => 1, '8-30' => 2, '31-45' => 2, '> 45' => 1],
            $rows[0],
        );
    }

    public function testJobsByDayPlacesDiff31InThirtyOneToFortyFiveWithAndWithoutBranch(): void
    {
        // diff 31 (UNW uses date_repair): normalized bucket is > 30, so it lands in 31-45 in both scopes.
        $this->insertJobOrder(1, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-09-01 00:00:00');

        $withoutBranch = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);
        $withBranch = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', 1);

        // brand1 x type1 is the first data row in both cases; diff 31 must sit in 31-45, not 8-30.
        self::assertSame(1, $withoutBranch[0]['31-45']);
        self::assertSame(0, $withoutBranch[0]['8-30']);
        self::assertSame(1, $withBranch[0]['31-45']);
        self::assertSame(0, $withBranch[0]['8-30']);
    }

    public function testJobsByDayHonoursWarantyCaseAndPicksCorrectRepairDate(): void
    {
        // 'IN' is excluded entirely (would be a same-day diff otherwise).
        $this->insertJobOrder(1, 1, 1, 'IN', '2026-08-01 00:00:00', '2026-08-01 00:00:00', '2026-08-02 00:00:00');
        // 'out' lowercase counted via date_repair_waranty: diff 2 -> 1-7. date_repair is far to prove it is unused.
        $this->insertJobOrder(2, 1, 1, 'out', '2026-01-01 00:00:00', '2026-08-05 00:00:00', '2026-08-07 00:00:00');
        // 'UNW' uses date_repair: diff 5 -> 1-7.
        $this->insertJobOrder(3, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-08-06 00:00:00');
        // '' uses date_repair: diff 10 -> 8-30.
        $this->insertJobOrder(4, 1, 1, '', '2026-08-01 00:00:00', null, '2026-08-11 00:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        // brand1 x type1: 'out'(diff2)->1-7, 'UNW'(diff5)->1-7, ''(diff10)->8-30; 'IN' excluded.
        self::assertSame(
            ['Brand' => 'BRAND A', 'Product Type' => 'TYPE A', '0' => 0, '1-7' => 2, '8-30' => 1, '31-45' => 0, '> 45' => 0],
            $rows[0],
        );
    }

    public function testJobsByDayEmitsEveryBrandTypePairEvenWhenZero(): void
    {
        // Only brand1 x type1 has data; the other three pairs must still appear as all-zero rows.
        $this->insertJobOrder(1, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-08-02 00:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        // 2 brands x 2 types = 4 data rows ordered by brand_id then type_id.
        self::assertSame([
            ['Brand' => 'BRAND A', 'Product Type' => 'TYPE A', '0' => 0, '1-7' => 1, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            ['Brand' => 'BRAND A', 'Product Type' => 'TYPE B', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            ['Brand' => 'BRAND B', 'Product Type' => 'TYPE A', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            ['Brand' => 'BRAND B', 'Product Type' => 'TYPE B', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
        ], array_slice($rows, 0, 4));
        // 4 data rows + TOTAL + 4 percent rows.
        self::assertCount(9, $rows);
    }

    public function testJobsByDayTotalRowIsRawIntAndPercentRowsUseSpacedFormat(): void
    {
        // brand1 x type1: two diff-0, one diff-3 (1-7), one diff-31 (31-45). Grand total 4.
        $this->insertJobOrder(1, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-08-01 00:00:00');
        $this->insertJobOrder(2, 1, 1, 'UNW', '2026-08-02 00:00:00', null, '2026-08-02 00:00:00');
        $this->insertJobOrder(3, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-08-04 00:00:00');
        $this->insertJobOrder(4, 1, 1, 'UNW', '2026-08-01 00:00:00', null, '2026-09-01 00:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        // TOTAL row (index 4, after 4 data rows) is raw ints across the 5 bucket columns.
        self::assertSame(
            ['Brand' => 'TOTAL', 'Product Type' => '', '0' => 2, '1-7' => 1, '8-30' => 0, '31-45' => 1, '> 45' => 0],
            $rows[4],
        );
        // Percent rows: exact labels, value round(p,2) . ' %' with a leading space; 3/4 -> 75, 1/4 -> 25.
        self::assertSame([
            ['Brand' => 'Over all repair time 0-7 Days', 'Product Type' => '75 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time 8-30 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time 31-45 Days', 'Product Type' => '25 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time >45 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
        ], array_slice($rows, 5, 4));
    }

    public function testJobsByDayPercentRowsAreZeroPercentWhenGrandTotalIsZero(): void
    {
        // No counted orders: grand total 0 -> each percent row is '0 %' with no divide-by-zero.
        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        self::assertSame(
            ['Brand' => 'TOTAL', 'Product Type' => '', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            $rows[4],
        );
        self::assertSame([
            ['Brand' => 'Over all repair time 0-7 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time 8-30 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time 31-45 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
            ['Brand' => 'Over all repair time >45 Days', 'Product Type' => '0 %', '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => ''],
        ], array_slice($rows, 5, 4));
    }

    public function testJobsByDayIgnoresNullAndNegativeDiffs(): void
    {
        // 'OUT' with a null date_repair_waranty -> diff null -> counted nowhere.
        $this->insertJobOrder(1, 1, 1, 'OUT', '2026-08-01 00:00:00', null, '2026-08-05 00:00:00');
        // date_complete before date_repair -> negative diff -> counted nowhere.
        $this->insertJobOrder(2, 1, 1, 'UNW', '2026-08-10 00:00:00', null, '2026-08-05 00:00:00');

        $rows = (new ReportMatrix($this->db))->matrix('jobs-by-day', '01/08/2026', '31/08/2026', null);

        // Both rows were fetched (date_complete set, waranty in set) but neither bucketed.
        self::assertSame(
            ['Brand' => 'BRAND A', 'Product Type' => 'TYPE A', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            $rows[0],
        );
        self::assertSame(
            ['Brand' => 'TOTAL', 'Product Type' => '', '0' => 0, '1-7' => 0, '8-30' => 0, '31-45' => 0, '> 45' => 0],
            $rows[4],
        );
    }

    private function insertJobOrder(
        int $id,
        int $brand,
        int $type,
        string $waranty,
        ?string $dateRepair,
        ?string $dateRepairWaranty,
        ?string $dateComplete,
        string $requestDate = '2026-08-15 10:00:00',
        int $branch = 1,
    ): void {
        $this->db->table('request_order')->insert([
            'request_id' => $id,
            'requestDate' => $requestDate,
            'trackID' => 'WP06A-' . $id,
            'branchID' => $branch,
            'action_status' => 1,
            'detailBrandId' => $brand,
            'detailTypeId' => $type,
            'waranty_cmg' => $waranty,
            'date_repair' => $dateRepair,
            'date_repair_waranty' => $dateRepairWaranty,
            'date_complete' => $dateComplete,
        ]);
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
