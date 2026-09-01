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

    public function legacyListing(?string $rawOffset = null): string
    {
        $offset = is_string($rawOffset) && preg_match('/\A[0-9]+\z/D', $rawOffset) === 1
            ? (int) $rawOffset
            : 0;
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : null;
        $search = is_string($rawSearch) ? $rawSearch : '';
        $store = new MenuStore(db_connect());
        $total = $store->legacyUserCount($search, $this->legacyBranch());
        $content = (new LegacyViewRenderer(null, $this->legacyPagination($offset, $total)))
            ->render('master/menus', [
                'menuRecords' => LegacyViewRenderer::escapedRecords($store->legacyAll($search, $offset)),
                'searchText' => esc($search),
            ]);

        return $this->layout('Tracking : Menu Listing', $content, ['contentOwnsWrapper' => true]);
    }

    public function legacyControllerListing(?string $ignored = null): string
    {
        unset($ignored);

        return $this->legacyListing();
    }

    public function add(): string
    {
        $this->assertAdmin();

        return $this->renderForm(null);
    }

    public function legacyAdd(): string
    {
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

    public function legacyEditMissing(): RedirectResponse
    {
        return $this->legacyRedirect('/menuListing');
    }

    public function legacyEdit(string $rawId): string
    {
        $row = (new MenuStore(db_connect()))->find((int) $rawId);

        return $this->renderForm($row ?? [], true);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $this->assertAdmin();

        return $this->save(null, '/menu');
    }

    public function legacyCreateNonPost(): string
    {
        return $this->renderForm(null);
    }

    public function legacyCreate(): string|RedirectResponse|ResponseInterface
    {
        $validation = $this->legacyNameValidation();
        if ($validation['error'] !== null) {
            return $this->renderForm(
                null,
                false,
                ['name' => $validation['name']],
                ['name' => $validation['error']],
            );
        }

        return $this->saveLegacy(null, $validation['name']);
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

    public function legacyUpdateMissing(): RedirectResponse
    {
        return $this->legacyRedirect('/menuListing');
    }

    public function legacyUpdate(): string|RedirectResponse|ResponseInterface
    {
        $rawId = $this->request->getPost('group_id');
        $validation = $this->legacyNameValidation();
        if ($validation['error'] !== null) {
            if ($rawId === null || $rawId === '') {
                return $this->legacyRedirect('/menuListing');
            }
            $id = is_scalar($rawId) ? $this->positiveInteger((string) $rawId) : null;
            $row = $id === null ? null : (new MenuStore(db_connect()))->find($id);

            return $this->renderForm($row ?? [], true, [], ['name' => $validation['error']]);
        }

        $id = is_scalar($rawId) ? $this->positiveInteger((string) $rawId) : null;
        if ($id === null) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_menu_group']);
        }

        return $this->saveLegacy($id, $validation['name']);
    }

    private function legacyPagination(int $offset, int $total): string
    {
        $pages = (int) ceil($total / 15);
        if ($pages <= 1) {
            return '';
        }
        $firstUrl = base_url('menuListing/');
        $currentOffset = $offset > $total ? ($pages - 1) * 15 : $offset;
        $currentPage = intdiv($currentOffset, 15) + 1;
        $numberLinks = 5;
        $start = ($currentPage - $numberLinks) > 0 ? $currentPage - ($numberLinks - 1) : 1;
        $end = ($currentPage + $numberLinks) < $pages ? $currentPage + $numberLinks : $pages;
        $links = '<nav><ul class="pagination">';

        if ($currentPage > $numberLinks + 1) {
            $links .= '<li class="arrow"><a href="' . $firstUrl
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
        }
        if ($currentPage !== 1) {
            $previousOffset = $currentOffset - 15;
            $previousUrl = $previousOffset === 0 ? $firstUrl : $firstUrl . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl
                . '" data-ci-pagination-page="' . ($currentPage - 1)
                . '" rel="prev">Previous</a></li>';
        }
        for ($number = max(1, $start - 1); $number <= $end; $number++) {
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
                continue;
            }
            $pageOffset = ($number - 1) * 15;
            $url = $pageOffset === 0 ? $firstUrl : $firstUrl . $pageOffset;
            $relation = $pageOffset === 0 ? ' rel="start"' : '';
            $links .= '<li><a href="' . $url . '" data-ci-pagination-page="' . $number . '"'
                . $relation . '>' . $number . '</a></li>';
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * 15;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1)
                . '" rel="next">Next</a></li>';
        }
        if ($currentPage + $numberLinks < $pages) {
            $lastOffset = ($pages - 1) * 15;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    private function legacyBranch(): ?int
    {
        $branch = (int) service('session')->get('BranchID');

        return $branch > 0 ? $branch : null;
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
    /** @param array<string, string> $oldValues @param array<string, string> $validationErrors */
    private function renderForm(
        ?array $row,
        ?bool $editing = null,
        array $oldValues = [],
        array $validationErrors = [],
    ): string {
        $editing ??= $row !== null;
        $store = new MenuStore(db_connect());

        $groups = LegacyViewRenderer::escapedRecords(array_map(
            static fn (array $group): array => [
                'group_type_id' => $group['id'], 'group_type_name' => $group['name'],
            ],
            $store->menuGroups(),
        ));
        $variables = ['nemugroups' => $groups];
        if ($editing) {
            $variables['MenuInfo'] = $row === [] ? [] : LegacyViewRenderer::escapedRecords([$row]);
        }
        $content = (new LegacyViewRenderer(
            validationErrors: $validationErrors,
            oldValues: $oldValues,
        ))->render(
            $editing ? 'master/ecit_menus' : 'master/add_menus',
            $variables,
        );

        return $this->layout(
            $editing ? 'CodeInsect : Edit User' : 'Menu group : Add New User',
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

    /** @return array{name: string, error: string|null} */
    private function legacyNameValidation(): array
    {
        $rawName = $this->request->getPost('name');
        $name = is_string($rawName) ? trim($rawName) : '';
        $error = match (true) {
            $name === '' => 'The Full Name field is required.',
            mb_strlen($name) > 500 => 'The Full Name field cannot exceed 500 characters in length.',
            default => null,
        };

        return ['name' => $name, 'error' => $error];
    }

    private function saveLegacy(?int $id, string $name): RedirectResponse|ResponseInterface
    {
        $result = (new MenuStore(db_connect()))->legacySave(
            $id,
            $name,
            $this->request->getPost('group_type'),
        );
        if ($result === 'created') {
            service('session')->setFlashdata('success', 'New Menu created successfully');

            return $this->legacyRedirect('/addNewMenu');
        }
        if ($result === 'updated') {
            service('session')->setFlashdata('success', 'Menu updated successfully');

            return $this->legacyRedirect('/menuListing');
        }
        if ($result === 'failed') {
            service('session')->setFlashdata(
                'error',
                $id === null ? 'User creation failed' : 'Menu updation failed',
            );

            return $this->legacyRedirect($id === null ? '/addNewMenu' : '/menuListing');
        }

        return $this->response
            ->setStatusCode(match ($result) {
                'duplicate' => 409,
                'not_found' => 404,
                default => 422,
            })
            ->setJSON(['error' => match ($result) {
                'duplicate' => 'duplicate_menu_group',
                'not_found' => 'menu_group_not_found',
                default => 'invalid_menu_group',
            }]);
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

    private function legacyRedirect(string $path): RedirectResponse
    {
        $status = strtoupper($this->request->getMethod()) === 'GET' ? 307 : 303;

        return redirect()->to($path)->setStatusCode($status);
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
