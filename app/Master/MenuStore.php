<?php

namespace App\Master;

use CodeIgniter\Database\BaseConnection;

final class MenuStore
{
    /** @var array<string, string> */
    private const LINK_REPLACEMENTS = [
        'branchListing' => 'master/branch',
        'branchtypeListing' => 'master/branchtype',
        'bookListing' => 'master/book',
        'producttypeListing' => 'master/producttype',
        'brandListing' => 'master/brand',
        'conditionListing' => 'master/condition',
        'estimatepriceListing' => 'master/estimateprice',
        'fixedListing' => 'master/fixed',
        'providerListing' => 'master/provider',
        'statustypeListing' => 'master/statustype',
        'userListing' => 'users',
        'BackgroundListing' => 'backgrounds',
        'menuListing' => 'menu',
    ];

    private const RETIRED_LINKS = ['ReportTrackingListingTest'];

    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->table('group_menu')->orderBy('id', 'ASC')->limit(100)->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db->table('group_menu')->where('id', $id)->get()->getRowArray();
    }

    public function save(?int $id, mixed $name, mixed $groupTypes): string
    {
        $name = is_string($name) ? trim($name) : '';
        $types = is_array($groupTypes) ? $groupTypes : ($groupTypes === null ? [] : explode(',', (string) $groupTypes));
        $normalized = [];
        foreach ($types as $type) {
            if (! is_string($type) && ! is_int($type)) {
                return 'invalid';
            }
            $type = (string) $type;
            if (preg_match('/\A[1-9][0-9]{0,3}\z/D', $type) !== 1) {
                return 'invalid';
            }
            $normalized[(int) $type] = (int) $type;
        }
        ksort($normalized);
        if ($name === '' || mb_strlen($name) > 250) {
            return 'invalid';
        }
        if ($id !== null && ($id < 1 || $this->find($id) === null)) {
            return 'not_found';
        }
        $duplicate = $this->db->table('group_menu')->where('name', $name);
        if ($id !== null) {
            $duplicate->where('id !=', $id);
        }
        if ($duplicate->countAllResults() !== 0) {
            return 'duplicate';
        }
        $values = ['name' => $name, 'group_type' => implode(',', $normalized)];
        if ($id === null) {
            $values['cdate'] = gmdate('Y-m-d H:i:s');

            return $this->db->table('group_menu')->insert($values) ? 'created' : 'failed';
        }

        return $this->db->table('group_menu')->where('id', $id)->update($values) ? 'updated' : 'failed';
    }

    /** @return list<array{menu_name: string, menu_link: string}> */
    public function visible(int $groupId): array
    {
        if ($groupId < 1
            || ! $this->db->tableExists($this->db->prefixTable('group_menu'), false)
            || ! $this->db->tableExists($this->db->prefixTable('tbl_menu'), false)) {
            return [];
        }
        $row = $this->db->table('group_menu')->select('group_type')->where('id', $groupId)->get()->getRowArray();
        $types = isset($row['group_type']) && is_string($row['group_type'])
            ? array_values(array_filter(array_map('intval', explode(',', $row['group_type'])), static fn (int $id): bool => $id > 0))
            : [];
        if ($types === []) {
            return [];
        }
        $rows = $this->db->table('tbl_menu')
            ->select('menu_name, menu_link')
            ->whereIn('group_type', array_values(array_unique($types)))
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $visible = [];
        $seen = [];
        foreach ($rows as $item) {
            $legacyLink = (string) $item['menu_link'];
            if (in_array($legacyLink, self::RETIRED_LINKS, true)) {
                continue;
            }
            $link = self::LINK_REPLACEMENTS[$legacyLink] ?? $legacyLink;
            if (preg_match('/\A[a-zA-Z0-9_\/-]+\z/D', $link) !== 1 || isset($seen[$link])) {
                continue;
            }
            $seen[$link] = true;
            $visible[] = ['menu_name' => (string) $item['menu_name'], 'menu_link' => $link];
        }

        return $visible;
    }

    /** @return list<array{id: int, name: string}> */
    public function menuGroups(): array
    {
        if ($this->db->tableExists($this->db->prefixTable('group_type'), false)) {
            return array_map(
                static fn (array $row): array => ['id' => (int) $row['group_type_id'], 'name' => (string) $row['group_type_name']],
                $this->db->table('group_type')->orderBy('group_type_id', 'ASC')->get()->getResultArray(),
            );
        }
        if (! $this->db->tableExists($this->db->prefixTable('tbl_menu'), false)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['group_type'], 'name' => 'Group ' . (int) $row['group_type']],
            $this->db->table('tbl_menu')->select('group_type')->distinct()->orderBy('group_type', 'ASC')->get()->getResultArray(),
        );
    }
}
