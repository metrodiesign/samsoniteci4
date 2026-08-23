<?php

namespace App\Master;

use CodeIgniter\Database\BaseConnection;

final class BackgroundStore
{
    public const FIELDS = [
        'image_track_laptop', 'image_track_mobile', 'image_trackstatus_laptop', 'image_trackstatus_mobile',
        'image_contact_laptop', 'image_contact_mobile', 'image_track_laptop_th', 'image_track_mobile_th',
        'image_trackstatus_laptop_th', 'image_trackstatus_mobile_th', 'image_contact_laptop_th', 'image_contact_mobile_th',
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->table('tbl_background_web')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $id > 0
            ? $this->db->table('tbl_background_web')->where('id', $id)->get()->getRowArray()
            : null;
    }

    /** @param array<string, string> $images */
    public function save(?int $id, mixed $status, array $images): string
    {
        $status = is_string($status) && preg_match('/\A[12]\z/D', $status) === 1 ? (int) $status : null;
        $row = $id === null ? null : $this->find($id);
        if ($status === null || ($id !== null && $row === null) || ($id === null && $images === [])) {
            return $id !== null && $row === null ? 'not_found' : 'invalid';
        }
        foreach ($images as $field => $name) {
            if (! in_array($field, self::FIELDS, true) || preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) !== 1) {
                return 'invalid';
            }
        }
        $values = ['status' => $status, 'date' => date('Y-m-d H:i:s'), ...$images];
        if ($id === null) {
            return $this->db->table('tbl_background_web')->insert($values) ? 'created' : 'failed';
        }

        return $this->db->table('tbl_background_web')->where('id', $id)->update($values) ? 'updated' : 'failed';
    }

    /** @return array<string, mixed>|null */
    public function delete(int $id): ?array
    {
        $row = $this->find($id);
        if ($row === null || ! $this->db->table('tbl_background_web')->where('id', $id)->delete()) {
            return null;
        }

        return $row;
    }

    public function published(string $field): ?string
    {
        if (! in_array($field, self::FIELDS, true)
            || ! $this->db->tableExists($this->db->prefixTable('tbl_background_web'), false)) {
            return null;
        }
        $value = $this->db->table('tbl_background_web')
            ->select($field)
            ->where('status', 1)
            ->where($field . ' IS NOT NULL', null, false)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow($field);

        return is_string($value) && preg_match('/\A[a-f0-9]{32}\.png\z/D', $value) === 1 ? $value : null;
    }
}
