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
