<?php

namespace App\Controllers;

use App\Users\UserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class Users extends BaseController
{
    public function listing(): string
    {
        $rawSearch = $this->request->getGet('search');
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
        return $this->layout('Change Password', view('change_password', [
            'changed' => $this->request->getGet('changed') === '1',
            'caption' => 'Enter Details',
        ]), ['subtitle' => 'Set new password for your account']);
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
        $rawSearch = $this->request->getGet('searchText');
        $search = is_string($rawSearch) && mb_strlen($rawSearch) <= 128 ? trim($rawSearch) : '';
        $rows = $id === null || $page === null
            ? null
            : $this->store()->history($this->role(), $this->branch(), $id, $page, $search);
        if ($rows === null || $id === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $owner = $this->store()->findAccessible($this->role(), $this->branch(), $id) ?? [];

        return $this->layout('Login History', view('login_history', [
            'rows' => $rows,
            'userId' => $id,
            'page' => $page,
            'search' => $search,
            'pages' => (int) ceil($this->store()->historyCount($this->role(), $this->branch(), $id, $search) / 5),
            'ownerName' => (string) ($owner['name'] ?? ''),
            'ownerEmail' => (string) ($owner['email'] ?? ''),
        ]), ['subtitle' => 'Add, Edit, Delete']);
    }

    public function ownHistory(): string
    {
        return $this->history((string) $this->actorId());
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
        return $this->layout('User Management', view('users_list', ['caption' => 'Users List'] + [
            'rows' => $this->store()->all($this->role(), $this->branch(), $search),
            'search' => $search,
        ]), ['actions' => $this->actionLink('/users/new', 'Add New'), 'subtitle' => 'Add, Edit, Delete']);
    }

    /** @param array<string, mixed>|null $row */
    private function renderForm(?array $row): string
    {
        return $this->layout('User Management', view('users_form', ['caption' => 'Enter User Details'] + [
            'row' => $row,
            'actorRole' => $this->role(),
            'actorBranch' => $this->branch(),
        ]), ['subtitle' => 'Add / Edit User']);
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
