<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ResetDeliveryIntentMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testDeliveryIntentStoresEncryptedPayloadAndRetryState(): void
    {
        self::assertTrue($this->db->tableExists('ci4_delivery_intents'));

        $fields = $this->db->getFieldNames('ci4_delivery_intents');

        self::assertContains('idempotency_key', $fields);
        self::assertContains('payload_ciphertext', $fields);
        self::assertContains('status', $fields);
        self::assertContains('attempt_count', $fields);
        self::assertContains('available_at', $fields);
        self::assertNotContains('email', $fields);
        self::assertNotContains('token', $fields);
        self::assertNotContains('password', $fields);
    }
}
