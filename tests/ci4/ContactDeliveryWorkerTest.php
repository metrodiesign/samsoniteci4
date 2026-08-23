<?php

namespace Tests\Ci4;

use App\Contact\ContactDeliveryIntentStore;
use App\Contact\ContactDeliveryWorker;
use App\Contact\ContactSubmissionWorkflow;
use App\Contact\LoopbackContactMailer;
use Closure;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;
use RuntimeException;

final class ContactDeliveryWorkerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $name = $this->db->escapeIdentifiers($this->db->prefixTable('contact'));
        $this->db->query("CREATE TABLE IF NOT EXISTS {$name} (id INTEGER PRIMARY KEY AUTOINCREMENT, fullname VARCHAR(128) NOT NULL, email VARCHAR(128) NOT NULL, samsoniteid VARCHAR(100), phone VARCHAR(32) NOT NULL, detail TEXT NOT NULL, cdate DATETIME NOT NULL)");
        $this->db->table('contact')->truncate();
        $this->db->table('ci4_delivery_intents')->truncate();
    }

    public function testLoopbackSendsOnceWithoutExposingContactPayload(): void
    {
        $store = $this->seedIntent('a1');
        $mailer = new LoopbackContactMailer();
        $worker = new ContactDeliveryWorker($store);

        self::assertSame('sent', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
            static function ($delivery) use ($mailer): void {
                $mailer->send($delivery);
                $mailer->send($delivery);
            },
        ));
        self::assertSame(1, $mailer->count());
        self::assertSame('idle', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:02:00+00:00'),
            Closure::fromCallable([$mailer, 'send']),
        ));
        $debug = print_r($mailer, true);
        self::assertStringNotContainsString('wp00c-contact@example.invalid', $debug);
        self::assertStringNotContainsString('SYNTHETIC CONTACT MESSAGE', $debug);
    }

    public function testProviderTimeoutRetriesWithStableIdempotencyKey(): void
    {
        $store = $this->seedIntent('b2');
        $worker = new ContactDeliveryWorker($store);
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
        $mailer = new LoopbackContactMailer();
        self::assertSame('sent', $worker->runNext(
            new DateTimeImmutable('2026-08-22T09:06:00+00:00'),
            Closure::fromCallable([$mailer, 'send']),
        ));
        self::assertSame($firstKey, $mailer->lastIdempotencyKey());
        self::assertSame(1, $mailer->count());
    }

    private function seedIntent(string $suffix): ContactDeliveryIntentStore
    {
        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x31", 32);
        $encrypter = Services::encrypter($config, false);
        (new ContactSubmissionWorkflow($this->db, $encrypter, 'contact@example.invalid'))->submit(
            str_repeat($suffix, 16),
            [
                'fullname' => 'SYNTHETIC CONTACT',
                'email'    => 'wp00c-contact@example.invalid',
                'phone'    => '0000000000',
                'detail'   => 'SYNTHETIC CONTACT MESSAGE',
            ],
            new DateTimeImmutable('2026-08-22T09:00:00+00:00'),
        );

        return new ContactDeliveryIntentStore($this->db, $encrypter);
    }
}
