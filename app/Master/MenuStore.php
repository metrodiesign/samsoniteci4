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
    public function all(string $search = ''): array
    {
        $query = $this->db->table('group_menu')->orderBy('id', 'ASC')->limit(100);
        if ($search !== '') {
            $query->like('name', $search);
        }

        return $query->get()->getResultArray();
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
            $values['cdate'] = date('Y-m-d H:i:s');

            return $this->db->table('group_menu')->insert($values) ? 'created' : 'failed';
        }

        return $this->db->table('group_menu')->where('id', $id)->update($values) ? 'updated' : 'failed';
    }

    private const ORDER_GROUP_TYPE = 3;

    private const BRANCH_HIDDEN_LINKS = ['TrackingListing', 'TrackingcloseListing'];

    /**
     * Visible sidebar menu, grouped by group_type in the current group's CSV order.
     *
     * When $branchId is not null (a branch user, per AuthenticationFilter/LoginService)
     * the two order queues in BRANCH_HIDDEN_LINKS are dropped by menu_link identity, and the
     * ORDER group (group_type 3) is renumbered over the survivors so its labels stay 1..n.
     *
     * @return list<array{group_id: int, group_name: string, icon: string, items: list<array{menu_name: string, menu_link: string}>}>
     */
    public function visible(int $groupId, ?int $branchId = null): array
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
        // Preserve CSV order, first occurrence of a duplicate group id wins.
        $orderedTypes = [];
        foreach ($types as $type) {
            if (! in_array($type, $orderedTypes, true)) {
                $orderedTypes[] = $type;
            }
        }
        $rows = $this->db->table('tbl_menu')
            ->select('menu_name, menu_link, group_type')
            ->whereIn('group_type', $orderedTypes)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $meta = $this->groupTypeMeta();

        $groups = [];
        foreach ($orderedTypes as $type) {
            $items = [];
            $ordinal = 0;
            $seen = []; // per-group dedup: a duplicate link is dropped only within the same group
                        // (a link shared across groups shows in each, matching CI3)
            foreach ($rows as $item) {
                if ((int) $item['group_type'] !== $type) {
                    continue;
                }
                $legacyLink = (string) $item['menu_link'];
                if (in_array($legacyLink, self::RETIRED_LINKS, true)) {
                    continue;
                }
                $link = self::LINK_REPLACEMENTS[$legacyLink] ?? $legacyLink;
                if (preg_match('/\A[a-zA-Z0-9_\/-]+\z/D', $link) !== 1 || isset($seen[$link])) {
                    continue;
                }
                if ($branchId !== null && in_array($link, self::BRANCH_HIDDEN_LINKS, true)) {
                    continue; // hidden before numbering so the ORDER group does not skip a number
                }
                $seen[$link] = true;
                $name = (string) $item['menu_name'];
                if ($type === self::ORDER_GROUP_TYPE) {
                    $ordinal++;
                    $name = $ordinal . '. ' . $name;
                }
                $items[] = ['menu_name' => $name, 'menu_link' => $link];
            }
            if ($items === []) {
                continue; // a group with no visible items renders no empty heading in the sidebar
            }
            $groups[] = [
                'group_id'   => $type,
                'group_name' => $meta[$type]['name'] ?? '',
                'icon'       => $meta[$type]['icon'] ?? '',
                'items'      => $items,
            ];
        }

        // No group_type table (unmigrated database): collapse into a single unnamed,
        // default-icon group so the sidebar still renders every visible link.
        if ($meta === null) {
            $merged = [];
            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    $merged[] = $item;
                }
            }

            return [['group_id' => 0, 'group_name' => '', 'icon' => '', 'items' => $merged]];
        }

        return $groups;
    }

    /**
     * Group name/icon keyed by group_type id, or null when the table is absent.
     *
     * @return array<int, array{name: string, icon: string}>|null
     */
    private function groupTypeMeta(): ?array
    {
        if (! $this->db->tableExists($this->db->prefixTable('group_type'), false)) {
            return null;
        }
        $meta = [];
        foreach ($this->db->table('group_type')->select('group_type_id, group_type_name, icon_menu')->get()->getResultArray() as $row) {
            $meta[(int) $row['group_type_id']] = [
                'name' => (string) $row['group_type_name'],
                'icon' => (string) $row['icon_menu'],
            ];
        }

        return $meta;
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
