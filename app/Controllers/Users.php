<?php

namespace App\Controllers;

use App\Presentation\LegacyViewRenderer;
use App\Users\UserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class Users extends BaseController
{
    public function listing(): string
    {
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : $this->request->getGet('search');
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';

        return $this->renderList($search);
    }

    public function legacyListing(string $rawOffset = '0'): string
    {
        if (preg_match('/\A[0-9]+\z/D', $rawOffset) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $offset = filter_var($rawOffset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset === false) {
            throw PageNotFoundException::forPageNotFound();
        }
        $rawSearch = strtoupper($this->request->getMethod()) === 'POST'
            ? $this->request->getPost('searchText')
            : '';
        $displaySearch = is_string($rawSearch) && mb_strlen($rawSearch) <= 1000 ? $rawSearch : '';
        $filterSearch = $displaySearch === '0' ? '' : $displaySearch;

        return $this->renderLegacyList($displaySearch, $filterSearch, (int) $offset);
    }

    public function add(): string
    {
        return $this->renderForm(null);
    }

    public function legacyAdd(): string
    {
        return $this->renderForm(null);
    }

    public function legacyEdit(?string $rawId = null): string|RedirectResponse
    {
        if ($rawId === null) {
            return $this->legacyRedirect('/userListing');
        }
        $id = $this->positiveInteger($rawId);
        $row = $id === null ? null : $this->store()->findAccessible($this->role(), $this->branch(), $id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($row);
    }

    public function legacyCreate(): string|RedirectResponse
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->renderForm(null);
        }
        $input = $this->request->getPost();
        $input = is_array($input) ? $input : [];
        $errors = $this->legacyUserValidation($input, true);
        if ($errors !== []) {
            return $this->renderForm(null, $this->legacyOldValues($input), $errors);
        }
        $mapped = $this->legacyUserInput($input, null);
        $result = $mapped === null
            ? 'invalid'
            : $this->store()->save($this->actorId(), $this->role(), $this->branch(), null, $mapped, true);
        service('session')->setFlashdata(
            $result === 'created' ? 'success' : 'error',
            $result === 'created' ? 'New User created successfully' : 'User creation failed',
        );

        return $this->legacyRedirect('/addNew');
    }

    public function legacyUpdate(): string|RedirectResponse
    {
        $input = $this->request->getPost();
        $input = is_array($input) ? $input : [];
        $rawId = is_scalar($input['userId'] ?? null) ? (string) $input['userId'] : '';
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            return $this->legacyRedirect('/userListing');
        }
        $current = $this->store()->findAccessible($this->role(), $this->branch(), $id);
        if ($current === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $errors = $this->legacyUserValidation($input, false);
        if ($errors !== []) {
            return $this->renderForm($current, [], $errors);
        }
        $mapped = $this->legacyUserInput($input, $current);
        $result = $mapped === null
            ? 'invalid'
            : $this->store()->save($this->actorId(), $this->role(), $this->branch(), $id, $mapped, true);
        service('session')->setFlashdata(
            $result === 'updated' ? 'success' : 'error',
            $result === 'updated' ? 'User updated successfully' : 'User updation failed',
        );

        return $this->legacyRedirect('/userListing');
    }

    public function legacyDelete(): ResponseInterface
    {
        $rawId = $this->request->getPost('userId');
        $id = is_scalar($rawId) ? $this->positiveInteger((string) $rawId) : null;
        $result = $id === null
            ? 'not_found'
            : $this->store()->softDelete($this->actorId(), $this->role(), $this->branch(), $id);
        $security = service('security');

        return \Config\Services::response(null, false)
            ->setBody(json_encode(['status' => $result === 'deleted'], JSON_THROW_ON_ERROR))
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->setHeader($security->getHeaderName(), $security->getHash());
    }

    public function legacyEmailExists(): ResponseInterface
    {
        $rawEmail = $this->request->getPost('email');
        $rawId = $this->request->getPost('userId');
        $excludeId = is_scalar($rawId) && (string) $rawId !== ''
            ? $this->positiveInteger((string) $rawId)
            : null;
        $exists = is_string($rawEmail)
            ? $this->store()->emailExists($this->role(), $this->branch(), $rawEmail, $excludeId)
            : null;
        $security = service('security');

        return $this->response->setContentType('text/html')
            ->setBody($exists === true ? 'false' : 'true')
            ->setHeader($security->getHeaderName(), $security->getHash());
    }

    public function edit(string $rawId): string
    {
        $id = $this->positiveInteger($rawId);
        $row = $id === null ? null : $this->store()->findAccessible($this->role(), $this->branch(), $id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($row);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        return $this->save(null);
    }

    public function update(string $rawId): RedirectResponse|ResponseInterface
    {
        $id = $this->positiveInteger($rawId);
        if ($id === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(string $rawId): ResponseInterface
    {
        $id = $this->positiveInteger($rawId);
        $result = $id === null ? 'not_found' : $this->store()->softDelete($this->actorId(), $this->role(), $this->branch(), $id);

        $response = match ($result) {
            'deleted' => $this->response->setStatusCode(204),
            'forbidden' => $this->response->setStatusCode(409)->setJSON(['error' => 'user_delete_forbidden']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'user_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'user_unavailable']),
        };
        $security = service('security');

        return $response->setHeader($security->getHeaderName(), $security->getHash());
    }

    public function emailExists(): ResponseInterface
    {
        $rawEmail = $this->request->getGet('email');
        $rawExclude = $this->request->getGet('exclude_id');
        $exclude = is_string($rawExclude) ? $this->positiveInteger($rawExclude) : null;
        $exists = is_string($rawEmail) && ($rawExclude === null || $exclude !== null)
            ? $this->store()->emailExists($this->role(), $this->branch(), $rawEmail, $exclude)
            : null;

        return $exists === null
            ? $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_email'])
            : $this->response->setJSON(['exists' => $exists]);
    }

    public function passwordForm(): string
    {
        if ($this->request->getGet('changed') === '1') {
            service('session')->setFlashdata('success', 'Password changed successfully');
        }
        $content = (new LegacyViewRenderer())->render('changePassword');

        return $this->layout('CodeInsect : Change Password', $content, [
            'contentOwnsWrapper' => true,
            'subtitle' => 'Set new password for your account',
        ]);
    }

    public function legacyPasswordForm(): string
    {
        return $this->passwordForm();
    }

    public function changePassword(): RedirectResponse|ResponseInterface
    {
        return $this->changePasswordFrom(
            $this->request->getPost('current_password'),
            $this->request->getPost('password'),
            $this->request->getPost('password_confirmation'),
            '/change-password?changed=1',
        );
    }

    public function changePasswordLegacy(): RedirectResponse|ResponseInterface
    {
        return $this->changePasswordFrom(
            $this->request->getPost('oldPassword'),
            $this->request->getPost('newPassword'),
            $this->request->getPost('cNewPassword'),
            '/loadChangePass?changed=1',
        );
    }

    private function changePasswordFrom(
        mixed $currentPassword,
        mixed $password,
        mixed $confirmation,
        string $successPath,
    ): RedirectResponse|ResponseInterface {
        $result = $this->store()->changePassword($this->actorId(), $currentPassword, $password, $confirmation);
        if ($result === 'changed') {
            $version = (new \App\Authentication\ShadowUserStore(db_connect()))->currentSessionVersion($this->actorId());
            if ($version === null) {
                return $this->response->setStatusCode(503)->setJSON(['error' => 'password_unavailable']);
            }
            service('session')->set('sessionVersion', $version);

            return redirect()->to($successPath);
        }

        return match ($result) {
            'invalid', 'invalid_current' => $this->response->setStatusCode(422)->setJSON(['error' => $result]),
            'unchanged' => $this->response->setStatusCode(409)->setJSON(['error' => 'password_unchanged']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'password_unavailable']),
        };
    }

    public function history(string $rawId, string $rawPage = '1'): string
    {
        $id = $this->positiveInteger($rawId);
        $page = $this->positiveInteger($rawPage);
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : $this->request->getGet('searchText');
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $rows = $id === null || $page === null
            ? null
            : $this->store()->history($this->role(), $this->branch(), $id, $page, $search);
        if ($rows === null || $id === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $owner = $this->store()->findAccessible($this->role(), $this->branch(), $id) ?? [];
        $total = $this->store()->historyCount($this->role(), $this->branch(), $id, $search);
        $pagination = $this->legacyHistoryPagination($id, $page, $total);

        $content = (new LegacyViewRenderer(null, $pagination))->render('loginHistory', [
            'userRecords' => LegacyViewRenderer::escapedRecords($rows),
            'userInfo' => new \App\Presentation\LegacyRecord([
                'name' => esc((string) ($owner['name'] ?? '')),
                'email' => esc((string) ($owner['email'] ?? '')),
            ]),
            'searchText' => esc($search),
        ]);

        return $this->layout('Tracking : User Login History', $content, ['contentOwnsWrapper' => true]);
    }

    public function legacyLoginHistory(?string $rawId = null, string $rawOffset = '0'): string
    {
        $id = $rawId === null ? $this->actorId() : $this->positiveInteger($rawId);
        if ($id === null || preg_match('/\A[0-9]+\z/D', $rawOffset) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $offset = filter_var($rawOffset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset === false) {
            throw PageNotFoundException::forPageNotFound();
        }
        $owner = $this->store()->findAccessible($this->role(), $this->branch(), $id);
        if ($owner === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $rawSearch = strtoupper($this->request->getMethod()) === 'POST'
            ? $this->request->getPost('searchText')
            : '';
        $displaySearch = is_string($rawSearch) && mb_strlen($rawSearch) <= 1000 ? $rawSearch : '';
        $store = $this->store();
        $rows = $store->legacyHistory($this->role(), $this->branch(), $id, (int) $offset);
        if ($rows === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $total = $store->legacyHistoryCount($this->role(), $this->branch(), $id);
        $content = (new LegacyViewRenderer(
            null,
            $this->legacyOffsetHistoryPagination($id, (int) $offset, $total),
        ))->render('loginHistory', [
            'userRecords' => LegacyViewRenderer::escapedRecords($rows),
            'userInfo' => new \App\Presentation\LegacyRecord([
                'name' => esc((string) ($owner['name'] ?? '')),
                'email' => esc((string) ($owner['email'] ?? '')),
            ]),
            'searchText' => esc($displaySearch),
        ]);

        return $this->layout('Tracking : User Login History', $content, ['contentOwnsWrapper' => true]);
    }

    public function legacyControllerLoginHistory(?string $rawId = null, ?string $ignoredOffset = null): string
    {
        unset($ignoredOffset);

        return $rawId === null
            ? $this->legacyLoginHistory()
            : $this->legacyLoginHistory($rawId, $rawId);
    }

    public function ownHistory(): string
    {
        return $this->history((string) $this->actorId());
    }

    public function legacyHistory(string $rawId, string $rawOffset): string
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $rawOffset) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->history($rawId, (string) (intdiv((int) $rawOffset, 5) + 1));
    }

    public function legacyBranches(string $rawType): ResponseInterface
    {
        $type = preg_match('/\A[0-9]+\z/D', $rawType) === 1 ? (int) $rawType : -1;
        $rows = $type < 0 ? [] : db_connect()->table('branch')
            ->select('branch_id, branch_name, branch_user_name')->where('branch_type', $type)
            ->orderBy('branch_id', 'ASC')->get()->getResultArray();
        $html = '<select class="form-control required" id="branch_id" name="branch_id" onchange="JavaScript:list_recommend_do_ajax_branchshort(document.getElementById(\'branch_id\').value)">';
        $html .= '<option value="0" selected>Select Branch</option>';
        foreach ($rows as $row) {
            $name = (string) $row['branch_name'];
            if ((string) $row['branch_user_name'] !== '') {
                $name .= ' (' . $row['branch_user_name'] . ')';
            }
            $html .= '<option value="' . esc((string) $row['branch_id'], 'attr') . '">' . esc($name) . '</option>';
        }

        return $this->response->setContentType('text/html')->setBody($html . '</select>');
    }

    public function legacyBooks(string $rawBranch): ResponseInterface
    {
        $branch = preg_match('/\A[0-9]+\z/D', $rawBranch) === 1 ? (int) $rawBranch : -1;
        $rows = $branch < 0 ? [] : db_connect()->table('book')->select('book_id, book_detail')
            ->where('branch_id', $branch)->orderBy('book_id', 'ASC')->get()->getResultArray();
        $html = '<select class="form-control required" id="bookID" name="bookID"><option value="0" selected>Select Book</option>';
        foreach ($rows as $row) {
            $html .= '<option value="' . esc((string) $row['book_id'], 'attr') . '">' . esc((string) $row['book_detail']) . '</option>';
        }

        return $this->response->setContentType('text/html')->setBody($html . '</select>');
    }

    public function legacyBranchShort(string $rawBranch): ResponseInterface
    {
        $branch = preg_match('/\A[0-9]+\z/D', $rawBranch) === 1 ? (int) $rawBranch : -1;
        $value = $branch < 0 ? null : db_connect()->table('branch')->select('default_suffix')
            ->where('branch_id', $branch)->get()->getRow('default_suffix');
        $value = is_scalar($value) ? (string) $value : '0';
        $html = '<input type="text" class="form-control" id="branchshort"  name="branchshort" maxlength="3"  value="' . esc($value, 'attr') . '" readonly >';

        return $this->response->setContentType('text/html')->setBody($html);
    }

    public function legacyBookShort(string $rawBook): ResponseInterface
    {
        $book = preg_match('/\A[0-9]+\z/D', $rawBook) === 1 ? (int) $rawBook : -1;
        $value = $book < 0 ? null : db_connect()->table('book')->select('book_detail')
            ->where('book_id', $book)->get()->getRow('book_detail');
        $value = is_scalar($value) ? (string) $value : '0';
        $html = '<input type="text" class="form-control" id="bookshort" value="' . esc($value, 'attr') . '"  name="bookshort" maxlength="3">';

        return $this->response->setContentType('text/html')->setBody($html);
    }

    public function branches(): ResponseInterface
    {
        return $this->response->setJSON(['branches' => $this->store()->branches($this->role(), $this->branch())]);
    }

    public function books(): ResponseInterface
    {
        $raw = $this->request->getGet('branch_id');
        $branchId = is_string($raw) ? $this->positiveInteger($raw) : null;
        $rows = $branchId === null ? null : $this->store()->books($this->role(), $this->branch(), $branchId);

        return $rows === null
            ? $this->response->setStatusCode(404)->setJSON(['error' => 'branch_not_found'])
            : $this->response->setJSON(['books' => $rows]);
    }

    private function legacyOffsetHistoryPagination(int $userId, int $offset, int $total): string
    {
        $pages = (int) ceil($total / 5);
        if ($pages <= 1) {
            return '';
        }
        $base = base_url('login-history/' . $userId . '/');
        $currentOffset = $offset > $total ? ($pages - 1) * 5 : $offset;
        $currentPage = intdiv($currentOffset, 5) + 1;
        $numberLinks = 5;
        $start = ($currentPage - $numberLinks) > 0 ? $currentPage - ($numberLinks - 1) : 1;
        $end = ($currentPage + $numberLinks) < $pages ? $currentPage + $numberLinks : $pages;
        $links = '<nav><ul class="pagination">';
        if ($currentPage > $numberLinks + 1) {
            $links .= '<li class="arrow"><a href="' . $base
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
        }
        if ($currentPage !== 1) {
            $previousOffset = $currentOffset - 5;
            $previousUrl = $previousOffset === 0 ? $base : $base . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl
                . '" data-ci-pagination-page="' . ($currentPage - 1)
                . '" rel="prev">Previous</a></li>';
        }
        for ($number = max(1, $start - 1); $number <= $end; $number++) {
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
                continue;
            }
            $pageOffset = ($number - 1) * 5;
            $url = $pageOffset === 0 ? $base : $base . $pageOffset;
            $relation = $pageOffset === 0 ? ' rel="start"' : '';
            $links .= '<li><a href="' . $url . '" data-ci-pagination-page="' . $number . '"'
                . $relation . '>' . $number . '</a></li>';
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * 5;
            $links .= '<li class="arrow"><a href="' . $base . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1)
                . '" rel="next">Next</a></li>';
        }
        if ($currentPage + $numberLinks < $pages) {
            $lastOffset = ($pages - 1) * 5;
            $links .= '<li class="arrow"><a href="' . $base . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    private function legacyHistoryPagination(int $userId, int $page, int $total): string
    {
        $pages = (int) ceil($total / 5);
        if ($pages <= 1) {
            return '';
        }
        $base = base_url('login-history/' . $userId . '/');
        $links = '<nav><ul class="pagination">';
        if ($page > 1) {
            $links .= '<li class="arrow"><a href="' . $base . (($page - 2) * 5) . '">Previous</a></li>';
        }
        for ($number = 1; $number <= $pages; $number++) {
            if ($number === $page) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
            } else {
                $links .= '<li><a href="' . $base . (($number - 1) * 5) . '">' . $number . '</a></li>';
            }
        }
        if ($page < $pages) {
            $links .= '<li class="arrow"><a href="' . $base . ($page * 5) . '">Next</a></li>';
        }

        return $links . '</ul></nav>';
    }

    private function renderList(string $search): string
    {
        $content = (new LegacyViewRenderer())->render('users', [
            'userRecords' => LegacyViewRenderer::escapedRecords($this->store()->all($this->role(), $this->branch(), $search)),
            'searchText' => esc($search),
            'page' => 0,
        ]);

        return $this->layout('Tracking : User Listing', $content, ['contentOwnsWrapper' => true]);
    }

    private function renderLegacyList(string $displaySearch, string $filterSearch, int $offset): string
    {
        $store = $this->store();
        $total = $store->legacyCount($this->branch(), $filterSearch);
        $content = (new LegacyViewRenderer(null, $this->legacyListingPagination($offset, $total)))->render('users', [
            'userRecords' => LegacyViewRenderer::escapedRecords(
                $store->legacyAll($this->branch(), $filterSearch, $offset),
            ),
            'searchText' => esc($displaySearch),
            'page' => $offset,
        ]);

        return $this->layout('Tracking : User Listing', $content, ['contentOwnsWrapper' => true]);
    }

    private function legacyListingPagination(int $offset, int $total): string
    {
        $pages = (int) ceil($total / 50);
        if ($pages <= 1) {
            return '';
        }
        $base = base_url('userListing/');
        $currentOffset = $offset > $total ? ($pages - 1) * 50 : $offset;
        $currentPage = intdiv($currentOffset, 50) + 1;
        $numberLinks = 5;
        $start = ($currentPage - $numberLinks) > 0 ? $currentPage - ($numberLinks - 1) : 1;
        $end = ($currentPage + $numberLinks) < $pages ? $currentPage + $numberLinks : $pages;
        $links = '<nav><ul class="pagination">';
        if ($currentPage > $numberLinks + 1) {
            $links .= '<li class="arrow"><a href="' . $base
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
        }
        if ($currentPage !== 1) {
            $previousOffset = $currentOffset - 50;
            $previousUrl = $previousOffset === 0 ? $base : $base . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl
                . '" data-ci-pagination-page="' . ($currentPage - 1)
                . '" rel="prev">Previous</a></li>';
        }
        for ($number = max(1, $start - 1); $number <= $end; $number++) {
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
                continue;
            }
            $pageOffset = ($number - 1) * 50;
            $url = $pageOffset === 0 ? $base : $base . $pageOffset;
            $relation = $pageOffset === 0 ? ' rel="start"' : '';
            $links .= '<li><a href="' . $url . '" data-ci-pagination-page="' . $number . '"'
                . $relation . '>' . $number . '</a></li>';
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * 50;
            $links .= '<li class="arrow"><a href="' . $base . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1)
                . '" rel="next">Next</a></li>';
        }
        if ($currentPage + $numberLinks < $pages) {
            $lastOffset = ($pages - 1) * 50;
            $links .= '<li class="arrow"><a href="' . $base . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    /** @param array<string, mixed>|null $row @param array<string, string> $oldValues @param array<string, string> $validationErrors */
    private function renderForm(
        ?array $row,
        array $oldValues = [],
        array $validationErrors = [],
    ): string
    {
        $db = db_connect();
        $roles = $db->table('tbl_roles')->select('roleId, role')->orderBy('roleId');
        if ($this->branch() !== null && $this->branch() > 0) {
            $roles->where('roleId >', 1);
        }
        $variables = [
            'roles' => LegacyViewRenderer::escapedRecords($roles->get()->getResultArray()),
            'usergroups' => LegacyViewRenderer::escapedRecords($db->table('group_menu')->select('id, name')->orderBy('id')->get()->getResultArray()),
            'branchtypes' => LegacyViewRenderer::escapedRecords($db->table('branch_type')->select('branch_type_id, branch_type_details')->orderBy('branch_type_id')->get()->getResultArray()),
            'BranchID' => $this->branch(),
        ];
        if ($row !== null) {
            $variables['userInfo'] = LegacyViewRenderer::escapedRecords([$row]);
            $variables['branchs'] = LegacyViewRenderer::escapedRecords($db->table('branch')->select('branch_id, branch_name')->orderBy('branch_id')->get()->getResultArray());
        }
        $content = (new LegacyViewRenderer(
            validationErrors: $validationErrors,
            oldValues: $oldValues,
        ))->render($row === null ? 'addNew' : 'editOld', $variables);

        return $this->layout(
            $row === null ? 'Tracking : Add New User' : 'CodeInsect : Edit User',
            $content,
            ['contentOwnsWrapper' => true],
        );
    }

    private function save(?int $id): RedirectResponse|ResponseInterface
    {
        $result = $this->store()->save($this->actorId(), $this->role(), $this->branch(), $id, $this->request->getPost());

        return match ($result) {
            'created', 'updated' => redirect()->to('/users'),
            'invalid' => $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_user']),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'duplicate_user']),
            'not_found' => $this->response->setStatusCode(404)->setJSON(['error' => 'user_not_found']),
            default => $this->response->setStatusCode(503)->setJSON(['error' => 'user_unavailable']),
        };
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function legacyUserValidation(array $input, bool $creating): array
    {
        $value = static fn (string $field): string => is_scalar($input[$field] ?? null)
            ? (string) $input[$field]
            : '';
        $errors = [];
        $name = trim($value('fname'));
        $email = trim($value('email'));
        $password = $value('password');
        $confirmation = trim($value('cpassword'));
        $role = trim($value('role'));
        $mobile = $value('mobile');
        if ($name === '') {
            $errors['fname'] = 'The Full Name field is required.';
        } elseif (mb_strlen($name) > 128) {
            $errors['fname'] = 'The Full Name field cannot exceed 128 characters in length.';
        }
        if ($email === '') {
            $errors['email'] = 'The Email field is required.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'The Email field must contain a valid email address.';
        } elseif (mb_strlen($email) > 128) {
            $errors['email'] = 'The Email field cannot exceed 128 characters in length.';
        }
        if ($creating && $password === '') {
            $errors['password'] = 'The Password field is required.';
        } elseif (mb_strlen($password) > 20) {
            $errors['password'] = 'The Password field cannot exceed 20 characters in length.';
        } elseif (! $creating && $password !== $confirmation) {
            $errors['password'] = 'The Password field does not match the Confirm Password field.';
        }
        if ($creating && $confirmation === '') {
            $errors['cpassword'] = 'The Confirm Password field is required.';
        } elseif (mb_strlen($confirmation) > 20) {
            $errors['cpassword'] = 'The Confirm Password field cannot exceed 20 characters in length.';
        } elseif ($confirmation !== $password) {
            $errors['cpassword'] = 'The Confirm Password field does not match the Password field.';
        }
        if ($role === '') {
            $errors['role'] = 'The Role field is required.';
        } elseif (! is_numeric($role)) {
            $errors['role'] = 'The Role field must contain only numbers.';
        }
        if ($mobile === '') {
            $errors['mobile'] = 'The Mobile Number field is required.';
        } elseif (mb_strlen($mobile) < 10) {
            $errors['mobile'] = 'The Mobile Number field must be at least 10 characters in length.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function legacyOldValues(array $input): array
    {
        $old = [];
        foreach (['fname', 'email', 'mobile', 'role', 'group_id', 'branch_type', 'branch_id'] as $field) {
            if (is_scalar($input[$field] ?? null)) {
                $old[$field] = (string) $input[$field];
            }
        }

        return $old;
    }

    /** @param array<string, mixed> $input @param array<string, mixed>|null $current @return array<string, mixed>|null */
    private function legacyUserInput(array $input, ?array $current): ?array
    {
        $string = static fn (string $field): ?string => is_string($input[$field] ?? null)
            ? $input[$field]
            : null;
        $name = $string('fname');
        $email = $string('email');
        $mobile = $string('mobile');
        $role = $string('role');
        $password = $string('password') ?? '';
        $confirmation = $string('cpassword') ?? '';
        if ($name === null || $email === null || $mobile === null || $role === null) {
            return null;
        }
        $role = trim($role);
        $sessionBranch = $this->branch();
        $branchId = $sessionBranch !== null && $sessionBranch > 0
            ? $sessionBranch
            : $this->positiveInteger(trim($string('branch_id') ?? ''));
        $group = $sessionBranch !== null && $sessionBranch > 0
            ? 4
            : $this->positiveInteger(trim($string('group_id') ?? ''));
        if ($branchId === null || $group === null) {
            return null;
        }
        $branch = db_connect()->table('branch')
            ->select('branch_type, branch_user_name')
            ->where('branch_id', $branchId)
            ->get()
            ->getRowArray();
        if ($branch === null || ! is_numeric($branch['branch_type'] ?? null)) {
            return null;
        }
        $username = is_string($branch['branch_user_name'] ?? null)
            ? trim($branch['branch_user_name'])
            : '';
        if ($username === '' && is_string($current['username'] ?? null)) {
            $username = (string) $current['username'];
        }

        return [
            'username' => $username,
            'name' => ucwords(strtolower($name)),
            'email' => $email,
            'mobile' => $mobile,
            'group_id' => (string) $group,
            'role_id' => $role,
            'branch_id' => (string) $branchId,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ];
    }

    private function legacyRedirect(string $path): RedirectResponse
    {
        $status = strtoupper($this->request->getMethod()) === 'GET' ? 307 : 303;

        return redirect()->to($path)->setStatusCode($status);
    }

    private function store(): UserStore
    {
        return new UserStore(db_connect());
    }

    private function actorId(): int
    {
        return (int) service('session')->get('userId');
    }

    private function role(): int
    {
        return (int) service('session')->get('role');
    }

    private function branch(): ?int
    {
        $value = service('session')->get('BranchID');

        return $value === null ? null : (int) $value;
    }

    private function positiveInteger(string $value): ?int
    {
        return preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
