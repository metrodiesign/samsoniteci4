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

    public function add(): string
    {
        return $this->renderForm(null);
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

        $content = (new LegacyViewRenderer())->render('loginHistory', [
            'userRecords' => LegacyViewRenderer::escapedRecords($rows),
            'userInfo' => new \App\Presentation\LegacyRecord([
                'name' => esc((string) ($owner['name'] ?? '')),
                'email' => esc((string) ($owner['email'] ?? '')),
            ]),
            'searchText' => esc($search),
        ]);

        return $this->layout('Tracking : User Login History', $content, ['contentOwnsWrapper' => true]);
    }

    public function ownHistory(): string
    {
        return $this->history((string) $this->actorId());
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

    private function renderList(string $search): string
    {
        $content = (new LegacyViewRenderer())->render('users', [
            'userRecords' => LegacyViewRenderer::escapedRecords($this->store()->all($this->role(), $this->branch(), $search)),
            'searchText' => esc($search),
            'page' => 0,
        ]);

        return $this->layout('Tracking : User Listing', $content, ['contentOwnsWrapper' => true]);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row): string
    {
        $db = db_connect();
        $variables = [
            'roles' => LegacyViewRenderer::escapedRecords($db->table('tbl_roles')->select('roleId, role')->orderBy('roleId')->get()->getResultArray()),
            'usergroups' => LegacyViewRenderer::escapedRecords($db->table('group_menu')->select('id, name')->orderBy('id')->get()->getResultArray()),
            'branchtypes' => LegacyViewRenderer::escapedRecords($db->table('branch_type')->select('branch_type_id, branch_type_details')->orderBy('branch_type_id')->get()->getResultArray()),
            'BranchID' => $this->branch(),
        ];
        if ($row !== null) {
            $variables['userInfo'] = LegacyViewRenderer::escapedRecords([$row]);
            $variables['branchs'] = LegacyViewRenderer::escapedRecords($db->table('branch')->select('branch_id, branch_name')->orderBy('branch_id')->get()->getResultArray());
        }
        $content = (new LegacyViewRenderer())->render($row === null ? 'addNew' : 'editOld', $variables);

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
