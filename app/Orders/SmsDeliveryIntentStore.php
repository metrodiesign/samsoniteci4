<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SmsDeliveryIntentStore
{
    public function __construct(private BaseConnection $db, private EncrypterInterface $encrypter)
    {
    }

    public function reserveNext(DateTimeImmutable $now): ?SmsDelivery
    {
        $timestamp = $this->timestamp($now);
        $row = $this->db->table('ci4_delivery_intents')
            ->select('id, idempotency_key, user_id, request_id, payload_ciphertext')
            ->where('kind', 'sms')->whereIn('status', ['pending', 'retry'])
            ->where('available_at <=', $timestamp)->orderBy('id', 'ASC')->get(1)->getRowArray();
        if ($row === null) {
            return null;
        }
        $reserved = $this->db->table('ci4_delivery_intents')
            ->set('status', 'sending')->set('attempt_count', 'attempt_count + 1', false)
            ->set('locked_at', $timestamp)->set('updated_at', $timestamp)
            ->where('id', $row['id'])->where('kind', 'sms')->whereIn('status', ['pending', 'retry'])->update();
        if (! $reserved) {
            throw new RuntimeException('Unable to reserve SMS delivery intent.');
        }
        if ($this->db->affectedRows() !== 1) {
            return null;
        }
        try {
            return $this->decode($row);
        } catch (Throwable $exception) {
            $this->db->table('ci4_delivery_intents')->where('id', $row['id'])->where('status', 'sending')->update([
                'status' => 'failed', 'last_error_code' => 'payload_invalid', 'updated_at' => $timestamp,
            ]);
            throw new RuntimeException('Unable to decode SMS delivery intent.', 0, $exception);
        }
    }

    public function markRetry(int $intentId, string $errorCode, DateTimeImmutable $nextAttemptAt, DateTimeImmutable $now): bool
    {
        if ($intentId < 1 || preg_match('/\A[a-z0-9_]{1,32}\z/D', $errorCode) !== 1 || $nextAttemptAt <= $now) {
            throw new InvalidArgumentException('Invalid SMS delivery retry.');
        }
        $updated = $this->db->table('ci4_delivery_intents')
            ->where('id', $intentId)->where('kind', 'sms')->where('status', 'sending')->update([
                'status' => 'retry', 'available_at' => $this->timestamp($nextAttemptAt), 'locked_at' => null,
                'last_error_code' => $errorCode, 'updated_at' => $this->timestamp($now),
            ]);
        if (! $updated) {
            throw new RuntimeException('Unable to retry SMS delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function markSent(int $intentId, DateTimeImmutable $now): bool
    {
        $updated = $this->db->table('ci4_delivery_intents')
            ->where('id', $intentId)->where('kind', 'sms')->where('status', 'sending')->update([
                'status' => 'sent', 'locked_at' => null, 'sent_at' => $this->timestamp($now),
                'last_error_code' => null, 'updated_at' => $this->timestamp($now),
            ]);
        if (! $updated) {
            throw new RuntimeException('Unable to complete SMS delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function releaseStale(DateTimeImmutable $lockedBefore, DateTimeImmutable $now): int
    {
        if ($lockedBefore >= $now) {
            throw new InvalidArgumentException('Invalid stale SMS cutoff.');
        }
        $updated = $this->db->table('ci4_delivery_intents')
            ->where('kind', 'sms')->where('status', 'sending')
            ->where('locked_at <', $this->timestamp($lockedBefore))->update([
                'status' => 'retry', 'available_at' => $this->timestamp($now), 'locked_at' => null,
                'last_error_code' => 'worker_stale', 'updated_at' => $this->timestamp($now),
            ]);
        if (! $updated) {
            throw new RuntimeException('Unable to release stale SMS delivery intents.');
        }

        return $this->db->affectedRows();
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): SmsDelivery
    {
        $ciphertext = is_string($row['payload_ciphertext'] ?? null)
            ? base64_decode($row['payload_ciphertext'], true)
            : false;
        if ($ciphertext === false) {
            throw new RuntimeException('Invalid SMS ciphertext.');
        }
        $payload = json_decode($this->encrypter->decrypt($ciphertext), true, 8, JSON_THROW_ON_ERROR);
        $requestId = is_string($row['request_id'] ?? null) ? $row['request_id'] : '';
        $key = is_string($row['idempotency_key'] ?? null) ? $row['idempotency_key'] : '';
        if (! is_array($payload) || preg_match('/\A[a-f0-9]{32}\z/D', $requestId) !== 1
            || ! hash_equals(hash('sha256', "sms\0" . $requestId), $key)
            || (int) ($payload['order_id'] ?? 0) !== (int) ($row['user_id'] ?? 0)
            || (int) ($payload['order_id'] ?? 0) < 1
            || ! is_string($payload['track_id'] ?? null)
            || preg_match('/\AG[0-9]{8}\z/D', $payload['track_id']) !== 1
            || ! is_string($payload['telephone'] ?? null)
            || preg_match('/\A[0-9]{10,20}\z/D', $payload['telephone']) !== 1
            || ! is_string($payload['message'] ?? null) || mb_strlen($payload['message']) > 500
            || ! str_contains($payload['message'], $payload['track_id'])) {
            throw new RuntimeException('Invalid SMS delivery payload.');
        }

        return new SmsDelivery(
            (int) $row['id'], $key, $requestId, (int) $payload['order_id'],
            $payload['track_id'], $payload['telephone'], $payload['message'],
        );
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
