<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class TimezoneContractTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testApplicationTimezoneIsBangkok(): void
    {
        // Config source of truth.
        self::assertSame('Asia/Bangkok', app_timezone());
        // Proves CI4 boot applied the config to the PHP runtime, which is what
        // makes date()/DateTimeImmutable('now') produce Bangkok wall-clock.
        self::assertSame('Asia/Bangkok', date_default_timezone_get());
    }

    public function testShadowUserStoreWritesLocalBangkokTimestamp(): void
    {
        $store = new ShadowUserStore($this->db);

        $before = date('Y-m-d H:i:s');
        $userId = $store->create(
            'timezone@example.invalid',
            password_hash('Synthetic passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $after = date('Y-m-d H:i:s');

        self::assertGreaterThan(0, $userId);

        $row = $this->db->table('ci4_users')
            ->select('created_at')
            ->where('email', 'timezone@example.invalid')
            ->get()
            ->getRowArray();

        self::assertIsArray($row);
        $createdAt = (string) $row['created_at'];

        // created_at must sit inside the local (Bangkok) wall-clock window of the
        // call. A gmdate/UTC writer would be 7 hours behind and fall outside it.
        self::assertGreaterThanOrEqual($before, $createdAt);
        self::assertLessThanOrEqual($after, $createdAt);
    }
}
