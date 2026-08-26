<?php

namespace App\Controllers;

use App\Master\MenuStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class Menu extends BaseController
{
    public function listing(): string
    {
        $this->assertAdmin();

        return $this->renderList($this->searchTerm());
    }

    public function add(): string
    {
        $this->assertAdmin();

        return $this->renderForm(null);
    }

    public function edit(string $rawId): string
    {
        $this->assertAdmin();
        $id = $this->positiveInteger($rawId);
        $row = $id === null ? null : (new MenuStore(db_connect()))->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($row);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null);
    }

    public function update(string $rawId): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    private function renderList(string $search): string
    {
        $store = new MenuStore(db_connect());
        $actions = $this->actionLink('/menu/new', 'Add New');

        return $this->layout('Menu Management', view('menu_list', [
            'rows' => $store->all($search), 'search' => $search, 'caption' => 'Menu List',
        ]), ['actions' => $actions, 'subtitle' => 'Add, Edit, Delete']);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row): string
    {
        $store = new MenuStore(db_connect());

        return $this->layout('Menu Management', view('menu_form', [
            'row' => $row, 'menuGroups' => $store->menuGroups(), 'caption' => 'Enter Menu Details',
        ]), ['subtitle' => 'Add / Edit Menu']);
    }

    private function searchTerm(): string
    {
        $rawSearch = $this->request->getGet('search');

        return is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
    }

    private function save(?int $id): RedirectResponse|ResponseInterface
    {
        $result = (new MenuStore(db_connect()))->save(
            $id,
            $this->request->getPost('name'),
            $this->request->getPost('group_type'),
        );

        return match ($result) {
            'created', 'updated' => redirect()->to('/menu'),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_menu_group']),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_menu_group']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'menu_group_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'menu_group_unavailable']),
        };
    }

    private function assertAdmin(): void
    {
        if ((int) service('session')->get('role') !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function positiveInteger(string $value): ?int
    {
        return preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
