<?php

namespace App\Reporting;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class TrackingReport
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<int> */
    public function parseStatusIds(mixed $rawStatusIds): array
    {
        if (! is_string($rawStatusIds)) {
            return [];
        }

        $rawStatusIds = trim($rawStatusIds);
        if ($rawStatusIds === '' || preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $rawStatusIds) !== 1) {
            return [];
        }

        $statusIds = [];
        foreach (explode(',', $rawStatusIds) as $rawStatusId) {
            $statusId = filter_var($rawStatusId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2_147_483_647],
            ]);
            if ($statusId === false) {
                return [];
            }

            $statusIds[(int) $statusId] = (int) $statusId;
        }

        return array_values($statusIds);
    }

    /**
     * @return list<array<string, int|float|string|null>>
     */
    public function rows(
        mixed $searchText,
        mixed $startDate,
        mixed $endDate,
        mixed $rawStatusIds,
        ?int $branchId,
    ): array {
        $searchText = is_string($searchText) ? trim($searchText) : '';
        if (strlen($searchText) > 128) {
            throw new InvalidArgumentException('Search text is too long.');
        }

        $start = $this->parseDate($startDate, 'start date');
        $end   = $this->parseDate($endDate, 'end date');
        if ($start !== null && $end !== null && $start > $end) {
            throw new InvalidArgumentException('Start date must not be after end date.');
        }

        $builder = $this->db->table('request_order orders')
            ->select([
                'orders.request_id',
                'orders.requestDate',
                'orders.trackID',
                'orders.orderID',
                'orders.orderIDShow',
                'orders.number_cmg',
                'orders.detailAgent',
                'orders.customerFullname',
                'orders.customerTel',
                'orders.customerEmail',
                'orders.detailSKUName',
                'orders.branchID',
                'orders.action_status',
                'orders.date_repair',
                'orders.date_repair_waranty',
                'orders.date_update_status',
                'orders.date_deliver',
                'orders.date_complete',
                'orders.provider_id',
                'orders.logistics_etc_detail',
                'orders.RepairPrice',
                'orders.waranty_cmg',
                'branches.branch_user_name',
                'branches.branch_name',
                'statuses.status_name',
            ])
            ->join('branch branches', 'branches.branch_id = orders.branchID', 'left')
            ->join('statusaction statuses', 'statuses.status_id = orders.action_status', 'left')
            ->where('orders.action_status >', 0);

        if ($searchText !== '') {
            $builder->groupStart()
                ->like('orders.trackID', $searchText)
                ->orLike('orders.orderID', $searchText)
                ->orLike('orders.customerFullname', $searchText)
                ->orLike('orders.detailSKUName', $searchText)
                ->orLike('orders.orderIDShow', $searchText)
                ->orLike('branches.branch_name', $searchText)
                ->orLike('orders.customerTel', $searchText)
                ->orLike('orders.customerEmail', $searchText)
                ->orLike('statuses.status_name', $searchText)
                ->groupEnd();
        }

        $statusIds = $this->parseStatusIds($rawStatusIds);
        if ($statusIds !== []) {
            $builder->whereIn('orders.action_status', $statusIds);
        }
        if ($start !== null) {
            $builder->where('orders.requestDate >=', $start->format('Y-m-d 00:00:00'));
        }
        if ($end !== null) {
            $builder->where('orders.requestDate <', $end->modify('+1 day')->format('Y-m-d 00:00:00'));
        }
        if ($branchId !== null) {
            $builder->where('orders.branchID', $branchId);
        }

        $rows = $builder
            ->orderBy('orders.requestDate', 'DESC')
            ->orderBy('orders.request_id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['TotalDay']    = $this->totalDays($row['requestDate'], $row['date_complete'], true);
            $row['CMGTotalDay'] = match ((string) ($row['waranty_cmg'] ?? '')) {
                'OUT'   => $this->totalDays($row['date_repair_waranty'], $row['date_complete'], false),
                'UNW', '' => $this->totalDays($row['date_repair'], $row['date_complete'], false),
                default => 0,
            };
        }
        unset($row);

        return $rows;
    }

    /** @return list<array{status_id:int, status_name:string}> */
    public function statuses(): array
    {
        return array_map(
            static fn (array $row): array => [
                'status_id'   => (int) $row['status_id'],
                'status_name' => (string) $row['status_name'],
            ],
            $this->db->table('statusaction')
                ->select('status_id, status_name')
                ->orderBy('status_id', 'ASC')
                ->get()
                ->getResultArray(),
        );
    }

    /** @return array<int, int> */
    public function statusCounts(?int $branchId): array
    {
        $builder = $this->db->table('request_order')
            ->select('action_status, COUNT(*) AS total', false)
            ->where('action_status >', 0);
        if ($branchId !== null) {
            $builder->where('branchID', $branchId);
        }

        $counts = [];
        foreach ($builder->groupBy('action_status')->get()->getResultArray() as $row) {
            $counts[(int) $row['action_status']] = (int) $row['total'];
        }

        return $counts;
    }

    private function parseDate(mixed $value, string $label): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) !== 10) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }

        $date   = DateTimeImmutable::createFromFormat('!d/m/Y', $value, new DateTimeZone(date_default_timezone_get()));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('d/m/Y') !== $value
        ) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }

        return $date;
    }

    private function totalDays(mixed $start, mixed $end, bool $inclusive): ?int
    {
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return null;
        }

        try {
            $interval = (new DateTimeImmutable($start))->setTime(0, 0)->diff((new DateTimeImmutable($end))->setTime(0, 0));
        } catch (\Exception) {
            return null;
        }

        if (! is_int($interval->days)) {
            return null;
        }

        $days = $interval->invert === 1 ? -$interval->days : $interval->days;

        return $days + ($inclusive ? 1 : 0);
    }
}
