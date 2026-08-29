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

    public function legacyListing(): string
    {
        $this->assertAdmin();

        return $this->renderList($this->searchTerm('searchText'));
    }

    public function add(): string
    {
        $this->assertAdmin();

        return $this->renderForm(null);
    }

    public function legacyAdd(): string
    {
        return $this->add();
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

    public function legacyEditMissing(): RedirectResponse
    {
        $this->assertAdmin();

        return redirect()->to('/menuListing');
    }

    public function legacyEdit(string $rawId): string
    {
        return $this->edit($rawId);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/menu');
    }

    public function legacyCreate(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/addNewMenu');
    }

    public function update(string $rawId): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id, '/menu');
    }

    public function legacyUpdate(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();
        $id = $this->positiveInteger((string) $this->request->getPost('group_id'));
        if ($id === null) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_menu_group']);
        }

        return $this->save($id, '/menuListing');
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
            'legacyAction' => $row === null ? 'addMenu' : 'editMenu',
        ]), ['subtitle' => 'Add / Edit Menu']);
    }

    private function searchTerm(string $legacyField = 'search'): string
    {
        $rawSearch = $legacyField === 'search'
            ? $this->request->getGet('search')
            : $this->request->getPost($legacyField);

        return is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
    }

    private function save(?int $id, string $successPath): RedirectResponse|ResponseInterface
    {
        $result = (new MenuStore(db_connect()))->save(
            $id,
            $this->request->getPost('name'),
            $this->request->getPost('group_type'),
        );

        return match ($result) {
            'created', 'updated' => redirect()->to($successPath),
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
