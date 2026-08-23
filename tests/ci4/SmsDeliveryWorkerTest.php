<?php

namespace Tests\Ci4;

use App\Orders\OrderCreationWorkflow;
use App\Orders\SmsDeliveryIntentStore;
use App\Orders\SmsDeliveryWorker;
use App\Orders\LoopbackSmsTransport;
use Closure;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;
use RuntimeException;

final class SmsDeliveryWorkerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    private EncrypterInterface $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $config = new Encryption();
        $config->driver = 'Sodium';
        $config->key = str_repeat("\x61", 32);
        $this->encrypter = Services::encrypter($config, false);
        foreach ([
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER, default_suffix VARCHAR(10)',
            'brand' => 'brand_id INTEGER PRIMARY KEY, brand_details VARCHAR(250)',
            'type' => 'type_id INTEGER PRIMARY KEY, type_details VARCHAR(250)',
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME, trackID VARCHAR(100) UNIQUE, bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerEmail VARCHAR(100), detailTypeId INTEGER, detailBrandId INTEGER, detailNote TEXT, detailImage VARCHAR(250), branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, action_status INTEGER, create_by_user VARCHAR(250)',
            'status_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, order_id VARCHAR(100), action_id INTEGER, update_id INTEGER, cdate DATETIME',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->table('ci4_delivery_intents')->truncate();
        $this->db->table('ci4_order_sequences')->truncate();
        $this->db->resetDataCache();
        $this->db->table('branch')->insert(['branch_id' => 1, 'branch_type' => 1, 'default_suffix' => 'WPA']);
        $this->db->table('brand')->insert(['brand_id' => 1, 'brand_details' => 'BRAND A']);
        $this->db->table('type')->insert(['type_id' => 1, 'type_details' => 'TYPE A']);
    }

    public function testLoopbackSendsOrderSmsOnceWithoutExposingPayload(): void
    {
        $store = $this->seed('a');
        $transport = new LoopbackSmsTransport();
        $worker = new SmsDeliveryWorker($store);
        $now = new DateTimeImmutable('+1 minute');

        self::assertSame('sent', $worker->runNext($now, static function ($delivery) use ($transport): void {
            $transport->send($delivery);
            $transport->send($delivery);
        }));
        self::assertSame(1, $transport->count());
        self::assertSame('idle', $worker->runNext($now, Closure::fromCallable([$transport, 'send'])));
        self::assertSame('sent', $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->get()->getRow('status'));
        $debug = print_r($transport, true);
        self::assertStringNotContainsString('0000000000', $debug);
        self::assertStringNotContainsString('/tracking/', $debug);
    }

    public function testProviderTimeoutRetriesWithStableSmsIdempotencyKey(): void
    {
        $store = $this->seed('b');
        $worker = new SmsDeliveryWorker($store);
        $firstKey = '';
        $now = new DateTimeImmutable('+1 minute');
        self::assertSame('retry', $worker->runNext($now, static function ($delivery) use (&$firstKey): void {
            $firstKey = $delivery->idempotencyKey();
            throw new RuntimeException('synthetic SMS timeout');
        }));
        self::assertSame('idle', $worker->runNext($now->modify('+4 minutes 59 seconds'), static fn (): null => null));
        $transport = new LoopbackSmsTransport();
        self::assertSame('sent', $worker->runNext($now->modify('+5 minutes'), Closure::fromCallable([$transport, 'send'])));
        self::assertSame($firstKey, $transport->lastIdempotencyKey());
        self::assertSame(2, (int) $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->get()->getRow('attempt_count'));
    }

    private function seed(string $marker): SmsDeliveryIntentStore
    {
        (new OrderCreationWorkflow($this->db, $this->encrypter))->create(1, 2, 1, [
            'submission_id' => str_repeat($marker, 32), 'number_id' => '10' . $marker,
            'order_id' => 'ORDER-' . $marker, 'book_id' => 'WPA', 'customer_name' => 'SMS CUSTOMER',
            'customer_tel' => '0000000000', 'customer_email' => 'sms@example.invalid',
            'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => 'Synthetic SMS',
        ], null);

        return new SmsDeliveryIntentStore($this->db, $this->encrypter);
    }
}
