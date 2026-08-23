<?php

namespace App\Master;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

final class MasterDataStore
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(string $type, string $search = ''): array
    {
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            return [];
        }
        $query = $this->db->table($definition['table'])->orderBy($definition['pk'], 'ASC')->limit(100);
        if ($search !== '') {
            $query->like($definition['label'], $search);
        }

        return $query->get()->getResultArray();
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
        if ($definition['table'] === 'branch') {
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
     * @param array<string, array{kind: string, max?: int, required?: bool}> $fields
     * @param array<string, mixed> $input
     * @return array<string, int|string|null>|null
     */
    private function normalize(array $fields, array $input): ?array
    {
        $values = [];
        foreach ($fields as $name => $rule) {
            $raw = $input[$name] ?? null;
            if ($rule['kind'] === 'int') {
                if (is_string($raw) && preg_match('/^[1-9][0-9]*$/D', $raw) === 1) {
                    $raw = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                }
                if (! is_int($raw) || $raw < 1) {
                    return null;
                }
                $values[$name] = $raw;
                continue;
            }
            if (! is_string($raw)) {
                return null;
            }
            $raw = trim($raw);
            if (($rule['required'] ?? false) && $raw === '') {
                return null;
            }
            if (mb_strlen($raw) > ($rule['max'] ?? 250)) {
                return null;
            }
            $values[$name] = $raw === '' ? null : $raw;
        }

        return $values;
    }
}
