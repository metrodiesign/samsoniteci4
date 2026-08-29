<?php

namespace App\Filters;

use App\Presentation\AccessDeniedResponder;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class BranchlessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        if (service('session')->get('BranchID') === null) {
            return null;
        }

        return (new AccessDeniedResponder())->respond($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
