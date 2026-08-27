<?php

namespace App\Reporting;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ReportMatrix
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array{question:int,total:int,scores:array<int, array{count:int,percentage:string}>}> */
    /**
     * Free-text answers to CI3's question 6 ("ข้อเสนอแนะเพิ่มเติม"), scoped by the same date
     * range and branch as the score matrix so the page reads as one report.
     *
     * @return list<array<string, mixed>>
     */
    public function ratingComments(mixed $startDate, mixed $endDate, ?int $branchId): array
    {
        [$start, $end] = $this->dates($startDate, $endDate);
        $query = $this->db->table('rating_comment')
            ->select('id, comment')
            ->where("TRIM(comment) <>", '');
        $this->scope($query, 'created_at', 'branch_id', $start, $end, $branchId);

        return $query->orderBy('id', 'ASC')->limit(500)->get()->getResultArray();
    }

    public function ratings(mixed $startDate, mixed $endDate, ?int $branchId): array
    {
        [$start, $end] = $this->dates($startDate, $endDate);
        $query = $this->db->table('rating')
            ->select('add_id, rating, COUNT(*) AS total', false)
            ->where('add_id >=', 1)->where('add_id <=', 8)
            ->where('rating >=', 1)->where('rating <=', 5);
        $this->scope($query, 'cdate', 'branchID', $start, $end, $branchId);
        $groups = [];
        for ($question = 1; $question <= 8; $question++) {
            $groups[$question] = ['question' => $question, 'total' => 0, 'scores' => []];
            for ($score = 1; $score <= 5; $score++) {
                $groups[$question]['scores'][$score] = ['count' => 0, 'percentage' => '0.00'];
            }
        }
        foreach ($query->groupBy(['add_id', 'rating'])->orderBy('add_id', 'ASC')->orderBy('rating', 'ASC')->get()->getResultArray() as $row) {
            $question = (int) $row['add_id'];
            $score = (int) $row['rating'];
            $count = (int) $row['total'];
            $groups[$question]['total'] += $count;
            $groups[$question]['scores'][$score]['count'] = $count;
        }
        foreach ($groups as &$group) {
            foreach ($group['scores'] as &$score) {
                $score['percentage'] = $group['total'] === 0
                    ? '0.00'
                    : number_format($score['count'] * 100 / $group['total'], 2, '.', '');
            }
            unset($score);
        }
        unset($group);

        return array_values($groups);
    }

    /** @return list<array<string, int|string|null>> */
    public function matrix(string $kind, mixed $startDate, mixed $endDate, ?int $branchId, mixed $rawStatusIds = null): array
    {
        [$start, $end] = $this->dates($startDate, $endDate);
        if ($kind === 'pending-total') {
            return $this->pendingTotal($start, $end, $branchId);
        }
        if ($kind === 'jobs-by-day') {
            return $this->jobsByDay($start, $end, $branchId);
        }
        if ($kind === 'pending') {
            return $this->pending($start, $end, $branchId);
        }
        if ($kind === 'in-progress-average') {
            return $this->inProgressAverage($start, $end, $branchId);
        }
        if ($kind === 'in-progress') {
            return $this->inProgress($start, $end, $branchId, $rawStatusIds);
        }
        throw new InvalidArgumentException('Unknown report.');
    }

    /** @return list<array<string, int|string>> */
    private function pendingTotal(?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId): array
    {
        $grouped = $this->db->table('request_order')
            ->select('action_status, COUNT(*) AS total', false)
            ->where('action_status >=', 1)->where('action_status <=', 4);
        $this->scope($grouped, 'requestDate', 'branchID', $start, $end, $branchId);
        $counts = [];
        foreach ($grouped->groupBy('action_status')->get()->getResultArray() as $row) {
            $counts[(int) $row['action_status']] = (int) $row['total'];
        }
        $waiting = $counts[1] ?? 0;
        $working = ($counts[2] ?? 0) + ($counts[3] ?? 0) + ($counts[4] ?? 0);

        $pendingQuery = $this->db->table('request_order')
            ->where('action_status', 5)->where('date_complete IS NOT NULL', null, false);
        $this->scope($pendingQuery, 'requestDate', 'branchID', $start, $end, $branchId);
        $pending = $pendingQuery->countAllResults();

        $total = $waiting + $working + $pending;
        $sumPercent = 0.0;
        $rows = [];
        foreach ([
            [1, 'Waiting for CMG to pick up', $waiting],
            [2, 'Working in process - CMG', $working],
            [3, 'Pending for customer to pick up', $pending],
        ] as [$no, $detail, $count]) {
            $percent = $total > 0 ? $count * 100 / $total : 0.0;
            $sumPercent += $percent;
            $rows[] = [
                'No' => $no,
                'Detail' => $detail,
                'Job' => $count,
                'Average (Percent)' => round($percent, 2) . '%',
            ];
        }
        $rows[] = [
            'No' => 'TOTAL',
            'Detail' => '',
            'Job' => number_format($total, 0),
            'Average (Percent)' => number_format($sumPercent, 0) . '%',
        ];

        return $rows;
    }

    /** @return list<array<string, int|string>> */
    private function inProgressAverage(?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId): array
    {
        $grouped = $this->db->table('request_order')
            ->select('action_status, COUNT(*) AS total', false)
            ->whereIn('action_status', [1, 2, 3, 4, 5]);
        $this->scope($grouped, 'requestDate', 'branchID', $start, $end, $branchId);
        $counts = [];
        foreach ($grouped->groupBy('action_status')->get()->getResultArray() as $row) {
            $counts[(int) $row['action_status']] = (int) $row['total'];
        }
        $total = array_sum($counts);

        $sumPercent = 0.0;
        $rows = [];
        foreach ([
            [1, 'เปิดงานซ่อม รอศูนย์บริการมารับ'],
            [2, 'สินค้าจัดส่งเข้าศูนย์บริการ'],
            [3, 'อยู่ระหว่างดำเนินการซ่อมสินค้า'],
            [4, 'ซ่อมเสร็จเรียบร้อยแล้ว รอส่งกลับจุดรับบริการ'],
            [5, 'สินค้าถึงจุดรับบริการ รอลูกค้ามารับ'],
        ] as [$no, $detail]) {
            $count = $counts[$no] ?? 0;
            $percent = $total > 0 ? $count * 100 / $total : 0.0;
            $sumPercent += $percent;
            $rows[] = [
                'No' => $no,
                'Detail' => $detail,
                'Job' => number_format($count, 0),
                'Average (Percent)' => number_format($percent, 2) . '%',
            ];
        }
        $rows[] = [
            'No' => 'TOTAL',
            'Detail' => '',
            'Job' => number_format($total, 0),
            'Average (Percent)' => number_format($sumPercent, 2) . '%',
        ];

        return $rows;
    }

    /** @return list<array<string, int|string|null>> */
    private function pending(?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId): array
    {
        $query = $this->db->table('request_order orders')
            ->select('orders.request_id, orders.trackID, orders.orderIDShow, orders.customerTel, orders.date_repair, statuses.status_name_th', false)
            ->join('statusaction statuses', 'statuses.status_id = orders.action_status', 'inner')
            ->where('orders.date_complete', null)
            ->orderBy('orders.date_repair', 'ASC')->orderBy('orders.request_id', 'ASC');
        $this->scope($query, 'orders.date_repair', 'orders.branchID', $start, $end, $branchId);

        $today = new DateTimeImmutable('today');
        $rows = [];
        $sumDay = 0;
        $no = 1;
        foreach ($query->get()->getResultArray() as $record) {
            $repair = (string) $record['date_repair'];
            $day = $this->dateDiff($today, $repair);
            $sumDay += $day ?? 0;
            $rows[] = [
                'No' => $no,
                'trackID' => $record['trackID'],
                'Status' => $record['status_name_th'],
                'เล่มที่/เลขที่' => $record['orderIDShow'],
                'เบอร์มือถือลูกค้า' => $record['customerTel'],
                'วันที่ส่งซ่อม' => $repair === '' ? '' : (new DateTimeImmutable($repair))->format('d/m/Y'),
                'Day' => $day,
            ];
            $no++;
        }
        $rows[] = [
            'No' => 'TOTAL',
            'trackID' => number_format($sumDay, 0),
            'Status' => '',
            'เล่มที่/เลขที่' => '',
            'เบอร์มือถือลูกค้า' => '',
            'วันที่ส่งซ่อม' => '',
            'Day' => '',
        ];

        return $rows;
    }

    /** @return list<array<string, int|string|null>> */
    private function inProgress(?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId, mixed $rawStatusIds): array
    {
        $query = $this->db->table('request_order orders')
            ->select('orders.request_id, orders.trackID, orders.orderIDShow, orders.customerFullname, orders.customerTel, orders.requestDate, statuses.status_name_th, branches.branch_name', false)
            ->join('statusaction statuses', 'statuses.status_id = orders.action_status', 'inner')
            ->join('branch branches', 'branches.branch_id = orders.branchID', 'left')
            ->where('orders.date_complete', null)
            ->orderBy('orders.requestDate', 'ASC')->orderBy('orders.request_id', 'ASC');
        $this->scope($query, 'orders.requestDate', 'orders.branchID', $start, $end, $branchId);
        $statusIds = (new TrackingReport($this->db))->parseStatusIds($rawStatusIds);
        if ($statusIds !== []) {
            $query->whereIn('orders.action_status', $statusIds);
        }

        $today = new DateTimeImmutable('today');
        $rows = [];
        $no = 1;
        foreach ($query->get()->getResultArray() as $record) {
            $requestDate = (string) $record['requestDate'];
            $day = $this->dateDiff($today, $requestDate);
            $rows[] = [
                'No' => $no,
                'Status' => $record['status_name_th'],
                'Track Id' => $record['trackID'],
                'Order Id' => $record['orderIDShow'],
                'Branch Name' => $record['branch_name'],
                'Full Name' => $record['customerFullname'],
                'Tel' => $record['customerTel'],
                'Request Date' => $requestDate === '' ? '' : (new DateTimeImmutable($requestDate))->format('d/m/Y'),
                'Day' => number_format($day ?? 0, 0),
            ];
            $no++;
        }

        return $rows;
    }

    /** @return list<array<array-key, int|string>> */
    private function jobsByDay(?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId): array
    {
        $query = $this->db->table('request_order')
            ->select('detailBrandId, detailTypeId, waranty_cmg, date_repair, date_repair_waranty, date_complete')
            ->where('date_complete IS NOT NULL', null, false)
            ->where("UPPER(TRIM(waranty_cmg)) IN ('OUT', 'UNW', '')", null, false);
        $this->scope($query, 'requestDate', 'branchID', $start, $end, $branchId);

        $tallies = [];
        foreach ($query->get()->getResultArray() as $record) {
            $diff = match (strtoupper(trim((string) $record['waranty_cmg']))) {
                'OUT'   => $this->dateDiff($record['date_complete'], $record['date_repair_waranty']),
                default => $this->dateDiff($record['date_complete'], $record['date_repair']),
            };
            $column = $this->jobsByDayColumn($diff);
            if ($column === null) {
                continue;
            }
            $key = (int) $record['detailBrandId'] . '|' . (int) $record['detailTypeId'];
            $tallies[$key][$column] = ($tallies[$key][$column] ?? 0) + 1;
        }

        $columns = ['0', '1-7', '8-30', '31-45', '> 45'];
        $brands = $this->db->table('brand')->select('brand_id, brand_details')->orderBy('brand_id', 'ASC')->get()->getResultArray();
        $types = $this->db->table('type')->select('type_id, type_details')->orderBy('type_id', 'ASC')->get()->getResultArray();

        $rows = [];
        $totals = array_fill_keys($columns, 0);
        foreach ($brands as $brand) {
            foreach ($types as $type) {
                $key = (int) $brand['brand_id'] . '|' . (int) $type['type_id'];
                $row = ['Brand' => (string) $brand['brand_details'], 'Product Type' => (string) $type['type_details']];
                foreach ($columns as $column) {
                    $count = $tallies[$key][$column] ?? 0;
                    $row[$column] = $count;
                    $totals[$column] += $count;
                }
                $rows[] = $row;
            }
        }
        $rows[] = ['Brand' => 'TOTAL', 'Product Type' => ''] + $totals;

        $grandTotal = array_sum($totals);
        foreach ([
            ['Over all repair time 0-7 Days', $totals['0'] + $totals['1-7']],
            ['Over all repair time 8-30 Days', $totals['8-30']],
            ['Over all repair time 31-45 Days', $totals['31-45']],
            ['Over all repair time >45 Days', $totals['> 45']],
        ] as [$label, $numerator]) {
            $percent = $grandTotal > 0 ? round($numerator * 100 / $grandTotal, 2) : 0;
            $rows[] = [
                'Brand' => $label,
                'Product Type' => $percent . ' %',
                '0' => '', '1-7' => '', '8-30' => '', '31-45' => '', '> 45' => '',
            ];
        }

        return $rows;
    }

    private function jobsByDayColumn(?int $diff): ?string
    {
        return match (true) {
            $diff === null => null,
            $diff === 0 => '0',
            $diff > 0 && $diff < 8 => '1-7',
            $diff > 7 && $diff < 31 => '8-30',
            $diff > 30 && $diff < 46 => '31-45',
            $diff > 45 => '> 45',
            default => null,
        };
    }

    /** @return list<array<string, int|float|string|null>> */
    public function summary(
        mixed $searchText,
        mixed $startDate,
        mixed $endDate,
        mixed $rawStatusIds,
        mixed $rawBrandId,
        mixed $rawTypeId,
        ?int $branchId,
        ?int $limit = null,
        int $offset = 0,
    ): array {
        $search = is_string($searchText) ? trim($searchText) : '';
        if (mb_strlen($search) > 128) {
            throw new InvalidArgumentException('Search text is too long.');
        }
        [$start, $end] = $this->dates($startDate, $endDate);
        $brandId = $this->optionalId($rawBrandId, 'brand');
        $typeId = $this->optionalId($rawTypeId, 'type');
        $statuses = (new TrackingReport($this->db))->parseStatusIds($rawStatusIds);
        $query = $this->db->table('request_order orders')
            // The column list mirrors CI3's reportsummary view one for one; it renders 26 columns
            // and the export shares the same rows, so trimming this select shrinks both.
            ->select([
                'orders.request_id', 'orders.requestDate', 'orders.trackID', 'orders.orderIDShow',
                'orders.customerFullname', 'orders.customerTel', 'orders.customerEmail',
                'orders.branchID', 'orders.action_status', 'orders.detailBrandId',
                'orders.detailTypeId', 'orders.RepairPrice', 'orders.detailAgent', 'orders.detailSKUName',
                'orders.detailNumberWaranty', 'orders.detailEquipment', 'orders.detailNote',
                'orders.detailCondition', 'orders.detailConditionOther', 'orders.detailEstimatePrice',
                'orders.detailEstimatePriceOther', 'orders.detailFixed', 'orders.detailFixedOther',
                'orders.date_repair', 'orders.date_update_status', 'orders.date_deliver',
                'orders.date_complete', 'orders.waranty_cmg',
                'branches.branch_name', 'branches.branch_user_name', 'statuses.status_name',
                'brands.brand_details', 'types.type_details',
            ])
            ->join('brand brands', 'brands.brand_id = orders.detailBrandId', 'inner')
            ->join('type types', 'types.type_id = orders.detailTypeId', 'inner')
            ->join('branch branches', 'branches.branch_id = orders.branchID', 'left')
            ->join('statusaction statuses', 'statuses.status_id = orders.action_status', 'left')
            ->where('orders.action_status >', 0);
        $this->scope($query, 'orders.requestDate', 'orders.branchID', $start, $end, $branchId);
        if ($search !== '') {
            $query->groupStart()->like('orders.trackID', $search)->orLike('orders.orderID', $search)
                ->orLike('orders.customerFullname', $search)->orLike('orders.detailSKUName', $search)
                ->orLike('orders.orderIDShow', $search)->orLike('branches.branch_name', $search)
                ->orLike('orders.customerTel', $search)->orLike('orders.customerEmail', $search)
                ->orLike('statuses.status_name', $search)->groupEnd();
        }
        if ($statuses !== []) {
            $query->whereIn('orders.action_status', $statuses);
        }
        if ($brandId !== null) {
            $query->where('orders.detailBrandId', $brandId);
        }
        if ($typeId !== null) {
            $query->where('orders.detailTypeId', $typeId);
        }

        $query->orderBy('orders.requestDate', 'DESC')->orderBy('orders.request_id', 'DESC');
        if ($limit !== null) {
            $query->limit($limit, $offset);
        }

        return $query->get()->getResultArray();
    }

    /** @return array{?DateTimeImmutable, ?DateTimeImmutable} */
    private function dates(mixed $start, mixed $end): array
    {
        $start = $this->date($start, 'start date');
        $end = $this->date($end, 'end date');
        if ($start !== null && $end !== null && $start > $end) {
            throw new InvalidArgumentException('Start date must not be after end date.');
        }

        return [$start, $end];
    }

    private function date(mixed $value, string $label): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) !== 10) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value, new DateTimeZone(date_default_timezone_get()));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('d/m/Y') !== $value) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }

        return $date;
    }

    private function optionalId(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }

        return (int) $value;
    }

    private function scope($query, string $dateColumn, string $branchColumn, ?DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $branchId): void
    {
        if ($start !== null) {
            $query->where($dateColumn . ' >=', $start->format('Y-m-d 00:00:00'));
        }
        if ($end !== null) {
            $query->where($dateColumn . ' <', $end->modify('+1 day')->format('Y-m-d 00:00:00'));
        }
        if ($branchId !== null) {
            $query->where($branchColumn, $branchId);
        }
    }

    private function dateDiff(mixed $to, mixed $from): ?int
    {
        $to = $this->toDate($to);
        $from = $this->toDate($from);
        if ($to === null || $from === null) {
            return null;
        }
        $interval = $from->setTime(0, 0)->diff($to->setTime(0, 0));

        return $interval->invert === 1 ? -$interval->days : $interval->days;
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
