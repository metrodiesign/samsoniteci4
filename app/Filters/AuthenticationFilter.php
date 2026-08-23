<?php

namespace App\Filters;

use App\Authentication\ShadowUserStore;
use App\Authorization\AuthorizationPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthenticationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $session = service('session');
        $policy  = new AuthorizationPolicy();
        $userId  = $this->positiveInteger($session->get('userId'));
        $role    = $this->positiveInteger($session->get('role'));
        $version = $this->positiveInteger($session->get('sessionVersion'));
        $rawBranchId = $session->get('BranchID');
        $branchId    = $rawBranchId === null ? null : $this->positiveInteger($rawBranchId);

        if (
            $session->get('isLoggedIn') === true
            && $userId !== null
            && $role !== null
            && $version !== null
            && ($rawBranchId === null || $branchId !== null)
            && $policy->isKnownRole($role)
            && ($role === 1 || $policy->allowsBranch($role, $branchId, $branchId))
            && (new ShadowUserStore(db_connect()))->matchesActiveSession($userId, $role, $branchId, $version)
        ) {
            return null;
        }

        if (is_array($arguments) && in_array('redirect', $arguments, true)) {
            $session->destroy();

            return redirect()->to('/login');
        }

        return service('response')
            ->setStatusCode(401)
            ->setJSON(['error' => 'unauthenticated']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
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
