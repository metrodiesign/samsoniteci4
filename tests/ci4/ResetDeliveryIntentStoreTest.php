<?php

namespace Tests\Ci4;

use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetTokenFactory;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;

final class ResetDeliveryIntentStoreTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testEncryptedIntentCanBeReservedForDelivery(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x0b", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store   = new ResetDeliveryIntentStore($this->db, $this->encrypter());

        $store->enqueue(9002, 'user@example.invalid', $issued);
        $delivery = $store->reserveNext(new DateTimeImmutable('2026-08-22T09:01:00+00:00'));

        self::assertNotNull($delivery);
        self::assertSame('user@example.invalid', $delivery->recipient());
        self::assertSame($issued->token(), $delivery->token());
        self::assertSame($issued->requestId(), $delivery->requestId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $delivery->idempotencyKey());

        $debug = print_r($delivery, true);
        self::assertStringNotContainsString('user@example.invalid', $debug);
        self::assertStringNotContainsString($issued->token(), $debug);
    }

    public function testRetryKeepsIdempotencyKeyAndWaitsUntilAvailable(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x12", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store   = new ResetDeliveryIntentStore($this->db, $this->encrypter());
        $store->enqueue(9002, 'retry@example.invalid', $issued);
        $first = $store->reserveNext(new DateTimeImmutable('2026-08-22T09:01:00+00:00'));

        self::assertNotNull($first);
        self::assertTrue($store->markRetry(
            $first->intentId(),
            'provider_timeout',
            new DateTimeImmutable('2026-08-22T09:06:00+00:00'),
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
        ));
        self::assertNull($store->reserveNext(new DateTimeImmutable('2026-08-22T09:05:59+00:00')));

        $retry = $store->reserveNext(new DateTimeImmutable('2026-08-22T09:06:00+00:00'));

        self::assertNotNull($retry);
        self::assertSame($first->idempotencyKey(), $retry->idempotencyKey());
        self::assertTrue($store->markSent(
            $retry->intentId(),
            new DateTimeImmutable('2026-08-22T09:06:01+00:00'),
        ));
        self::assertNull($store->reserveNext(new DateTimeImmutable('2026-08-22T09:07:00+00:00')));
    }

    public function testStaleWorkerReservationCanBeRecovered(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x17", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store   = new ResetDeliveryIntentStore($this->db, $this->encrypter());
        $store->enqueue(9002, 'recovery@example.invalid', $issued);
        $reserved = $store->reserveNext(new DateTimeImmutable('2026-08-22T09:01:00+00:00'));
        $this->db->table('ci4_delivery_intents')->insert([
            'idempotency_key' => str_repeat('a', 64), 'kind' => 'sms', 'user_id' => 1,
            'request_id' => str_repeat('b', 32), 'payload_ciphertext' => 'not-read-by-reset-store',
            'status' => 'sending', 'attempt_count' => 1, 'available_at' => '2026-08-22 09:00:00',
            'locked_at' => '2026-08-22 09:00:00', 'created_at' => '2026-08-22 09:00:00',
            'updated_at' => '2026-08-22 09:00:00',
        ]);

        self::assertNotNull($reserved);
        self::assertSame(1, $store->releaseStale(
            new DateTimeImmutable('2026-08-22T09:05:00+00:00'),
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
        ));

        $recovered = $store->reserveNext(new DateTimeImmutable('2026-08-22T09:10:00+00:00'));

        self::assertNotNull($recovered);
        self::assertSame($reserved->idempotencyKey(), $recovered->idempotencyKey());
        self::assertSame('sending', $this->db->table('ci4_delivery_intents')
            ->where('kind', 'sms')->get()->getRow('status'));
    }

    private function encrypter(): EncrypterInterface
    {
        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x0c", 32);

        return Services::encrypter($config, false);
    }
}
