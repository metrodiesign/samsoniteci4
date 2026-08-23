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

        return $this->render($type, $definition, null, $search);
    }

    public function edit(string $type, string $rawId): string
    {
        $definition = $this->authorizedDefinition($type);
        $id         = $this->positiveInteger($rawId);
        $row        = $id === null ? null : (new MasterDataStore(db_connect()))->find($type, $id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render($type, $definition, $row, '');
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

    /** @return array{table: string, pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool}>} */
    private function authorizedDefinition(string $type): array
    {
        if ((int) service('session')->get('role') !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
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
     * @param array{table: string, pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool}>} $definition
     * @param array<string, mixed>|null $row
     */
    private function render(string $type, array $definition, ?array $row, string $search): string
    {
        return view('layout', [
            'title'   => 'Master data: ' . $type,
            'content' => view('master_data', [
                'definition' => $definition,
                'rows'       => (new MasterDataStore(db_connect()))->all($type, $search),
                'row'        => $row,
                'search'     => $search,
                'type'       => $type,
            ]),
        ]);
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
