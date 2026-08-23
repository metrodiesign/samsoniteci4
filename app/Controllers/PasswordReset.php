<?php

namespace App\Controllers;

use App\Authentication\AtomicRateLimiter;
use App\Authentication\PasswordPolicy;
use App\Authentication\PasswordResetWorkflow;
use App\Authentication\ResetRequestWorkflow;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use JsonException;
use Throwable;

final class PasswordReset extends BaseController
{
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

    public function requestReset(): ResponseInterface
    {
        if (! str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/json')) {
            return $this->response
                ->setStatusCode(415)
                ->setJSON(['error' => 'unsupported_media_type']);
        }

        if (($response = $this->oversizedPayloadResponse()) !== null) {
            return $response;
        }

        $payload = $this->jsonPayload();
        $email = is_array($payload) ? ($payload['email'] ?? null) : null;

        if (
            ! is_string($email)
            || strlen($email) > 128
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
        ) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'invalid_request']);
        }

        $now = new DateTimeImmutable('now');

        try {
            $retryAfter = $this->requestRateLimitRetryAfter($email, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Reset request rate limiter unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setJSON(['error' => 'reset_service_unavailable']);
        }

        if ($retryAfter !== null) {
            return $this->response
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setStatusCode(429)
                ->setJSON(['error' => 'too_many_requests']);
        }

        $startedAt = hrtime(true);

        try {
            (new ResetRequestWorkflow(db_connect(), service('encrypter')))->request(
                $email,
                $now,
            );
        } catch (Throwable $exception) {
            log_message('error', 'Reset request unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setJSON(['error' => 'reset_service_unavailable']);
        } finally {
            $this->waitForResetRequestFloor($startedAt);
        }

        return $this->response
            ->setStatusCode(202)
            ->setJSON([
                'status'  => 'accepted',
                'message' => 'If the account exists, reset instructions will be sent.',
            ]);
    }

    public function completeReset(): ResponseInterface
    {
        if (! str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/json')) {
            return $this->response
                ->setStatusCode(415)
                ->setJSON(['error' => 'unsupported_media_type']);
        }

        if (($response = $this->oversizedPayloadResponse()) !== null) {
            return $response;
        }

        $payload = $this->jsonPayload();
        $email = is_array($payload) && is_string($payload['email'] ?? null) ? $payload['email'] : '';
        $token = is_array($payload) && is_string($payload['token'] ?? null) ? $payload['token'] : '';
        $password = is_array($payload) && is_string($payload['password'] ?? null) ? $payload['password'] : '';
        $confirmation = is_array($payload) && is_string($payload['password_confirmation'] ?? null)
            ? $payload['password_confirmation']
            : '';

        if (
            strlen($email) > 128
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
            || preg_match('/^[0-9a-f]{64}$/D', $token) !== 1
        ) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'invalid_or_expired_reset']);
        }

        if (! hash_equals($password, $confirmation) || ! (new PasswordPolicy())->accepts($password)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'invalid_password']);
        }

        $now = new DateTimeImmutable('now');

        try {
            $retryAfter = $this->completeRateLimitRetryAfter($email, $now);
        } catch (Throwable $exception) {
            log_message('error', 'Password reset rate limiter unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setJSON(['error' => 'reset_service_unavailable']);
        }

        if ($retryAfter !== null) {
            return $this->response
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setStatusCode(429)
                ->setJSON(['error' => 'too_many_requests']);
        }

        try {
            $reset = (new PasswordResetWorkflow(db_connect()))->reset(
                $email,
                $token,
                $password,
                $now,
            );
        } catch (Throwable $exception) {
            log_message('error', 'Password reset unavailable: {exception}', [
                'exception' => $exception::class,
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setJSON(['error' => 'reset_service_unavailable']);
        }

        if (! $reset) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'invalid_or_expired_reset']);
        }

        return $this->response->setJSON(['status' => 'password_reset']);
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
