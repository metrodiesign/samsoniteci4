<?php

namespace Tests\Ci4;

use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;
use RuntimeException;

final class ResetTokenStoreTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testNewRequestRevokesPreviousToken(): void
    {
        $seed = 0;
        $factory = new ResetTokenFactory(
            static function (int $length) use (&$seed): string {
                $seed++;

                return str_repeat(chr($seed), $length);
            },
        );
        $store = new ResetTokenStore($this->db);

        $first = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->issue(9002, $first);
        self::assertTrue($store->isValid(9002, $first->token(), new DateTimeImmutable('2026-08-22T09:10:00+00:00')));

        $second = $factory->issue(new DateTimeImmutable('2026-08-22T09:11:00+00:00'));
        $store->issue(9002, $second);

        self::assertFalse($store->isValid(9002, $first->token(), new DateTimeImmutable('2026-08-22T09:12:00+00:00')));
        self::assertTrue($store->isValid(9002, $second->token(), new DateTimeImmutable('2026-08-22T09:12:00+00:00')));
    }

    public function testConsumeIsSingleUse(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x05", $length));
        $store   = new ResetTokenStore($this->db);
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->issue(9002, $issued);
        $passwordUpdates = 0;

        $first = $store->consume(
            9002,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
            static function () use (&$passwordUpdates): void {
                $passwordUpdates++;
            },
        );
        $replay = $store->consume(
            9002,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:11:00+00:00'),
            static function () use (&$passwordUpdates): void {
                $passwordUpdates++;
            },
        );

        self::assertTrue($first);
        self::assertFalse($replay);
        self::assertSame(1, $passwordUpdates);
    }

    public function testFailedPasswordUpdateRollsBackTokenConsume(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x06", $length));
        $store   = new ResetTokenStore($this->db);
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->issue(9002, $issued);

        try {
            $store->consume(
                9002,
                $issued->token(),
                new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
                static fn () => throw new RuntimeException('synthetic password update failure'),
            );
            self::fail('Expected password update failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('synthetic password update failure', $exception->getMessage());
        }

        self::assertTrue($store->isValid(
            9002,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:11:00+00:00'),
        ));
    }

    public function testFailedDeliveryIntentRollsBackTokenIssue(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x0a", $length));
        $store   = new ResetTokenStore($this->db, $factory);
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));

        try {
            $store->issue(
                9002,
                $issued,
                static fn () => throw new RuntimeException('synthetic delivery intent failure'),
            );
            self::fail('Expected delivery intent failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('synthetic delivery intent failure', $exception->getMessage());
        }

        self::assertFalse($store->isValid(
            9002,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:01:00+00:00'),
        ));
    }

    public function testExpiredWrongUserAndMalformedTokensAreDenied(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x07", $length));
        $store   = new ResetTokenStore($this->db);
        $issued  = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $store->issue(9002, $issued);

        self::assertFalse($store->isValid(
            9002,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:30:00+00:00'),
        ));
        self::assertFalse($store->isValid(
            9003,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
        ));
        self::assertFalse($store->isValid(
            9002,
            'malformed',
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
        ));
    }
}
