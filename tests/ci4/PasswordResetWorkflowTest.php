<?php

namespace Tests\Ci4;

use App\Authentication\PasswordResetWorkflow;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;

final class PasswordResetWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testSuccessfulResetConsumesTokenChangesPasswordAndRevokesSessions(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x08", $length));
        $users   = new ShadowUserStore($this->db);
        $tokens  = new ResetTokenStore($this->db, $factory);
        $userId  = $users->create(
            'reset@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $issued = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $tokens->issue($userId, $issued);
        $workflow = new PasswordResetWorkflow($this->db, $factory);

        self::assertTrue($workflow->reset(
            'RESET@example.invalid',
            $issued->token(),
            'Synthetic new passphrase',
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
        ));
        self::assertFalse($users->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertTrue($users->verifyPassword($userId, 'Synthetic new passphrase'));
        self::assertSame(2, $users->currentSessionVersion($userId));

        self::assertFalse($workflow->reset(
            'reset@example.invalid',
            $issued->token(),
            'Synthetic replay passphrase',
            new DateTimeImmutable('2026-08-22T09:11:00+00:00'),
        ));
        self::assertTrue($users->verifyPassword($userId, 'Synthetic new passphrase'));
        self::assertSame(2, $users->currentSessionVersion($userId));
    }

    public function testWrongEmailDeniesWithoutConsumingTokenOrChangingPassword(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x09", $length));
        $users   = new ShadowUserStore($this->db);
        $tokens  = new ResetTokenStore($this->db, $factory);
        $userId  = $users->create(
            'known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $issued = $factory->issue(new DateTimeImmutable('2026-08-22T09:00:00+00:00'));
        $tokens->issue($userId, $issued);

        self::assertFalse((new PasswordResetWorkflow($this->db, $factory))->reset(
            'wrong@example.invalid',
            $issued->token(),
            'Synthetic new passphrase',
            new DateTimeImmutable('2026-08-22T09:10:00+00:00'),
        ));
        self::assertTrue($users->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertSame(1, $users->currentSessionVersion($userId));
        self::assertTrue($tokens->isValid(
            $userId,
            $issued->token(),
            new DateTimeImmutable('2026-08-22T09:11:00+00:00'),
        ));
    }
}
