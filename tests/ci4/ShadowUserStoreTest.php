<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use InvalidArgumentException;

final class ShadowUserStoreTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testCreateNormalizesEmailAndOwnsCredentials(): void
    {
        $store = new ShadowUserStore($this->db);
        $userId = $store->create(
            '  USER@example.invalid ',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        self::assertGreaterThan(0, $userId);
        self::assertSame($userId, $store->findActiveIdByEmail('user@example.invalid'));
        self::assertTrue($store->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertFalse($store->verifyPassword($userId, 'wrong'));
        self::assertSame(1, $store->currentSessionVersion($userId));
        self::assertTrue($store->matchesActiveSession($userId, 2, 1, 1));
        self::assertFalse($store->matchesActiveSession($userId, 2, 1, 2));
    }

    public function testBranchScopedRoleRequiresBranch(): void
    {
        $store = new ShadowUserStore($this->db);

        $this->expectException(InvalidArgumentException::class);

        $store->create(
            'operator@example.invalid',
            password_hash('Synthetic passphrase', PASSWORD_DEFAULT),
            2,
            null,
        );
    }

    public function testPasswordReplacementInvalidatesOldPasswordAndSessionVersion(): void
    {
        $store = new ShadowUserStore($this->db);
        $userId = $store->create(
            'viewer@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );

        self::assertTrue($store->replacePasswordAndRevokeSessions(
            $userId,
            password_hash('Synthetic new passphrase', PASSWORD_DEFAULT),
        ));
        self::assertFalse($store->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertTrue($store->verifyPassword($userId, 'Synthetic new passphrase'));
        self::assertSame(2, $store->currentSessionVersion($userId));
        self::assertFalse($store->matchesActiveSession($userId, 3, 7, 1));
        self::assertTrue($store->matchesActiveSession($userId, 3, 7, 2));
    }
}
