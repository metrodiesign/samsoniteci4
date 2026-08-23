<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ResetDeliveryIntentStore
{
    private const KIND = 'password_reset';

    private const TABLE = 'ci4_delivery_intents';

    public function __construct(
        private BaseConnection $db,
        private EncrypterInterface $encrypter,
    ) {
    }

    public function enqueue(int $userId, string $recipient, IssuedResetToken $issued): void
    {
        $recipient = strtolower(trim($recipient));

        if (
            $userId < 1
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || strlen($recipient) > 128
        ) {
            throw new InvalidArgumentException('Invalid reset delivery intent.');
        }

        $payload = json_encode([
            'recipient'  => $recipient,
            'request_id' => $issued->requestId(),
            'token'      => $issued->token(),
        ], JSON_THROW_ON_ERROR);
        $timestamp = $this->timestamp($issued->createdAt());

        $superseded = $this->db->table(self::TABLE)
            ->where('kind', self::KIND)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'retry'])
            ->update([
                'status'          => 'cancelled',
                'last_error_code' => 'superseded',
                'updated_at'      => $timestamp,
            ]);

        if (! $superseded) {
            throw new RuntimeException('Unable to supersede reset delivery intent.');
        }

        $inserted  = $this->db->table(self::TABLE)->insert([
            'idempotency_key'   => $this->idempotencyKey($issued->requestId()),
            'kind'              => self::KIND,
            'user_id'           => $userId,
            'request_id'        => $issued->requestId(),
            'payload_ciphertext' => base64_encode($this->encrypter->encrypt($payload)),
            'status'            => 'pending',
            'attempt_count'     => 0,
            'available_at'      => $timestamp,
            'created_at'        => $timestamp,
            'updated_at'        => $timestamp,
        ]);

        if (! $inserted) {
            throw new RuntimeException('Unable to persist reset delivery intent.');
        }
    }

    public function reserveNext(DateTimeImmutable $now): ?ResetDelivery
    {
        $timestamp = $this->timestamp($now);
        $row = $this->db->table(self::TABLE)
            ->select('id, idempotency_key, request_id, payload_ciphertext')
            ->where('kind', self::KIND)
            ->whereIn('status', ['pending', 'retry'])
            ->where('available_at <=', $timestamp)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $reserved = $this->db->table(self::TABLE)
            ->set('status', 'sending')
            ->set('attempt_count', 'attempt_count + 1', false)
            ->set('locked_at', $timestamp)
            ->set('updated_at', $timestamp)
            ->where('id', $row['id'])
            ->where('kind', self::KIND)
            ->whereIn('status', ['pending', 'retry'])
            ->update();

        if (! $reserved) {
            throw new RuntimeException('Unable to reserve reset delivery intent.');
        }

        if ($this->db->affectedRows() !== 1) {
            return null;
        }

        try {
            return $this->decodeDelivery($row);
        } catch (Throwable $exception) {
            $this->db->table(self::TABLE)
                ->where('id', $row['id'])
                ->where('kind', self::KIND)
                ->where('status', 'sending')
                ->update([
                    'status'          => 'failed',
                    'last_error_code' => 'payload_invalid',
                    'updated_at'      => $timestamp,
                ]);

            throw new RuntimeException('Unable to decode reset delivery intent.', 0, $exception);
        }
    }

    public function markRetry(
        int $intentId,
        string $errorCode,
        DateTimeImmutable $nextAttemptAt,
        DateTimeImmutable $now,
    ): bool {
        if (
            $intentId < 1
            || preg_match('/^[a-z0-9_]{1,32}$/D', $errorCode) !== 1
            || $nextAttemptAt <= $now
        ) {
            throw new InvalidArgumentException('Invalid delivery retry.');
        }

        $updated = $this->db->table(self::TABLE)
            ->where('id', $intentId)
            ->where('kind', self::KIND)
            ->where('status', 'sending')
            ->update([
                'status'          => 'retry',
                'available_at'    => $this->timestamp($nextAttemptAt),
                'locked_at'       => null,
                'last_error_code' => $errorCode,
                'updated_at'      => $this->timestamp($now),
            ]);

        if (! $updated) {
            throw new RuntimeException('Unable to retry reset delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function markSent(int $intentId, DateTimeImmutable $now): bool
    {
        if ($intentId < 1) {
            return false;
        }

        $timestamp = $this->timestamp($now);
        $updated = $this->db->table(self::TABLE)
            ->where('id', $intentId)
            ->where('kind', self::KIND)
            ->where('status', 'sending')
            ->update([
                'status'          => 'sent',
                'locked_at'       => null,
                'sent_at'         => $timestamp,
                'last_error_code' => null,
                'updated_at'      => $timestamp,
            ]);

        if (! $updated) {
            throw new RuntimeException('Unable to complete reset delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function releaseStale(DateTimeImmutable $lockedBefore, DateTimeImmutable $now): int
    {
        if ($lockedBefore >= $now) {
            throw new InvalidArgumentException('Invalid stale delivery cutoff.');
        }

        $timestamp = $this->timestamp($now);
        $updated = $this->db->table(self::TABLE)
            ->where('kind', self::KIND)
            ->where('status', 'sending')
            ->where('locked_at <', $this->timestamp($lockedBefore))
            ->update([
                'status'          => 'retry',
                'available_at'    => $timestamp,
                'locked_at'       => null,
                'last_error_code' => 'worker_stale',
                'updated_at'      => $timestamp,
            ]);

        if (! $updated) {
            throw new RuntimeException('Unable to release stale reset delivery intents.');
        }

        return $this->db->affectedRows();
    }

    /** @param array<string, mixed> $row */
    private function decodeDelivery(array $row): ResetDelivery
    {
        $ciphertext = isset($row['payload_ciphertext']) && is_string($row['payload_ciphertext'])
            ? base64_decode($row['payload_ciphertext'], true)
            : false;

        if ($ciphertext === false) {
            throw new RuntimeException('Invalid delivery ciphertext.');
        }

        $payload = json_decode($this->encrypter->decrypt($ciphertext), true, 8, JSON_THROW_ON_ERROR);
        $requestId = is_string($row['request_id'] ?? null) ? $row['request_id'] : '';
        $recipient = is_string($payload['recipient'] ?? null) ? $payload['recipient'] : '';
        $token     = is_string($payload['token'] ?? null) ? $payload['token'] : '';

        if (
            ! is_array($payload)
            || ! hash_equals($requestId, is_string($payload['request_id'] ?? null) ? $payload['request_id'] : '')
            || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1
            || preg_match('/^[0-9a-f]{64}$/D', $token) !== 1
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || ! hash_equals(
                is_string($row['idempotency_key'] ?? null) ? $row['idempotency_key'] : '',
                $this->idempotencyKey($requestId),
            )
        ) {
            throw new RuntimeException('Invalid delivery payload.');
        }

        return new ResetDelivery(
            (int) $row['id'],
            $this->idempotencyKey($requestId),
            $requestId,
            $recipient,
            $token,
        );
    }

    private function idempotencyKey(string $requestId): string
    {
        return hash('sha256', self::KIND . "\0" . $requestId);
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    }
}
