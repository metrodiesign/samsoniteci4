<?php

namespace App\Controllers;

use App\Presentation\LegacyViewRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class ParityErrors extends BaseController
{
    private function guard(): void
    {
        if (ENVIRONMENT !== 'parity' || getenv('PARITY_ERROR_TRIGGER') !== 'enabled') {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    public function legacyPageNotFound(): ResponseInterface
    {
        $content = (new LegacyViewRenderer())->render('404');

        return $this->response
            ->setContentType('text/html')
            ->setBody($this->layout('Tracking : 404 - Page Not Found', $content, ['contentOwnsWrapper' => true]));
    }

    public function trigger(string $kind): never
    {
        $this->guard();
        if ($kind === '404') {
            throw PageNotFoundException::forPageNotFound('Synthetic parity 404.');
        }
        if ($kind === 'db') {
            db_connect()->query('SELECT * FROM __parity_missing_table');
        }
        if ($kind === 'php') {
            trigger_error('Synthetic parity PHP error.', E_USER_WARNING);
        }
        throw new RuntimeException('Synthetic parity ' . $kind . ' error.');
    }
}
