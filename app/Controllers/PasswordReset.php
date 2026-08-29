<?php

namespace App\Controllers;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\PasswordPolicy;
use App\Authentication\PasswordResetWorkflow;
use App\Authentication\ResetAuditLog;
use App\Authentication\ResetRequestWorkflow;
use App\Authentication\ResetTokenStore;
use App\Authentication\ShadowUserStore;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use JsonException;
use Throwable;

final class PasswordReset extends BaseController
{
    private const GENERIC_REQUEST_MESSAGE = 'If the account exists, reset instructions will be sent.';

    private const INVALID_RESET_MESSAGE = 'This reset link is invalid or has expired. Please request a new one.';

    private const RESET_SERVICE_UNAVAILABLE_MESSAGE = 'The reset service is temporarily unavailable. Please try again later.';

    private const MAX_JSON_BYTES = 4096;

    private const RESET_REQUEST_MINIMUM_NANOSECONDS = 100_000_000;

    public function csrf(): ResponseInterface
    {
        $security = service('security');

        return $this->response
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON([
                'header' => $security->getHeaderName(),
                'token'  => $security->getHash(),
            ]);
    }

    public function forgotForm(): string
    {
        $message = service('session')->getFlashdata('reset_message');

        return $this->forgotDocument(is_string($message) ? $message : null);
    }

    public function resetForm(): string
    {
        $token = $this->request->getGet('token');

        return $this->resetDocument(is_string($token) ? $token : '');
    }

    public function legacyResetForm(): RedirectResponse
    {
        // CI3 embedded a reusable activation code and account email in this URL.
        // CI4 issues only hashed, expiring, single-use tokens, so old links cannot be
        // safely translated. Keep route reachable without reflecting either value.
        return redirect()->to('/forgotPassword');
    }

    public function requestResetForm(): RedirectResponse|ResponseInterface
    {
        $result = $this->requestOperation($this->request->getPost('login_email'));

        if ($result['status'] === 202) {
            return redirect()->to('/forgotPassword')->with('reset_message', self::GENERIC_REQUEST_MESSAGE);
        }

        $message = match ($result['error'] ?? '') {
            'invalid_request'   => 'Please enter a valid email address.',
            'too_many_requests' => 'Too many requests. Please try again later.',
            default             => self::RESET_SERVICE_UNAVAILABLE_MESSAGE,
        };

        if (isset($result['retry_after'])) {
            $this->response->setHeader('Retry-After', (string) $result['retry_after']);
        }

        return $this->response
            ->setStatusCode($result['status'])
            ->setBody($this->forgotDocument($message, 'alert-danger'));
    }

    public function completeResetForm(): RedirectResponse|ResponseInterface
    {
        $email = $this->request->getPost('email');
        $token = $this->request->getPost('activation_code');
        $result = $this->completeOperation(
            $email,
            $token,
            $this->request->getPost('password'),
            $this->request->getPost('cpassword'),
        );

        if ($result['status'] === 200) {
            service('session')->setFlashdata('login_success', 'Your password has been reset. You can now sign in.');

            return redirect()->to('/login');
        }

        $message = match ($result['error'] ?? '') {
            'invalid_password'          => 'Password does not meet the requirements or the confirmation does not match.',
            'too_many_requests'         => 'Too many attempts. Please try again later.',
            'reset_service_unavailable' => self::RESET_SERVICE_UNAVAILABLE_MESSAGE,
            default                     => self::INVALID_RESET_MESSAGE,
        };

        if (isset($result['retry_after'])) {
            $this->response->setHeader('Retry-After', (string) $result['retry_after']);
        }

        return $this->response
            ->setStatusCode($result['status'])
            ->setBody($this->resetDocument(
                is_string($token) ? $token : '',
                $message,
                is_string($email) ? $email : null,
            ));
    }

    public function requestReset(): ResponseInterface
    {
        if (! str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/json')) {
            $this->audit()->record('request_invalid', null, $this->request->getIPAddress());

            return $this->response
                ->setStatusCode(415)
                ->setJSON(['error' => 'unsupported_media_type']);
        }

        if (($response = $this->oversizedPayloadResponse()) !== null) {
            $this->audit()->record('request_invalid', null, $this->request->getIPAddress());

            return $response;
        }

        $payload = $this->jsonPayload();
        $result = $this->requestOperation(is_array($payload) ? ($payload['email'] ?? null) : null);

        if ($result['status'] === 202) {
            return $this->response
                ->setStatusCode(202)
                ->setJSON([
                    'status'  => 'accepted',
                    'message' => self::GENERIC_REQUEST_MESSAGE,
                ]);
        }

        if (isset($result['retry_after'])) {
            $this->response->setHeader('Retry-After', (string) $result['retry_after']);
        }

        return $this->response
            ->setStatusCode($result['status'])
            ->setJSON(['error' => $result['error'] ?? 'reset_service_unavailable']);
    }

    public function completeReset(): ResponseInterface
    {
        if (! str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/json')) {
            $this->audit()->record('complete_invalid', null, $this->request->getIPAddress());

            return $this->response
                ->setStatusCode(415)
                ->setJSON(['error' => 'unsupported_media_type']);
        }

        if (($response = $this->oversizedPayloadResponse()) !== null) {
            $this->audit()->record('complete_invalid', null, $this->request->getIPAddress());

            return $response;
        }

        $payload = $this->jsonPayload();
        $result = $this->completeOperation(
            is_array($payload) ? ($payload['email'] ?? null) : null,
            is_array($payload) ? ($payload['token'] ?? null) : null,
            is_array($payload) ? ($payload['password'] ?? null) : null,
            is_array($payload) ? ($payload['password_confirmation'] ?? null) : null,
        );

        if ($result['status'] === 200) {
            return $this->response->setJSON(['status' => 'password_reset']);
        }

        if (isset($result['retry_after'])) {
            $this->response->setHeader('Retry-After', (string) $result['retry_after']);
        }

        return $this->response
            ->setStatusCode($result['status'])
            ->setJSON(['error' => $result['error'] ?? 'reset_service_unavailable']);
    }

    /** @return array{status: int, error?: string, retry_after?: int} */
    private function requestOperation(mixed $email): array
    {
        if (
            ! is_string($email)
            || strlen($email) > 128
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
        ) {
            $this->audit()->record('request_invalid', null, $this->request->getIPAddress());

            return ['status' => 422, 'error' => 'invalid_request'];
        }

        $now = new DateTimeImmutable('now');

        try {
            $retryAfter = $this->requestRateLimitRetryAfter($email, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Reset request rate limiter unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return ['status' => 503, 'error' => 'reset_service_unavailable'];
        }

        if ($retryAfter !== null) {
            $this->audit()->record('request_throttled', $email, $this->request->getIPAddress());

            return ['status' => 429, 'error' => 'too_many_requests', 'retry_after' => $retryAfter];
        }

        $startedAt = hrtime(true);

        try {
            (new ResetRequestWorkflow(db_connect(), service('encrypter')))->request($email, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Reset request unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return ['status' => 503, 'error' => 'reset_service_unavailable'];
        } finally {
            $this->waitForResetRequestFloor($startedAt);
        }

        try {
            $known = (new ShadowUserStore(db_connect()))->findActiveIdByEmail($email) !== null;
            $this->audit()->record(
                $known ? 'request_accepted' : 'request_unknown_identity',
                $email,
                $this->request->getIPAddress(),
            );
        } catch (Throwable $exception) {
            log_message('error', 'Password reset audit lookup failed: {exception}', [
                'exception' => $exception::class,
            ]);
        }

        return ['status' => 202];
    }

    /** @return array{status: int, error?: string, retry_after?: int} */
    private function completeOperation(
        mixed $email,
        mixed $token,
        mixed $password,
        mixed $confirmation,
    ): array {
        if (
            ! is_string($email)
            || ! is_string($token)
            || ! is_string($password)
            || ! is_string($confirmation)
            || strlen($email) > 128
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
            || preg_match('/^[0-9a-f]{64}$/D', $token) !== 1
        ) {
            $this->audit()->record('complete_invalid', null, $this->request->getIPAddress());

            return ['status' => 400, 'error' => 'invalid_or_expired_reset'];
        }

        if (! hash_equals($password, $confirmation) || ! (new PasswordPolicy())->accepts($password)) {
            $this->audit()->record('complete_invalid', $email, $this->request->getIPAddress());

            return ['status' => 422, 'error' => 'invalid_password'];
        }

        $now = new DateTimeImmutable('now');

        try {
            $retryAfter = $this->completeRateLimitRetryAfter($email, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Password reset rate limiter unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return ['status' => 503, 'error' => 'reset_service_unavailable'];
        }

        if ($retryAfter !== null) {
            $this->audit()->record('complete_throttled', $email, $this->request->getIPAddress());

            return ['status' => 429, 'error' => 'too_many_requests', 'retry_after' => $retryAfter];
        }

        try {
            $reset = (new PasswordResetWorkflow(db_connect()))->reset($email, $token, $password, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Password reset unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return ['status' => 503, 'error' => 'reset_service_unavailable'];
        }

        if (! $reset) {
            $this->audit()->record('complete_invalid_token', $email, $this->request->getIPAddress());

            return ['status' => 400, 'error' => 'invalid_or_expired_reset'];
        }

        $this->audit()->record('complete_success', $email, $this->request->getIPAddress());

        return ['status' => 200];
    }

    private function forgotDocument(?string $message = null, string $messageClass = 'alert-success'): string
    {
        return view('forgot_password', [
            'message'      => $message,
            'messageClass' => $messageClass,
        ]);
    }

    private function resetDocument(string $token, ?string $message = null, ?string $expectedEmail = null): string
    {
        if ($message === self::RESET_SERVICE_UNAVAILABLE_MESSAGE) {
            return view('reset_password', [
                'email'   => '',
                'token'   => '',
                'message' => $message,
            ]);
        }

        $email = null;
        $lookupFailed = false;

        try {
            $userId = (new ResetTokenStore(db_connect()))->findActiveUserId($token, new DateTimeImmutable('now'));
            $email = $userId === null ? null : (new ShadowUserStore(db_connect()))->findActiveEmailById($userId);
        } catch (Throwable $exception) {
            $lookupFailed = true;
            log_message('error', 'Reset page lookup failed: {exception}', [
                'exception' => $exception::class,
            ]);
        }

        if ($lookupFailed) {
            $token = '';
            $email = '';
            $message ??= self::RESET_SERVICE_UNAVAILABLE_MESSAGE;
        } elseif (
            $email === null
            || ($expectedEmail !== null && ! hash_equals($email, strtolower(trim($expectedEmail))))
        ) {
            $token = '';
            $email = '';
            $message = self::INVALID_RESET_MESSAGE;
        }

        return view('reset_password', [
            'email'   => $email,
            'token'   => $token,
            'message' => $message,
        ]);
    }

    private function audit(): ResetAuditLog
    {
        return new ResetAuditLog(db_connect());
    }

    private function oversizedPayloadResponse(): ?ResponseInterface
    {
        if (strlen($this->request->getBody()) <= self::MAX_JSON_BYTES) {
            return null;
        }

        return $this->response
            ->setStatusCode(413)
            ->setJSON(['error' => 'payload_too_large']);
    }

    /** @return array<string, mixed>|null */
    private function jsonPayload(): ?array
    {
        try {
            $payload = json_decode($this->request->getBody(), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function requestRateLimitRetryAfter(string $email, DateTimeImmutable $now): ?int
    {
        $ipAddress = $this->request->getIPAddress();
        $limiter   = new AtomicRateLimiter(db_connect());

        return $limiter->consume("password_reset_request_ip\0" . $ipAddress, 20, 900, $now)
            ?? $limiter->consume(
                "password_reset_request_identity\0" . $ipAddress . "\0" . strtolower(trim($email)),
                5,
                900,
                $now,
            );
    }

    private function completeRateLimitRetryAfter(string $email, DateTimeImmutable $now): ?int
    {
        $ipAddress = $this->request->getIPAddress();
        $limiter   = new AtomicRateLimiter(db_connect());

        return $limiter->consume("password_reset_complete_ip\0" . $ipAddress, 30, 900, $now)
            ?? $limiter->consume(
                "password_reset_complete_identity\0" . $ipAddress . "\0" . strtolower(trim($email)),
                10,
                900,
                $now,
            );
    }

    private function waitForResetRequestFloor(int $startedAt): void
    {
        $remaining = self::RESET_REQUEST_MINIMUM_NANOSECONDS - (hrtime(true) - $startedAt);

        if ($remaining > 0) {
            usleep((int) ceil($remaining / 1_000));
        }
    }
}
