<?php

namespace App\Authorization;

use CodeIgniter\Exceptions\PageNotFoundException;

final class AuthorizationPolicy
{
    public const ACTION_READ = 'read';

    public const ACTION_WRITE = 'write';

    public const ACTION_DELETE = 'delete';

    /**
     * CI3 parity (business decision 2026-08-23): every known role may write and
     * delete, as the legacy isAdmin() gate was dead code and never restricted
     * anyone. Branch isolation below still applies to roles 2 and 3.
     *
     * @var array<int, list<string>>
     */
    private const ACTIONS_BY_ROLE = [
        1 => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
        2 => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
        3 => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
    ];

    public function allowsAction(mixed $role, string $action): bool
    {
        $role = $this->positiveInteger($role);

        return $role !== null && in_array($action, self::ACTIONS_BY_ROLE[$role] ?? [], true);
    }

    public function isKnownRole(mixed $role): bool
    {
        $role = $this->positiveInteger($role);

        return $role !== null && isset(self::ACTIONS_BY_ROLE[$role]);
    }

    public function allowsBranch(mixed $role, mixed $actorBranchId, mixed $objectBranchId): bool
    {
        $role           = $this->positiveInteger($role);
        $objectBranchId = $this->positiveInteger($objectBranchId);

        if ($objectBranchId === null) {
            return false;
        }

        if ($role === 1) {
            return true;
        }

        if ($role !== 2 && $role !== 3) {
            return false;
        }

        $actorBranchId = $this->positiveInteger($actorBranchId);

        return $actorBranchId !== null && $actorBranchId === $objectBranchId;
    }

    public function assertBranchAccess(mixed $role, mixed $actorBranchId, mixed $objectBranchId): void
    {
        if (! $this->allowsBranch($role, $actorBranchId, $objectBranchId)) {
            throw PageNotFoundException::forPageNotFound();
        }
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

        return $integer === false ? null : $integer;
    }
}
