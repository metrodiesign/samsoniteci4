<?php

namespace App\Users;

use App\Authentication\PasswordPolicy;
use App\Authentication\ShadowUserStore;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class UserStore
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $actorRole, ?int $actorBranch, string $search = ''): array
    {
        $query = $this->db->table('tbl_users users')
            ->select('users.userId, users.email, users.username, users.name, users.mobile, users.group_id, users.roleId, users.branch_id, users.isDeleted, roles.role AS role')
            ->join('tbl_roles roles', 'roles.roleId = users.roleId', 'left')
            ->where('users.isDeleted', 0)
            ->orderBy('users.userId', 'ASC')
            ->limit(100);
        if ($actorRole !== 1) {
            $query->where('users.branch_id', $actorBranch);
        }
        if ($search !== '') {
            $query->groupStart()->like('users.name', $search)->orLike('users.email', $search)->orLike('users.mobile', $search)->groupEnd();
        }

        return $query->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function findAccessible(int $actorRole, ?int $actorBranch, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $query = $this->db->table('tbl_users')->where('userId', $id)->where('isDeleted', 0);
        if ($actorRole !== 1) {
            $query->where('branch_id', $actorBranch);
        }

        return $query->get()->getRowArray();
    }

    public function emailExists(int $actorRole, ?int $actorBranch, string $email, ?int $excludeId = null): ?bool
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 128 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ($excludeId !== null && $excludeId < 1)
            || ($actorRole !== 1 && ($actorBranch === null || $actorBranch < 1))) {
            return null;
        }
        $query = $this->db->table('tbl_users')->where('email', $email)->where('isDeleted', 0);
        if ($actorRole !== 1) {
            $query->where('branch_id', $actorBranch);
        }
        if ($excludeId !== null) {
            $query->where('userId !=', $excludeId);
        }

        return $query->countAllResults() !== 0;
    }

    /** @param array<string, mixed> $input */
    public function save(int $actorId, int $actorRole, ?int $actorBranch, ?int $id, array $input): string
    {
        $current = $id === null ? null : $this->findAccessible($actorRole, $actorBranch, $id);
        if ($id !== null && $current === null) {
            return 'not_found';
        }
        $values = $this->normalize($actorId, $actorRole, $actorBranch, $current, $input);
        if ($values === null) {
            return 'invalid';
        }
        foreach (['email', 'username'] as $field) {
            $duplicate = $this->db->table('tbl_users')->where($field, $values[$field])->where('isDeleted', 0);
            if ($id !== null) {
                $duplicate->where('userId !=', $id);
            }
            if ($duplicate->countAllResults() !== 0) {
                return 'duplicate';
            }
        }

        $password = $values['password'];
        unset($values['password']);
        $timestamp = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if ($id === null) {
                $inserted = $this->db->table('tbl_users')->insert([
                    ...$values, 'password' => $password, 'isDeleted' => 0,
                    'createdBy' => $actorId, 'createdDtm' => $timestamp,
                ]);
                $id = (int) $this->db->insertID();
                if (! $inserted || $id < 1) {
                    throw new \RuntimeException('Unable to insert user.');
                }
            } else {
                $legacy = [...$values, 'updatedBy' => $actorId, 'updatedDtm' => $timestamp];
                if ($password !== null) {
                    $legacy['password'] = $password;
                }
                if (! $this->db->table('tbl_users')->where('userId', $id)->update($legacy)) {
                    throw new \RuntimeException('Unable to update user.');
                }
            }
            $hash = $password ?? (string) $current['password'];
            $shadow = new ShadowUserStore($this->db);
            $shadow->synchronizeLegacyUser(
                $id,
                (string) $values['email'],
                $hash,
                (int) $values['roleId'],
                $values['branch_id'] === null ? null : (int) $values['branch_id'],
                (string) $values['username'],
                (string) $values['name'],
                (int) $values['group_id'],
                match ((int) $values['roleId']) { 1 => 'Administrator', 2 => 'Operator', default => 'Viewer' },
                true,
            );
            if ($current !== null && $password !== null && ! $shadow->replacePasswordAndRevokeSessions($id, $password)) {
                throw new \RuntimeException('Unable to update shadow password.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Unable to commit user.');
            }
        } catch (Throwable) {
            $this->db->transRollback();

            return 'failed';
        }

        return $current === null ? 'created' : 'updated';
    }

    public function softDelete(int $actorId, int $actorRole, ?int $actorBranch, int $id): string
    {
        $row = $this->findAccessible($actorRole, $actorBranch, $id);
        if ($row === null) {
            return 'not_found';
        }
        if ($id === $actorId || ($actorRole !== 1 && (int) $row['roleId'] === 1)) {
            return 'forbidden';
        }
        $this->db->transBegin();
        try {
            $timestamp = date('Y-m-d H:i:s');
            if (! $this->db->table('tbl_users')->where('userId', $id)->where('isDeleted', 0)->update([
                'isDeleted' => 1, 'updatedBy' => $actorId, 'updatedDtm' => $timestamp,
            ])) {
                throw new \RuntimeException('Unable to delete legacy user.');
            }
            if (! $this->db->table('ci4_users')->where('id', $id)->where('is_active', 1)->update([
                'is_active' => 0, 'session_version' => (int) $this->db->table('ci4_users')->select('session_version')->where('id', $id)->get()->getRow('session_version') + 1,
                'updated_at' => $timestamp,
            ])) {
                throw new \RuntimeException('Unable to deactivate shadow user.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Unable to commit user delete.');
            }
        } catch (Throwable) {
            $this->db->transRollback();

            return 'failed';
        }

        return 'deleted';
    }

    public function changePassword(int $userId, mixed $currentPassword, mixed $password, mixed $confirmation): string
    {
        if (! is_string($currentPassword) || ! is_string($password) || ! is_string($confirmation)
            || ! hash_equals($password, $confirmation) || ! (new PasswordPolicy())->accepts($password)) {
            return 'invalid';
        }
        $shadow = new ShadowUserStore($this->db);
        if (! $shadow->verifyPassword($userId, $currentPassword)) {
            return 'invalid_current';
        }
        if ($shadow->verifyPassword($userId, $password)) {
            return 'unchanged';
        }
        $hash = (new PasswordPolicy())->hash($password);
        $this->db->transBegin();
        try {
            if (! $this->db->table('tbl_users')->where('userId', $userId)->where('isDeleted', 0)->update([
                'password' => $hash, 'updatedBy' => $userId, 'updatedDtm' => date('Y-m-d H:i:s'),
            ]) || ! $shadow->replacePasswordAndRevokeSessions($userId, $hash)
                || ! $this->db->transCommit()) {
                throw new \RuntimeException('Unable to change password.');
            }
        } catch (Throwable) {
            $this->db->transRollback();

            return 'failed';
        }

        return 'changed';
    }

    /** @return list<array<string, mixed>>|null */
    public function history(int $actorRole, ?int $actorBranch, int $userId, int $page): ?array
    {
        if ($page < 1 || $this->findAccessible($actorRole, $actorBranch, $userId) === null) {
            return null;
        }

        return $this->db->table('tbl_last_login')
            ->select('id, machineIp, userAgent, agentString, platform, createdDtm')
            ->where('userId', $userId)
            ->orderBy('id', 'DESC')
            ->limit(5, ($page - 1) * 5)
            ->get()
            ->getResultArray();
    }

    /** @return list<array{id: int, name: string}> */
    public function branches(int $actorRole, ?int $actorBranch): array
    {
        $query = $this->db->table('branch')->select('branch_id, branch_name')->orderBy('branch_id', 'ASC');
        if ($actorRole !== 1) {
            $query->where('branch_id', $actorBranch);
        }

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['branch_id'], 'name' => (string) $row['branch_name']],
            $query->get()->getResultArray(),
        );
    }

    /** @return list<array{id: int, code: string}>|null */
    public function books(int $actorRole, ?int $actorBranch, int $branchId): ?array
    {
        if ($branchId < 1 || ($actorRole !== 1 && $actorBranch !== $branchId)
            || $this->db->table('branch')->where('branch_id', $branchId)->countAllResults() !== 1) {
            return null;
        }

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['book_id'], 'code' => (string) $row['book_detail']],
            $this->db->table('book')->select('book_id, book_detail')
                ->where('branch_id', $branchId)->where('status', 1)->orderBy('book_id', 'ASC')->get()->getResultArray(),
        );
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed>      $input
     * @return array<string, int|string|null>|null
     */
    private function normalize(int $actorId, int $actorRole, ?int $actorBranch, ?array $current, array $input): ?array
    {
        foreach (['username', 'name', 'email', 'mobile'] as $field) {
            if (! is_string($input[$field] ?? null)) {
                return null;
            }
            $input[$field] = trim($input[$field]);
        }
        $email = strtolower($input['email']);
        $role = $this->positiveInteger($input['role_id'] ?? null);
        $group = $this->positiveInteger($input['group_id'] ?? null);
        $branch = ($input['branch_id'] ?? '') === '' ? null : $this->positiveInteger($input['branch_id']);
        if ($input['username'] === '' || strlen($input['username']) > 50
            || preg_match('/\A[a-zA-Z0-9._-]+\z/D', $input['username']) !== 1
            || $input['name'] === '' || mb_strlen($input['name']) > 128
            || strlen($email) > 128 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/\A[0-9]{10,20}\z/D', $input['mobile']) !== 1
            || ! in_array($role, [1, 2, 3], true) || $group === null
            || ($role === 1 && $branch !== null) || ($role !== 1 && $branch === null)) {
            return null;
        }
        if ($actorRole !== 1 && ($actorBranch === null || $branch !== $actorBranch || $role === 1)) {
            return null;
        }
        if ($current !== null && (int) $current['userId'] === $actorId && (int) $current['roleId'] !== $role) {
            return null;
        }
        $branchType = null;
        if ($branch !== null) {
            $branchType = $this->db->table('branch')->select('branch_type')->where('branch_id', $branch)->get()->getRow('branch_type');
            if ($branchType === null) {
                return null;
            }
        }

        $password = $input['password'] ?? '';
        $confirmation = $input['password_confirmation'] ?? '';
        if (! is_string($password) || ! is_string($confirmation)) {
            return null;
        }
        if ($current === null || $password !== '' || $confirmation !== '') {
            if (! hash_equals($password, $confirmation) || ! (new PasswordPolicy())->accepts($password)) {
                return null;
            }
            $password = (new PasswordPolicy())->hash($password);
        } else {
            $password = null;
        }

        return [
            'email' => $email, 'username' => $input['username'], 'password' => $password,
            'name' => $input['name'], 'mobile' => $input['mobile'], 'group_id' => $group,
            'roleId' => $role, 'branch_id' => $branch, 'branch_type_id' => $branchType === null ? null : (int) $branchType,
        ];
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }
}
