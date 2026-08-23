<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class PasswordResetMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testResetTokenTableStoresHashAndLifecycleTimestampsOnly(): void
    {
        self::assertTrue($this->db->tableExists('ci4_password_reset_tokens'));

        $fields = $this->db->getFieldNames('ci4_password_reset_tokens');

        self::assertContains('token_hash', $fields);
        self::assertContains('expires_at', $fields);
        self::assertContains('consumed_at', $fields);
        self::assertContains('revoked_at', $fields);
        self::assertNotContains('token', $fields);
        self::assertNotContains('email', $fields);
        self::assertNotContains('password', $fields);
    }
}
