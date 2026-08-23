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
            $query = $this->db->table('request_order')
                ->select('DATE(requestDate) AS label, COUNT(*) AS total', false)
                ->where('action_status >', 0);
            $this->scope($query, 'requestDate', 'branchID', $start, $end, $branchId);

            return $query->groupBy('DATE(requestDate)', false)->orderBy('label', 'ASC')->get()->getResultArray();
        }
        if ($kind === 'pending') {
            return $this->pending($start, $end, $branchId);
        }
        if ($kind === 'in-progress-average') {
            return $this->inProgressAverage($start, $end, $branchId);
        }
        if ($kind === 'in-progress') {
            $query = $this->db->table('request_order')
                ->select('request_id AS id, trackID AS label, requestDate, date_update_status, action_status')
                ->whereIn('action_status', [2, 3]);
            $this->scope($query, 'requestDate', 'branchID', $start, $end, $branchId);
            $rows = $query->orderBy('request_id', 'ASC')->get()->getResultArray();
            foreach ($rows as &$row) {
                $row['total'] = $this->days($row['requestDate'], $row['date_update_status']);
            }
            unset($row);

            return $rows;
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
            ->select([
                'orders.request_id', 'orders.requestDate', 'orders.trackID', 'orders.orderIDShow',
                'orders.customerFullname', 'orders.branchID', 'orders.action_status', 'orders.detailBrandId',
                'orders.detailTypeId', 'orders.RepairPrice', 'branches.branch_name', 'statuses.status_name',
                'brands.brand_details', 'types.type_details',
            ])
            ->join('brand brands', 'brands.brand_id = orders.detailBrandId', 'inner')
            ->join('type types', 'types.type_id = orders.detailTypeId', 'inner')
            ->join('branch branches', 'branches.branch_id = orders.branchID', 'left')
            ->join('statusaction statuses', 'statuses.status_id = orders.action_status', 'left')
            ->where('orders.action_status >', 0);
        $this->scope($query, 'orders.requestDate', 'orders.branchID', $start, $end, $branchId);
        if ($search !== '') {
            $query->groupStart()->like('orders.trackID', $search)->orLike('orders.orderIDShow', $search)
                ->orLike('orders.customerFullname', $search)->groupEnd();
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

    private function days(mixed $start, mixed $end): ?int
    {
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return null;
        }
        $interval = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end));

        return is_int($interval->days) ? ($interval->invert ? -$interval->days : $interval->days) : null;
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
