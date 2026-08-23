<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;

final class OrderStore
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listing(int $status, ?int $branchId, string $search, int $page, string $sdate = ''): array
    {
        $query = $this->db->table('request_order')
            ->select('request_order.request_id, request_order.requestDate, request_order.trackID, request_order.orderID, request_order.orderIDShow, request_order.customerFullname, request_order.customerTel, request_order.customerEmail, request_order.branchID, request_order.action_status, request_order.date_complete, statusaction.status_name')
            ->join('statusaction', 'statusaction.status_id = request_order.action_status', 'left')
            ->where('request_order.action_status', $status)
            ->orderBy('request_order.request_id', 'DESC')
            ->limit(50, ($page - 1) * 50);
        if ($branchId !== null) {
            $query->where('request_order.branchID', $branchId);
        }
        $range = $this->dateRange($sdate);
        if ($range !== null) {
            $query->where('request_order.requestDate >=', $range[0])
                ->where('request_order.requestDate <', $range[1]);
        }
        if ($search !== '') {
            $query->groupStart()
                ->like('request_order.trackID', $search)
                ->orLike('request_order.orderIDShow', $search)
                ->orLike('request_order.customerFullname', $search)
                ->orLike('request_order.customerTel', $search)
                ->groupEnd();
        }

        return $query->get()->getResultArray();
    }

    /**
     * Latest tracking_status.description_th per (orderID, customerTel) pair in one query.
     *
     * @param list<array{orderID: string, customerTel: string}> $pairs
     * @return array<string, string> key: orderID . "\x00" . customerTel
     */
    public function latestStatusUpdates(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }
        $orderIds = array_values(array_unique(array_column($pairs, 'orderID')));
        $tels = array_values(array_unique(array_column($pairs, 'customerTel')));
        // ponytail: IN x IN over-fetch, เปลี่ยนเป็น tuple IN ถ้าหน้าโตเกิน 50 แถว
        $rows = $this->db->table('uploadstaus')
            ->select('uploadstaus.tracking_id, uploadstaus.Telephone, tracking_status.description_th')
            ->join('tracking_status', 'tracking_status.status_id = uploadstaus.tracking_status', 'left')
            ->whereIn('uploadstaus.tracking_id', $orderIds)
            ->whereIn('uploadstaus.Telephone', $tels)
            ->orderBy('uploadstaus.id', 'ASC')
            ->get()
            ->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['tracking_id'] . "\x00" . $row['Telephone']] = (string) $row['description_th'];
        }

        return $map;
    }

    /**
     * Parse dd/mm/yyyy (CE) into [startInclusive, endExclusive]; null when empty or malformed.
     *
     * @return array{0: string, 1: string}|null
     */
    private function dateRange(string $sdate): ?array
    {
        if ($sdate === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $sdate);
        if ($date === false || $date->format('d/m/Y') !== $sdate) {
            return null;
        }

        return [$date->format('Y-m-d 00:00:00'), $date->modify('+1 day')->format('Y-m-d 00:00:00')];
    }

    /** @return array<string, mixed>|null */
    public function find(int $actorRole, ?int $actorBranch, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $query = $this->db->table('request_order')->where('request_id', $id);
        if ($actorRole !== 1) {
            $query->where('branchID', $actorBranch);
        }

        return $query->get()->getRowArray();
    }

    /** @param array<string, mixed> $input */
    public function edit(int $actorRole, ?int $actorBranch, int $id, array $input): string
    {
        if ($this->find($actorRole, $actorBranch, $id) === null) {
            return 'not_found';
        }
        foreach (['customer_name', 'customer_tel', 'customer_email', 'note'] as $field) {
            if (! is_string($input[$field] ?? null)) {
                return 'invalid';
            }
            $input[$field] = trim($input[$field]);
        }
        $typeId = $this->positiveInteger($input['type_id'] ?? null);
        $brandId = $this->positiveInteger($input['brand_id'] ?? null);
        if ($input['customer_name'] === '' || mb_strlen($input['customer_name']) > 250
            || preg_match('/\A[0-9]{10,20}\z/D', $input['customer_tel']) !== 1
            || ($input['customer_email'] !== '' && filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL) === false)
            || strlen($input['customer_email']) > 100 || mb_strlen($input['note']) > 4000
            || $typeId === null || $brandId === null
            || $this->db->table('type')->where('type_id', $typeId)->countAllResults() !== 1
            || $this->db->table('brand')->where('brand_id', $brandId)->countAllResults() !== 1) {
            return 'invalid';
        }
        $updated = $this->db->table('request_order')->where('request_id', $id)->update([
            'customerFullname' => $input['customer_name'], 'customerTel' => $input['customer_tel'],
            'customerEmail' => strtolower($input['customer_email']), 'detailTypeId' => $typeId,
            'detailBrandId' => $brandId, 'detailNote' => $input['note'],
        ]);

        return $updated ? 'updated' : 'failed';
    }

    public function softDelete(int $actorRole, ?int $actorBranch, int $id): string
    {
        $row = $this->find($actorRole, $actorBranch, $id);
        if ($row === null) {
            return 'not_found';
        }
        if ((int) $row['action_status'] === 8) {
            return 'conflict';
        }
        $updated = $this->db->table('request_order')
            ->where('request_id', $id)
            ->where('action_status', $row['action_status'])
            ->update(['action_status' => 8]);

        return $updated && $this->db->affectedRows() === 1 ? 'deleted' : 'conflict';
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
