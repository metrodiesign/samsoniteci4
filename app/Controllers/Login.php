<?php

namespace App\Controllers;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\LoginService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class Login extends BaseController
{
    public function index(): string
    {
        return view('layout', [
            'title'   => 'Sign in',
            'content' => view('login', [
                'error' => service('session')->getFlashdata('login_error'),
            ]),
        ]);
    }

    public function authenticate(): RedirectResponse|ResponseInterface
    {
        $identifier = $this->request->getPost('username');
        $password   = $this->request->getPost('password');
        if (
            ! is_string($identifier)
            || ! is_string($password)
            || trim($identifier) === ''
            || strlen($identifier) > 128
            || $password === ''
            || strlen($password) > 128
        ) {
            return $this->failedLogin();
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $limiter   = new AtomicRateLimiter(db_connect());
            $ipAddress = $this->request->getIPAddress();
            $retryAfter = $limiter->consume("login_ip\0" . $ipAddress, 20, 900, $now)
                ?? $limiter->consume(
                    "login_identity\0" . $ipAddress . "\0" . strtolower(trim($identifier)),
                    5,
                    900,
                    $now,
                );
            if ($retryAfter !== null) {
                return $this->response
                    ->setHeader('Retry-After', (string) $retryAfter)
                    ->setStatusCode(429)
                    ->setBody(view('layout', [
                        'title'   => 'Sign in',
                        'content' => view('login', ['error' => 'Unable to sign in. Try again later.']),
                    ]));
            }

            $sessionData = (new LoginService(db_connect()))->authenticate(
                $identifier,
                $password,
                $ipAddress,
                $this->request->getUserAgent()->getAgentString(),
            );
        } catch (Throwable $exception) {
            log_message('error', 'Login service unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setBody(view('layout', [
                    'title'   => 'Sign in',
                    'content' => view('login', ['error' => 'Sign-in service is unavailable.']),
                ]));
        }

        if ($sessionData === null) {
            return $this->failedLogin();
        }

        $session = service('session');
        $session->regenerate(true);
        $session->set($sessionData);

        return redirect()->to('/dashboard');
    }

    public function logout(): RedirectResponse
    {
        service('session')->destroy();

        return redirect()->to('/login');
    }

    private function failedLogin(): RedirectResponse
    {
        service('session')->setFlashdata('login_error', 'Unable to sign in with those credentials.');

        return redirect()->to('/login');
    }
}
