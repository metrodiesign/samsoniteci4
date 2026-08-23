<?php

namespace App\Filters;

use App\Authorization\AuthorizationPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AuthorizationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $action = is_array($arguments) && count($arguments) === 1 ? $arguments[0] : '';

        if ((new AuthorizationPolicy())->allowsAction(service('session')->get('role'), $action)) {
            return null;
        }

        return service('response')
            ->setStatusCode(403)
            ->setJSON(['error' => 'forbidden']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
