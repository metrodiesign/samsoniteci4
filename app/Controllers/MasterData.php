<?php

namespace App\Controllers;

use App\Master\MasterCatalog;
use App\Master\MasterDataStore;
use App\Master\BranchTypeImageStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class MasterData extends BaseController
{
    public function listing(string $type): string
    {
        $definition = $this->authorizedDefinition($type);
        $rawSearch  = $this->request->getGet('search');
        $search     = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $rawPage    = $this->request->getGet('page');
        $page       = $rawPage === null ? 1 : (is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/D', $rawPage) === 1 ? (int) $rawPage : 0);
        if ($page < 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderList($type, $definition, $search, $page);
    }

    public function add(string $type): string
    {
        $definition = $this->authorizedDefinition($type);

        return $this->renderForm($type, $definition, null);
    }

    public function edit(string $type, string $rawId): string
    {
        $definition = $this->authorizedDefinition($type);
        $id         = $this->positiveInteger($rawId);
        $row        = $id === null ? null : (new MasterDataStore(db_connect()))->find($type, $id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($type, $definition, $row);
    }

    public function create(string $type): RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);

        return $this->save($type, null);
    }

    public function update(string $type, string $rawId): RedirectResponse|ResponseInterface
    {
        $this->authorizedDefinition($type);
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($type, $id);
    }

    public function delete(string $type, string $rawId): ResponseInterface
    {
        $this->authorizedDefinition($type);
        $id = $this->positiveInteger($rawId);
        $result = $id === null ? 'not_found' : (new MasterDataStore(db_connect()))->delete($type, $id);

        return match ($result) {
            'deleted' => $this->response->setStatusCode(204),
            'referenced' => $this->response->setStatusCode(409)->setJSON(['error' => 'master_referenced']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'master_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'master_unavailable']),
        };
    }

    public function image(string $name): ResponseInterface
    {
        $path = WRITEPATH . 'uploads/branch-types/' . $name;
        if (preg_match('/\A[a-f0-9]{32}\.png\z/D', $name) !== 1 || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'branch_type_image_not_found']);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'public, max-age=86400, immutable')
            ->setBody((string) file_get_contents($path));
    }

    /** @return array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} */
    private function authorizedDefinition(string $type): array
    {
        // CI3 parity (business decision 2026-08-23): master data is open to every
        // authenticated role; the route-level authorized filters remain in force.
        $definition = MasterCatalog::definition($type);
        if ($definition === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $definition;
    }

    private function save(string $type, ?int $id): RedirectResponse|ResponseInterface
    {
        $db = db_connect();
        $store = new MasterDataStore($db);
        $images = new BranchTypeImageStore();
        $newImage = null;
        $oldImage = $type === 'branchtype' && $id !== null
            ? ($store->find($type, $id)['branch_type_image'] ?? null)
            : null;
        if ($type === 'branchtype') {
            try {
                $newImage = $images->store($this->request->getFile('branch_type_image'));
            } catch (\InvalidArgumentException) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_branch_type_image']);
            }
        }
        $trusted = $newImage === null ? [] : ['branch_type_image' => $newImage];
        $result = $store->save($type, $id, $this->request->getPost(), $trusted);
        if (in_array($result, ['created', 'updated'], true)) {
            if ($newImage !== null && is_string($oldImage)) {
                $images->remove($oldImage);
            }
        } elseif ($newImage !== null) {
            $images->remove($newImage);
        }

        return match ($result) {
            'created', 'updated' => redirect()->to('/master/' . $type),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_master']),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_master']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'master_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'master_unavailable']),
        };
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition
     */
    private function renderList(string $type, array $definition, string $search, int $page): string
    {
        $store = new MasterDataStore(db_connect());
        $rows = $store->all($type, $search, $page);
        if ($type === 'branch') {
            $branchTypes = [];
            foreach ($store->options('branchtype') as $option) {
                $branchTypes[(string) $option['value']] = (string) $option['label'];
            }
            foreach ($rows as &$row) {
                $row['branch_type'] = $branchTypes[(string) ($row['branch_type'] ?? '')] ?? '';
            }
            unset($row);
        } elseif ($type === 'book') {
            $branches = [];
            foreach ($store->options('branch') as $option) {
                $branches[(string) $option['value']] = (string) $option['label'];
            }
            foreach ($rows as &$row) {
                $row['branch_id'] = $branches[(string) ($row['branch_id'] ?? '')] ?? '';
                $row['status'] = (int) ($row['status'] ?? 0) === 1 ? 'Publishing' : 'Unpublish';
            }
            unset($row);
        }
        $actions = $this->actionLink('/master/' . rawurlencode($type) . '/new', 'Add New');
        $heading = MasterCatalog::heading($type, false);

        return $this->layout($heading['title'], view('master_list', [
            'definition' => $definition,
            'rows'       => $rows,
            'search'     => $search,
            'type'       => $type,
            'page'       => $page,
            'caption'    => $heading['caption'],
        ]), ['actions' => $actions, 'subtitle' => $heading['subtitle']]);
    }

    /**
     * @param array{table: string, pk: string, label: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition
     * @param array<string, mixed>|null $row
     */
    private function renderForm(string $type, array $definition, ?array $row): string
    {
        $store     = new MasterDataStore(db_connect());
        $fkOptions = [];
        foreach ($definition['fields'] as $field => $rule) {
            if (isset($rule['fk'])) {
                $fkOptions[$field] = $store->options($rule['fk']);
            }
        }

        $heading = MasterCatalog::heading($type, true);

        return $this->layout($heading['title'], view('master_form', [
            'definition' => $definition,
            'row'        => $row,
            'type'       => $type,
            'fkOptions'  => $fkOptions,
            'caption'    => $heading['caption'],
        ]), ['subtitle' => $heading['subtitle']]);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : (int) $integer;
    }
}
