<?php

namespace App\Controllers;

use App\Master\MenuStore;
use App\Presentation\LegacyViewRenderer;
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
        $content = (new LegacyViewRenderer())->render('master/menus', [
            'menuRecords' => LegacyViewRenderer::escapedRecords($store->all($search)),
            'searchText' => esc($search),
        ]);

        return $this->layout('Tracking : Menu Listing', $content, ['contentOwnsWrapper' => true]);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row): string
    {
        $store = new MenuStore(db_connect());

        $groups = LegacyViewRenderer::escapedRecords(array_map(
            static fn (array $group): array => [
                'group_type_id' => $group['id'], 'group_type_name' => $group['name'],
            ],
            $store->menuGroups(),
        ));
        $variables = ['nemugroups' => $groups];
        if ($row !== null) {
            $variables['MenuInfo'] = LegacyViewRenderer::escapedRecords([$row]);
        }
        $content = (new LegacyViewRenderer())->render(
            $row === null ? 'master/add_menus' : 'master/ecit_menus',
            $variables,
        );

        return $this->layout(
            $row === null ? 'Menu group : Add New User' : 'CodeInsect : Edit User',
            $content,
            ['contentOwnsWrapper' => true],
        );
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
