<?php

namespace Tests\Ci4;

use App\Orders\OrderCreationWorkflow;
use App\Orders\OrderSmsMessages;
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
            'condition' => 'condition_id INTEGER PRIMARY KEY, condition_details VARCHAR(250)',
            'estimateprice' => 'estimateprice_id INTEGER PRIMARY KEY, estimateprice_details VARCHAR(250)',
            'fixed' => 'fixed_id INTEGER PRIMARY KEY, fixed_details VARCHAR(250)',
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME, trackID VARCHAR(100) UNIQUE, bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), customerTel2 VARCHAR(100), customerEmail VARCHAR(100), detailTypeId INTEGER, detailBrandId INTEGER, detailAgent INTEGER, detailSKUName VARCHAR(100), warantyType INTEGER, detailNumberWaranty VARCHAR(100), detailDatePurchase DATETIME, detailCondition VARCHAR(250), detailConditionOther VARCHAR(250), detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250), detailFixed VARCHAR(250), detailFixedOther VARCHAR(250), detailEquipment TEXT, detailNote TEXT, detailImage VARCHAR(250), branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, action_status INTEGER, create_by_user VARCHAR(250)',
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
        $this->db->table('condition')->insert(['condition_id' => 1, 'condition_details' => 'CONDITION A']);
        $this->db->table('estimateprice')->insert(['estimateprice_id' => 1, 'estimateprice_details' => 'ESTIMATE A']);
        $this->db->table('fixed')->insert(['fixed_id' => 1, 'fixed_details' => 'FIXED A']);
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

    public function testCreateEnqueuesThaiTrackingSmsThatDecodes(): void
    {
        $store = $this->seed('c');
        $delivery = $store->reserveNext(new DateTimeImmutable('+1 minute'));

        self::assertNotNull($delivery);
        helper('url');
        self::assertStringStartsWith('หมายเลขการติดตามสถานะสินค้าซ่อม', $delivery->message());
        self::assertStringContainsString(base_url('tracking/' . $delivery->trackId()), $delivery->message());
    }

    public function testEnqueueSkipsSmsWhenTelephoneMalformed(): void
    {
        $store = new SmsDeliveryIntentStore($this->db, $this->encrypter);

        $queued = $store->enqueue(1, 'WPA00000001', '12-345', OrderSmsMessages::created('WPA00000001'), str_repeat('d', 32), new DateTimeImmutable('now'));

        self::assertFalse($queued);
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->where('kind', 'sms')->countAllResults());
    }

    public function testEnqueueSkipsWhenRequestIdAlreadyQueued(): void
    {
        $store = new SmsDeliveryIntentStore($this->db, $this->encrypter);
        $requestId = str_repeat('e', 32);
        $args = [1, 'WPA00000001', '0000000000', OrderSmsMessages::created('WPA00000001'), $requestId, new DateTimeImmutable('now')];

        self::assertTrue($store->enqueue(...$args));
        self::assertFalse($store->enqueue(...$args));
        self::assertSame(1, $this->db->table('ci4_delivery_intents')->where('request_id', $requestId)->countAllResults());
    }

    public function testSmsMessagesMatchCi3Verbatim(): void
    {
        helper('url');
        $trackId = 'WPA00000001';

        self::assertSame(
            'หมายเลขการติดตามสถานะสินค้าซ่อม ' . $trackId . ' ท่านสามารถตรวจสอบสถานะได้ที่ ' . base_url('tracking/' . $trackId),
            OrderSmsMessages::created($trackId),
        );
        self::assertSame(
            'สินค้าซ่อมของท่าน ' . $trackId . ' ส่งคืนมายังสาขาแล้ว สามารถติดต่อรับคืนได้ภายใน 15 วันหลังจากได้รับข้อความนี้',
            OrderSmsMessages::returned($trackId),
        );
        self::assertSame(
            'ขอบคุณที่ใช้บริการกับ Samsonite  แสดงความคิดเห็น ' . base_url('rating/' . $trackId),
            OrderSmsMessages::completed($trackId),
        );
    }

    private function seed(string $marker): SmsDeliveryIntentStore
    {
        (new OrderCreationWorkflow($this->db, $this->encrypter))->create(1, 2, 1, [
            'submission_id' => str_repeat($marker, 32), 'number_id' => '10' . $marker,
            'order_id' => 'ORDER-' . $marker, 'book_id' => 'WPA', 'customer_name' => 'SMS CUSTOMER',
            'customer_tel' => '0000000000', 'customer_email' => 'sms@example.invalid',
            'type_id' => '1', 'brand_id' => '1', 'branch_id' => '1', 'note' => 'Synthetic SMS',
            'detail_sku_name' => 'SMS BAG', 'create_by_user' => 'SMS RECEIVER',
            'condition' => ['1'], 'estimateprice' => ['1'], 'fixed' => ['1'],
        ], []);

        return new SmsDeliveryIntentStore($this->db, $this->encrypter);
    }
}
