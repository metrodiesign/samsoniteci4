<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class Health extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'status'  => 'ok',
            'service' => 'ci4',
        ]);
    }
}
