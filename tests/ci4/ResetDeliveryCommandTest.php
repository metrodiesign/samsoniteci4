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

final class ResetDeliveryCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    private EncrypterInterface $encrypter;

    protected function setUp(): void
    {
        parent::setUp();

        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x21", 32);
        $this->encrypter = Services::encrypter($config, false);
        Services::injectMock('encrypter', $this->encrypter);
    }

    public function testLoopbackWorkerProcessesIntentWithoutPrintingRecipientOrToken(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x22", $length));
        $issued  = $factory->issue(new DateTimeImmutable('-1 minute'));
        (new ResetDeliveryIntentStore($this->db, $this->encrypter))->enqueue(
            9003,
            'worker@example.invalid',
            $issued,
        );

        $output = command('reset:delivery-work --transport loopback --limit 10');

        self::assertIsString($output);
        self::assertSame(
            'sent',
            $this->db->table('ci4_delivery_intents')->select('status')->get()->getRow('status'),
        );
    }
}
