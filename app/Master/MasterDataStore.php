<?php

namespace App\Master;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

final class MasterDataStore
{
    public function __construct(private BaseConnection $db)
    {
    }

    private const PAGE_SIZE = 50;

    /** @var array<string, string> */
    private const BRANCH_SCOPE_COLUMNS = [
        'branch' => 'branch.branch_id',
        'book' => 'book.branch_id',
    ];

    /** @return list<array<string, mixed>> */
    public function all(
        string $type,
        string $search = '',
        int $page = 1,
        ?int $branchId = null,
        ?int $legacyOffset = null,
    ): array
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            return [];
        }
        $page  = $page < 1 ? 1 : $page;
        $offset = $legacyOffset ?? (($page - 1) * self::PAGE_SIZE);
        $query = $this->db->table($definition['table'])
            ->orderBy($definition['pk'], 'ASC')
            ->limit(self::PAGE_SIZE, $offset);
        $branchScopeColumn = self::BRANCH_SCOPE_COLUMNS[$type] ?? null;
        if ($branchScopeColumn !== null && $branchId !== null && $branchId > 0) {
            $query->where($branchScopeColumn, $branchId);
        }
        if ($search !== '') {
            $columns = $definition['searchColumns'] ?? [$definition['label']];
            if (isset($definition['searchJoins'])) {
                $query->select($definition['table'] . '.*');
                foreach ($definition['searchJoins'] as $join) {
                    $query->join($join['table'], $join['on'], 'left');
                }
            }
            $query->groupStart();
            foreach ($columns as $index => $column) {
                $index === 0 ? $query->like($column, $search) : $query->orLike($column, $search);
            }
            $query->groupEnd();
        }

        return $query->get()->getResultArray();
    }

    public function count(string $type, string $search = '', ?int $branchId = null): int
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            return 0;
        }
        $query = $this->db->table($definition['table']);
        $branchScopeColumn = self::BRANCH_SCOPE_COLUMNS[$type] ?? null;
        if ($branchScopeColumn !== null && $branchId !== null && $branchId > 0) {
            $query->where($branchScopeColumn, $branchId);
        }
        if ($search !== '') {
            $columns = $definition['searchColumns'] ?? [$definition['label']];
            if (isset($definition['searchJoins'])) {
                foreach ($definition['searchJoins'] as $join) {
                    $query->join($join['table'], $join['on'], 'left');
                }
            }
            $query->groupStart();
            foreach ($columns as $index => $column) {
                $index === 0 ? $query->like($column, $search) : $query->orLike($column, $search);
            }
            $query->groupEnd();
        }

        return $query->countAllResults();
    }

    /** @return list<array<string, mixed>> */
    public function options(string $type): array
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            return [];
        }

        return $this->db->table($definition['table'])
            ->select($definition['pk'] . ' AS value, ' . $definition['label'] . ' AS label')
            ->orderBy($definition['pk'], 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function find(string $type, int $id): ?array
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null || $id < 1) {
            return null;
        }

        return $this->db->table($definition['table'])
            ->where($definition['pk'], $id)
            ->get()
            ->getRowArray();
    }

    /**
     * @param array<string, mixed>  $input
     * @param array<string, string> $trusted
     */
    public function save(string $type, ?int $id, array $input, array $trusted = []): string
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null || ($id !== null && $id < 1)) {
            return 'not_found';
        }
        $values = $this->normalize($definition['fields'], $input);
        if ($values === null) {
            return 'invalid';
        }
        if ($type === 'branchtype' && isset($trusted['branch_type_image'])
            && preg_match('/\A[a-f0-9]{32}\.png\z/D', $trusted['branch_type_image']) === 1) {
            $values['branch_type_image'] = $trusted['branch_type_image'];
        }
        $duplicate = $this->db->table($definition['table'])
            ->where($definition['label'], $values[$definition['label']]);
        if ($id !== null) {
            $duplicate->where($definition['pk'] . ' !=', $id);
        }
        if ($duplicate->countAllResults() !== 0) {
            return 'duplicate';
        }

        $timestamp = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if ($id === null) {
            $values['cdate'] = $timestamp;

            return $this->db->table($definition['table'])->insert($values) ? 'created' : 'failed';
        }
        if ($this->find($type, $id) === null) {
            return 'not_found';
        }
        if (in_array($type, ['statustype', 'brand', 'condition', 'estimateprice', 'fixed', 'provider'], true)) {
            $values['cdate'] = $timestamp;
        } elseif ($definition['table'] === 'branch') {
            $values['udate'] = $timestamp;
        }

        return $this->db->table($definition['table'])
            ->where($definition['pk'], $id)
            ->update($values) ? 'updated' : 'failed';
    }

    public function delete(string $type, int $id): string
    {
        $definition = MasterCatalog::definition($type);
        $row = $definition === null || $id < 1 ? null : $this->find($type, $id);
        if ($definition === null || $row === null) {
            return 'not_found';
        }
        foreach (MasterCatalog::references($type) as $reference) {
            if (! $this->db->tableExists($reference['table'])
                || ! $this->db->fieldExists($reference['column'], $reference['table'])) {
                continue;
            }
            $value = ($reference['mode'] ?? '') === 'label' ? $row[$definition['label']] : $id;
            $query = $this->db->table($reference['table']);
            if (($reference['mode'] ?? '') === 'csv') {
                $query->groupStart()
                    ->where($reference['column'], (string) $value)
                    ->orLike($reference['column'], (string) $value . ',', 'after')
                    ->orLike($reference['column'], ',' . (string) $value, 'before')
                    ->orLike($reference['column'], ',' . (string) $value . ',')
                    ->groupEnd();
            } else {
                $query->where($reference['column'], $value);
            }
            if ($query->countAllResults() !== 0) {
                return 'referenced';
            }
        }

        return $this->db->table($definition['table'])
            ->where($definition['pk'], $id)
            ->delete() ? 'deleted' : 'failed';
    }

    /**
     * @param array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string}> $fields
     * @param array<string, mixed> $input
     * @return array<string, int|string|null>|null
     */
    private function normalize(array $fields, array $input): ?array
    {
        $values = [];
        foreach ($fields as $name => $rule) {
            $raw = $input[$name] ?? null;
            if ($raw === null) {
                if ($rule['preserveNull'] ?? false) {
                    $values[$name] = null;
                    continue;
                }
                if (! ($rule['required'] ?? false)) {
                    continue;
                }
            }
            if ($rule['kind'] === 'int') {
                $min = ($rule['allowZero'] ?? false) ? 0 : 1;
                if (is_string($raw) && array_key_exists('emptyIntValue', $rule)) {
                    $raw = trim($raw);
                    if ($raw === '') {
                        $raw = $rule['emptyIntValue'];
                    }
                }
                if (is_string($raw) && preg_match('/^(0|[1-9][0-9]*)$/D', $raw) === 1) {
                    $raw = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]);
                }
                if (! is_int($raw) || $raw < $min) {
                    return null;
                }
                $values[$name] = $raw;
                continue;
            }
            if (! is_string($raw)) {
                return null;
            }
            if ($rule['trim'] ?? true) {
                $raw = trim($raw);
            }
            if (($rule['required'] ?? false) && $raw === '') {
                return null;
            }
            if (mb_strlen($raw) > ($rule['max'] ?? 250)) {
                return null;
            }
            $values[$name] = $raw === '' && ! ($rule['preserveEmpty'] ?? false) ? null : $raw;
        }

        return $values;
    }
}
