<?php

namespace App\Contact;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ContactDeliveryIntentStore
{
    public function __construct(
        private BaseConnection $db,
        private EncrypterInterface $encrypter,
    ) {
    }

    public function reserveNext(DateTimeImmutable $now): ?ContactDelivery
    {
        $timestamp = $this->timestamp($now);
        $row = $this->db->table('ci4_delivery_intents')
            ->select(['id', 'idempotency_key', 'user_id', 'request_id', 'payload_ciphertext'])
            ->where('kind', 'contact')
            ->whereIn('status', ['pending', 'retry'])
            ->where('available_at <=', $timestamp)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($row === null) {
            return null;
        }
        $reserved = $this->db->table('ci4_delivery_intents')
            ->set('status', 'sending')
            ->set('attempt_count', 'attempt_count + 1', false)
            ->set('locked_at', $timestamp)
            ->set('updated_at', $timestamp)
            ->where('id', $row['id'])
            ->whereIn('status', ['pending', 'retry'])
            ->update();
        if (! $reserved) {
            throw new RuntimeException('Unable to reserve contact delivery intent.');
        }
        if ($this->db->affectedRows() !== 1) {
            return null;
        }

        try {
            return $this->decode($row);
        } catch (Throwable $exception) {
            $this->db->table('ci4_delivery_intents')
                ->where('id', $row['id'])
                ->where('status', 'sending')
                ->update([
                    'status'          => 'failed',
                    'last_error_code' => 'payload_invalid',
                    'updated_at'      => $timestamp,
                ]);

            throw new RuntimeException('Unable to decode contact delivery intent.', 0, $exception);
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
            throw new InvalidArgumentException('Invalid contact delivery retry.');
        }
        $updated = $this->db->table('ci4_delivery_intents')
            ->where('id', $intentId)
            ->where('kind', 'contact')
            ->where('status', 'sending')
            ->update([
                'status'          => 'retry',
                'available_at'    => $this->timestamp($nextAttemptAt),
                'locked_at'       => null,
                'last_error_code' => $errorCode,
                'updated_at'      => $this->timestamp($now),
            ]);
        if (! $updated) {
            throw new RuntimeException('Unable to retry contact delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    public function markSent(int $intentId, DateTimeImmutable $now): bool
    {
        $updated = $this->db->table('ci4_delivery_intents')
            ->where('id', $intentId)
            ->where('kind', 'contact')
            ->where('status', 'sending')
            ->update([
                'status'          => 'sent',
                'locked_at'       => null,
                'sent_at'         => $this->timestamp($now),
                'last_error_code' => null,
                'updated_at'      => $this->timestamp($now),
            ]);
        if (! $updated) {
            throw new RuntimeException('Unable to complete contact delivery intent.');
        }

        return $this->db->affectedRows() === 1;
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): ContactDelivery
    {
        $ciphertext = is_string($row['payload_ciphertext'] ?? null)
            ? base64_decode($row['payload_ciphertext'], true)
            : false;
        if ($ciphertext === false) {
            throw new RuntimeException('Invalid contact ciphertext.');
        }
        $payload = json_decode($this->encrypter->decrypt($ciphertext), true, 8, JSON_THROW_ON_ERROR);
        $requestId = is_string($row['request_id'] ?? null) ? $row['request_id'] : '';
        $idempotencyKey = is_string($row['idempotency_key'] ?? null) ? $row['idempotency_key'] : '';
        if (
            ! is_array($payload)
            || preg_match('/^[0-9a-f]{32}$/D', $requestId) !== 1
            || ! hash_equals(hash('sha256', "contact\0" . $requestId), $idempotencyKey)
            || (int) ($payload['contact_id'] ?? 0) !== (int) ($row['user_id'] ?? 0)
        ) {
            throw new RuntimeException('Invalid contact delivery payload.');
        }
        foreach (['recipient', 'email', 'fullname', 'phone', 'detail'] as $field) {
            if (! is_string($payload[$field] ?? null)) {
                throw new RuntimeException('Invalid contact delivery field.');
            }
        }

        return new ContactDelivery(
            (int) $row['id'],
            $idempotencyKey,
            $requestId,
            $payload['recipient'],
            $payload['email'],
            $payload['fullname'],
            $payload['phone'],
            $payload['detail'],
        );
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
