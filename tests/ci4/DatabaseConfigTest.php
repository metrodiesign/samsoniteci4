<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class DatabaseConfigTest extends CIUnitTestCase
{
    public function testEveryApplicationConnectionGroupUsesMysqliUtf8mb4(): void
    {
        $config = new Database();
        $groups = [];
        foreach (get_object_vars($config) as $name => $value) {
            if (! is_array($value) || ! array_key_exists('DBDriver', $value)) {
                continue;
            }
            $groups[] = $name;
            if ($name === 'tests') {
                self::assertSame('SQLite3', $value['DBDriver']);
                continue;
            }
            self::assertSame('MySQLi', $value['DBDriver'], "group {$name} drifted off MySQLi");
            self::assertSame('utf8mb4', $value['charset'], "group {$name} charset drifted");
            self::assertSame('utf8mb4_general_ci', $value['DBCollat'], "group {$name} collation drifted");
        }
        self::assertContains('default', $groups);
        self::assertContains('tests', $groups);
    }

    public function testDbDebugFollowsEnvironment(): void
    {
        $config = new Database();
        self::assertSame(ENVIRONMENT !== 'production', $config->default['DBDebug']);
        self::assertTrue($config->tests['DBDebug']);
    }
}
