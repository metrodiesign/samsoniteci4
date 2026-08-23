<?php

namespace Tests\Ci4;

use App\Authentication\LoopbackResetMailer;
use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetDeliveryWorker;
use App\Authentication\ResetTokenFactory;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;
use Closure;
use RuntimeException;

final class ResetDeliveryWorkerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testLoopbackDeliverySendsIntentOnceWithoutSensitiveDebugOutput(): void
    {
        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x13", 32);
        $store = new ResetDeliveryIntentStore($this->db, Services::encrypter($config, false));
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x14", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->enqueue(9002, 'loopback@example.invalid', $issued);
        $mailer = new LoopbackResetMailer();
        $worker = new ResetDeliveryWorker($store);

        self::assertSame('sent', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
            static function ($delivery) use ($mailer): void {
                $mailer->send($delivery);
                $mailer->send($delivery);
            },
        ));
        self::assertSame(1, $mailer->count());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $mailer->lastIdempotencyKey());
        self::assertSame('idle', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:02:00+00:00'),
            Closure::fromCallable([$mailer, 'send']),
        ));
        self::assertSame(1, $mailer->count());

        $debug = print_r($mailer, true);
        self::assertStringNotContainsString('loopback@example.invalid', $debug);
        self::assertStringNotContainsString($issued->token(), $debug);
    }

    public function testProviderFailureRetriesWithSameIdempotencyKey(): void
    {
        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x15", 32);
        $store = new ResetDeliveryIntentStore($this->db, Services::encrypter($config, false));
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x16", $length));
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->enqueue(9002, 'retry@example.invalid', $issued);
        $worker = new ResetDeliveryWorker($store);
        $firstKey = '';

        self::assertSame('retry', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
            static function ($delivery) use (&$firstKey): void {
                $firstKey = $delivery->idempotencyKey();

                throw new RuntimeException('synthetic provider timeout');
            },
        ));
        self::assertSame('idle', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:05:59+00:00'),
            static fn (): null => null,
        ));

        $mailer = new LoopbackResetMailer();
        self::assertSame('sent', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:06:00+00:00'),
            Closure::fromCallable([$mailer, 'send']),
        ));
        self::assertSame($firstKey, $mailer->lastIdempotencyKey());
        self::assertSame(1, $mailer->count());
    }
}
