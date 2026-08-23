<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;

final class OrderStore
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listing(int $status, ?int $branchId, string $search, int $page): array
    {
        $query = $this->db->table('request_order')
            ->select('request_id, requestDate, trackID, orderIDShow, customerFullname, customerTel, branchID, action_status')
            ->where('action_status', $status)
            ->orderBy('request_id', 'DESC')
            ->limit(50, ($page - 1) * 50);
        if ($branchId !== null) {
            $query->where('branchID', $branchId);
        }
        if ($search !== '') {
            $query->groupStart()
                ->like('trackID', $search)
                ->orLike('orderIDShow', $search)
                ->orLike('customerFullname', $search)
                ->orLike('customerTel', $search)
                ->groupEnd();
        }

        return $query->get()->getResultArray();
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
