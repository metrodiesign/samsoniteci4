<?php

namespace Tests\Ci4;

use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetRequestWorkflow;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Authentication\ShadowUserStore;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;

final class ResetRequestWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testKnownAccountCreatesUsableTokenAndEncryptedDeliveryIntent(): void
    {
        $factory  = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x0d", $length));
        $encrypter = $this->encrypter();
        $users    = new ShadowUserStore($this->db);
        $userId   = $users->create(
            'known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        (new ResetRequestWorkflow($this->db, $encrypter, $factory))->request(
            '  KNOWN@example.invalid ',
            new DateTimeImmutable('2026-08-22T09:00:00+00:00'),
        );

        $delivery = (new ResetDeliveryIntentStore($this->db, $encrypter))->reserveNext(
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
        );

        self::assertNotNull($delivery);
        self::assertSame('known@example.invalid', $delivery->recipient());
        self::assertTrue((new ResetTokenStore($this->db, $factory))->isValid(
            $userId,
            $delivery->token(),
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
        ));
    }

    public function testUnknownAccountCreatesNoDeliveryIntent(): void
    {
        $factory   = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x0e", $length));
        $encrypter = $this->encrypter();

        (new ResetRequestWorkflow($this->db, $encrypter, $factory))->request(
            'unknown@example.invalid',
            new DateTimeImmutable('2026-08-22T09:00:00+00:00'),
        );

        self::assertNull((new ResetDeliveryIntentStore($this->db, $encrypter))->reserveNext(
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
        ));
    }

    public function testNewRequestSupersedesPreviousTokenAndPendingDelivery(): void
    {
        $seed = 0;
        $factory = new ResetTokenFactory(
            static function (int $length) use (&$seed): string {
                $seed++;

                return str_repeat(chr($seed), $length);
            },
        );
        $encrypter = $this->encrypter();
        $users     = new ShadowUserStore($this->db);
        $userId    = $users->create(
            'newest@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $workflow = new ResetRequestWorkflow($this->db, $encrypter, $factory);

        $workflow->request('newest@example.invalid', new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $workflow->request('newest@example.invalid', new DateTimeImmutable('2026-08-22T09:01:00+00:00'));

        $deliveryStore = new ResetDeliveryIntentStore($this->db, $encrypter);
        $delivery = $deliveryStore->reserveNext(new DateTimeImmutable('2026-08-22T09:02:00+00:00'));
        $firstToken  = bin2hex(str_repeat("\x01", 32));
        $newestToken = bin2hex(str_repeat("\x03", 32));

        self::assertNotNull($delivery);
        self::assertSame($newestToken, $delivery->token());
        self::assertNull($deliveryStore->reserveNext(new DateTimeImmutable('2026-08-22T09:02:00+00:00')));
        self::assertFalse((new ResetTokenStore($this->db, $factory))->isValid(
            $userId,
            $firstToken,
            new DateTimeImmutable('2026-08-22T09:02:00+00:00'),
        ));
        self::assertTrue((new ResetTokenStore($this->db, $factory))->isValid(
            $userId,
            $newestToken,
            new DateTimeImmutable('2026-08-22T09:02:00+00:00'),
        ));
    }

    private function encrypter(): EncrypterInterface
    {
        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x0f", 32);

        return Services::encrypter($config, false);
    }
}
