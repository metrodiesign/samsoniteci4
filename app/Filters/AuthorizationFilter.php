<?php

namespace App\Filters;

use App\Authorization\AuthorizationPolicy;
use App\Presentation\AccessDeniedResponder;
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

        return (new AccessDeniedResponder())->respond($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
