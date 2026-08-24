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
            ->join('branch', 'branch.branch_id = request_order.branchID', 'left')
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
                ->orLike('request_order.orderID', $search)
                ->orLike('request_order.customerFullname', $search)
                ->orLike('request_order.detailSKUName', $search)
                ->orLike('branch.branch_name', $search)
                ->orLike('statusaction.status_name', $search)
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

    /**
     * Edit accepts the same field set as create (design §6) with the same validation rules. The
     * immutable columns (trackID/orderID/orderIDShow/numberID/bookID/branchID/branch_type_id/
     * action_status) are simply left out of the update. Images: no upload keeps the existing
     * detailImage untouched, an upload replaces the whole set (the controller owns rollback).
     *
     * @param array<string, mixed> $input
     * @param list<string> $imageNames
     */
    public function edit(int $actorRole, ?int $actorBranch, int $id, array $input, array $imageNames): string
    {
        if ($this->find($actorRole, $actorBranch, $id) === null) {
            return 'not_found';
        }
        foreach (['customer_name', 'customer_tel', 'customer_email', 'note', 'detail_sku_name', 'create_by_user'] as $field) {
            if (! is_string($input[$field] ?? null)) {
                return 'invalid';
            }
            $input[$field] = trim($input[$field]);
        }
        foreach (['customer_tel2', 'condition_other', 'estimateprice_other', 'fixed_other', 'detail_equipment', 'detail_number_waranty'] as $field) {
            $value = $input[$field] ?? '';
            if (! is_string($value)) {
                return 'invalid';
            }
            $input[$field] = trim($value);
        }
        $typeId = $this->positiveInteger($input['type_id'] ?? null);
        $brandId = $this->positiveInteger($input['brand_id'] ?? null);
        $warantyRaw = $input['waranty_type'] ?? '0';
        $warantyType = in_array($warantyRaw, ['0', '1'], true) ? (int) $warantyRaw : null;
        $numberWaranty = $warantyType === 1 ? $input['detail_number_waranty'] : '';
        $datePurchase = $this->purchaseDate($input['detail_date_purchase'] ?? '');
        $conditionIds = $this->catalogueIds($input['condition'] ?? null, 'condition', 'condition_id');
        $estimatePriceIds = $this->catalogueIds($input['estimateprice'] ?? null, 'estimateprice', 'estimateprice_id');
        $fixedIds = $this->catalogueIds($input['fixed'] ?? null, 'fixed', 'fixed_id');
        if ($typeId === null || $brandId === null || $warantyType === null
            || $datePurchase === null || $conditionIds === null || $estimatePriceIds === null || $fixedIds === null
            || $input['customer_name'] === '' || mb_strlen($input['customer_name']) > 250
            || preg_match('/\A[0-9]{10,20}\z/D', $input['customer_tel']) !== 1
            || ($input['customer_tel2'] !== '' && preg_match('/\A[0-9]{1,20}\z/D', $input['customer_tel2']) !== 1)
            || ($input['customer_email'] !== '' && (strlen($input['customer_email']) > 100 || filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL) === false))
            || $input['detail_sku_name'] === '' || mb_strlen($input['detail_sku_name']) > 100
            || mb_strlen($numberWaranty) > 100
            || mb_strlen($input['condition_other']) > 250
            || mb_strlen($input['estimateprice_other']) > 250
            || mb_strlen($input['fixed_other']) > 250
            || mb_strlen($input['detail_equipment']) > 4000
            || $input['create_by_user'] === '' || mb_strlen($input['create_by_user']) > 250
            || mb_strlen($input['note']) > 4000
            || $this->db->table('type')->where('type_id', $typeId)->countAllResults() !== 1
            || $this->db->table('brand')->where('brand_id', $brandId)->countAllResults() !== 1) {
            return 'invalid';
        }
        $values = [
            'customerFullname' => $input['customer_name'], 'customerTel' => $input['customer_tel'],
            'customerTel2' => $input['customer_tel2'], 'customerEmail' => strtolower($input['customer_email']),
            'detailTypeId' => $typeId, 'detailBrandId' => $brandId,
            'detailAgent' => ($input['detail_agent'] ?? null) === '1' ? 1 : 0,
            'detailSKUName' => $input['detail_sku_name'], 'warantyType' => $warantyType,
            'detailNumberWaranty' => $numberWaranty, 'detailDatePurchase' => $datePurchase,
            'detailCondition' => $conditionIds, 'detailConditionOther' => $input['condition_other'],
            'detailEstimatePrice' => $estimatePriceIds, 'detailEstimatePriceOther' => $input['estimateprice_other'],
            'detailFixed' => $fixedIds, 'detailFixedOther' => $input['fixed_other'],
            'detailEquipment' => $input['detail_equipment'], 'create_by_user' => $input['create_by_user'],
            'detailNote' => $input['note'],
        ];
        if ($imageNames !== []) {
            $values['detailImage'] = implode('|', $imageNames);
        }
        $updated = $this->db->table('request_order')->where('request_id', $id)->update($values);

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

    /**
     * Empty stays the legacy zero date; otherwise parse dd/mm/yyyy strictly (round-trip guards the
     * PHP overflow that turns 31/02 into 03/03). Malformed input returns null so the caller rejects.
     */
    private function purchaseDate(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '0000-00-00 00:00:00';
        }
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($date === false || $date->format('d/m/Y') !== $raw) {
            return null;
        }

        return $date->format('Y-m-d 00:00:00');
    }

    /**
     * Require at least one id, all present in the catalogue table, no duplicates (whereIn matches
     * distinct rows, so its count only equals the raw id count when every id is real and unique);
     * join in submission order for the print view, capped at the column width. null on any breach.
     */
    private function catalogueIds(mixed $raw, string $table, string $idColumn): ?string
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $ids = [];
        foreach ($raw as $value) {
            if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
                return null;
            }
            $ids[] = $value;
        }
        if ($this->db->table($table)->whereIn($idColumn, $ids)->countAllResults() !== count($ids)) {
            return null;
        }
        $joined = implode('|', $ids);

        return strlen($joined) > 250 ? null : $joined;
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
