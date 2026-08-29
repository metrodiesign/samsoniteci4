<?php

namespace App\Authentication;

use App\Authorization\AuthorizationPolicy;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

final class ShadowUserStore
{
    private const TABLE = 'ci4_users';

    public function __construct(private BaseConnection $db)
    {
    }

    public function create(string $email, string $passwordHash, int $role, ?int $branchId): int
    {
        $email = $this->normalizedEmail($email);
        $this->assertPasswordHash($passwordHash);

        if (
            $email === null
            || ! (new AuthorizationPolicy())->isKnownRole($role)
            || ($branchId !== null && $branchId < 1)
            || ($role !== 1 && $branchId === null)
        ) {
            throw new InvalidArgumentException('Invalid shadow user.');
        }

        $timestamp = date('Y-m-d H:i:s');
        $inserted  = $this->db->table(self::TABLE)->insert([
            'email'           => $email,
            'password_hash'   => $passwordHash,
            'role_id'         => $role,
            'branch_id'       => $branchId,
            'is_active'       => 1,
            'session_version' => 1,
            'created_at'      => $timestamp,
            'updated_at'      => $timestamp,
        ]);

        $userId = (int) $this->db->insertID();

        if (! $inserted || $userId < 1) {
            throw new RuntimeException('Unable to create shadow user.');
        }

        return $userId;
    }

    public function synchronizeLegacyUser(
        int $userId,
        string $email,
        string $passwordHash,
        int $role,
        ?int $branchId,
        string $username,
        string $displayName,
        int $groupId,
        string $roleText,
        bool $isActive,
    ): int {
        $email = $this->normalizedEmail($email);
        $username = trim($username);
        $displayName = trim($displayName);
        $roleText = trim($roleText);
        $this->assertPasswordHash($passwordHash);

        if (
            $userId < 1
            || $email === null
            || $username === ''
            || strlen($username) > 50
            || strlen($displayName) > 128
            || $groupId < 1
            || $roleText === ''
            || strlen($roleText) > 64
            || ! (new AuthorizationPolicy())->isKnownRole($role)
            || ($branchId !== null && $branchId < 1)
            || ($role !== 1 && $branchId === null)
        ) {
            throw new InvalidArgumentException('Invalid legacy shadow user.');
        }

        $row = $this->db->table(self::TABLE)
            ->select('role_id, branch_id, is_active, session_version')
            ->where('id', $userId)
            ->get()
            ->getRowArray();
        $timestamp = date('Y-m-d H:i:s');

        if ($row === null) {
            $inserted = $this->db->table(self::TABLE)->insert([
                'id'              => $userId,
                'email'           => $email,
                'username'        => $username,
                'display_name'    => $displayName,
                'password_hash'   => $passwordHash,
                'role_id'         => $role,
                'branch_id'       => $branchId,
                'group_id'        => $groupId,
                'role_text'       => $roleText,
                'is_active'       => $isActive ? 1 : 0,
                'session_version' => 1,
                'created_at'      => $timestamp,
                'updated_at'      => $timestamp,
            ]);

            if (! $inserted) {
                throw new RuntimeException('Unable to synchronize legacy shadow user.');
            }

            return 1;
        }

        $sessionVersion = max(1, (int) $row['session_version']);
        if (
            (int) $row['role_id'] !== $role
            || ($row['branch_id'] === null ? null : (int) $row['branch_id']) !== $branchId
            || (int) $row['is_active'] !== ($isActive ? 1 : 0)
        ) {
            $sessionVersion++;
        }

        $updated = $this->db->table(self::TABLE)
            ->where('id', $userId)
            ->update([
                'email'           => $email,
                'username'        => $username,
                'display_name'    => $displayName,
                'role_id'         => $role,
                'branch_id'       => $branchId,
                'group_id'        => $groupId,
                'role_text'       => $roleText,
                'is_active'       => $isActive ? 1 : 0,
                'session_version' => $sessionVersion,
                'updated_at'      => $timestamp,
            ]);

        if (! $updated) {
            throw new RuntimeException('Unable to synchronize legacy shadow user.');
        }

        return $sessionVersion;
    }

    public function findActiveIdByEmail(string $email): ?int
    {
        $email = $this->normalizedEmail($email);

        if ($email === null) {
            return null;
        }

        $row = $this->db->table(self::TABLE)
            ->select('id')
            ->where('email', $email)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        return isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : null;
    }

    public function findActiveEmailById(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }

        $row = $this->db->table(self::TABLE)
            ->select('email')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        return isset($row['email']) && is_string($row['email']) ? $row['email'] : null;
    }

    public function verifyPassword(int $userId, string $password): bool
    {
        if ($userId < 1) {
            return false;
        }

        $row = $this->db->table(self::TABLE)
            ->select('password_hash')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        return isset($row['password_hash'])
            && is_string($row['password_hash'])
            && password_verify($password, $row['password_hash']);
    }

    public function currentSessionVersion(int $userId): ?int
    {
        if ($userId < 1) {
            return null;
        }

        $row = $this->db->table(self::TABLE)
            ->select('session_version')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        return isset($row['session_version']) && (int) $row['session_version'] > 0
            ? (int) $row['session_version']
            : null;
    }

    public function matchesActiveSession(
        int $userId,
        int $role,
        ?int $branchId,
        int $sessionVersion,
    ): bool {
        if (
            $userId < 1
            || $sessionVersion < 1
            || ! (new AuthorizationPolicy())->isKnownRole($role)
            || ($branchId !== null && $branchId < 1)
            || ($role !== 1 && $branchId === null)
        ) {
            return false;
        }

        return $this->db->table(self::TABLE)
            ->where('id', $userId)
            ->where('role_id', $role)
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->where('session_version', $sessionVersion)
            ->countAllResults() === 1;
    }

    public function replacePasswordAndRevokeSessions(int $userId, string $passwordHash): bool
    {
        $this->assertPasswordHash($passwordHash);

        if ($userId < 1) {
            return false;
        }

        $updated = $this->db->table(self::TABLE)
            ->set('password_hash', $passwordHash)
            ->set('session_version', 'session_version + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', $userId)
            ->where('is_active', 1)
            ->update();

        if (! $updated) {
            throw new RuntimeException('Unable to replace shadow user password.');
        }

        return $this->db->affectedRows() === 1;
    }

    private function normalizedEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        return $email !== ''
            && strlen($email) <= 128
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                ? $email
                : null;
    }

    private function assertPasswordHash(string $passwordHash): void
    {
        if (strlen($passwordHash) > 255 || password_get_info($passwordHash)['algo'] === null) {
            throw new InvalidArgumentException('Invalid password hash.');
        }
    }
}
