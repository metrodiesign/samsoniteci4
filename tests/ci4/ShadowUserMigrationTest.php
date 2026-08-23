<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ShadowUserMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';

    public function testShadowUserTableOwnsAuthenticationAndSessionVersion(): void
    {
        self::assertTrue($this->db->tableExists('ci4_users'));

        $fields = $this->db->getFieldNames('ci4_users');

        self::assertContains('email', $fields);
        self::assertContains('password_hash', $fields);
        self::assertContains('role_id', $fields);
        self::assertContains('branch_id', $fields);
        self::assertContains('is_active', $fields);
        self::assertContains('session_version', $fields);
        self::assertNotContains('password', $fields);
    }
}
