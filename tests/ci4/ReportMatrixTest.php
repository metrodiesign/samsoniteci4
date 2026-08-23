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
        $this->db->query("CREATE TABLE {$name} (request_id INTEGER PRIMARY KEY, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL, orderIDShow VARCHAR(100), customerTel VARCHAR(100), detailBrandId INTEGER, detailTypeId INTEGER, branchID INTEGER, action_status INTEGER, date_repair DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, date_complete DATETIME, waranty_cmg VARCHAR(100))");
        $this->db->resetDataCache();
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
