<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class UserHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'tbl_users' => 'userId INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(128) NOT NULL, username VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(128), mobile VARCHAR(20), group_id INTEGER, roleId INTEGER NOT NULL, branch_id INTEGER, branch_type_id INTEGER, isDeleted INTEGER NOT NULL DEFAULT 0, createdBy INTEGER, createdDtm DATETIME NOT NULL, updatedBy INTEGER, updatedDtm DATETIME',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER NOT NULL, branch_user_name VARCHAR(100), branch_name VARCHAR(250) NOT NULL',
            'book' => 'book_id INTEGER PRIMARY KEY, branch_id INTEGER NOT NULL, book_detail VARCHAR(3) NOT NULL, status INTEGER NOT NULL',
            'tbl_last_login' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, userId INTEGER NOT NULL, sessionData VARCHAR(2048) NOT NULL, machineIp VARCHAR(1024) NOT NULL, userAgent VARCHAR(128) NOT NULL, agentString VARCHAR(1024) NOT NULL, platform VARCHAR(128) NOT NULL, createdDtm DATETIME NOT NULL',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->table('ci4_users')->truncate();
        $this->db->resetDataCache();
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'branch-a', 'branch_name' => 'BRANCH A'],
            ['branch_id' => 2, 'branch_type' => 2, 'branch_user_name' => 'branch-b', 'branch_name' => 'BRANCH B'],
        ]);
        $this->db->table('book')->insertBatch([
            ['book_id' => 1, 'branch_id' => 1, 'book_detail' => 'BKA', 'status' => 1],
            ['book_id' => 2, 'branch_id' => 2, 'book_detail' => 'BKB', 'status' => 1],
        ]);
        $this->seedUser(9001, 'admin', 'admin@example.invalid', 1, null, 1, 'ADMIN');
        $this->seedUser(9002, 'operator-a', 'a@example.invalid', 2, 1, 4, 'OPERATOR A');
        $this->seedUser(9003, 'operator-b', 'b@example.invalid', 3, 2, 4, 'OPERATOR B');
    }

    public function testUserCrudScopesListingsAndSoftDeletesBothStores(): void
    {
        $admin = $this->withSession($this->session(9001, 1, null, 1))->get('/users');
        $admin->assertStatus(200);
        $admin->assertSee('OPERATOR A');
        $admin->assertSee('OPERATOR B');

        $operator = $this->withSession($this->session(9002, 2, 1, 4))->get('/users');
        $operator->assertStatus(200);
        $operator->assertSee('OPERATOR A');
        $operator->assertDontSee('OPERATOR B');

        $payload = [
            'username' => 'new-operator', 'name' => 'NEW OPERATOR', 'email' => 'new@example.invalid',
            'mobile' => '0000000000', 'group_id' => '4', 'role_id' => '2', 'branch_id' => '1',
            'password' => 'Synthetic passphrase', 'password_confirmation' => 'Synthetic passphrase',
        ];
        $this->postAs(9001, 1, null, 1, '/users', $payload)->assertRedirectTo('/users');
        $legacy = $this->db->table('tbl_users')->where('email', 'new@example.invalid')->get()->getRowArray();
        self::assertNotNull($legacy);
        self::assertTrue(password_verify('Synthetic passphrase', (string) $legacy['password']));
        self::assertSame(1, $this->db->table('ci4_users')->where('id', $legacy['userId'])->where('is_active', 1)->countAllResults());

        unset($payload['password'], $payload['password_confirmation']);
        $payload['name'] = 'UPDATED OPERATOR';
        $this->postAs(9001, 1, null, 1, '/users/' . $legacy['userId'], $payload)->assertRedirectTo('/users');
        self::assertSame('UPDATED OPERATOR', $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('name'));
        self::assertSame('UPDATED OPERATOR', $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('display_name'));

        $this->postAs(9001, 1, null, 1, '/users/' . $legacy['userId'] . '/delete', [])->assertStatus(204);
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('isDeleted'));
        self::assertSame(0, (int) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('is_active'));
    }

    public function testUserValidationDuplicateEmailAndExistenceContractHaveNoWrites(): void
    {
        $valid = [
            'username' => 'validation-user', 'name' => 'VALIDATION USER', 'email' => 'validation@example.invalid',
            'mobile' => '0000000000', 'group_id' => '4', 'role_id' => '2', 'branch_id' => '1',
            'password' => 'Synthetic passphrase', 'password_confirmation' => 'Synthetic passphrase',
        ];
        foreach ([
            [...$valid, 'name' => ''],
            [...$valid, 'email' => 'a@example.invalid'],
            [...$valid, 'mobile' => '123'],
            [...$valid, 'password_confirmation' => 'Different passphrase'],
        ] as $index => $payload) {
            $result = $this->postAs(9001, 1, null, 1, '/users', $payload);
            $result->assertStatus($index === 1 ? 409 : 422);
            self::assertSame(3, $this->db->table('tbl_users')->countAllResults());
            self::assertSame(3, $this->db->table('ci4_users')->countAllResults());
        }

        $exists = $this->withSession($this->session(9001, 1, null, 1))
            ->get('/users/email-exists?email=a%40example.invalid');
        $exists->assertStatus(200);
        $exists->assertJSONExact(['exists' => true]);
        $available = $this->withSession($this->session(9001, 1, null, 1))
            ->get('/users/email-exists?email=available%40example.invalid');
        $available->assertJSONExact(['exists' => false]);
    }

    public function testChangePasswordRejectsWrongAndSameThenRevokesOtherSessionsOnSuccess(): void
    {
        $this->withSession($this->session(9002, 2, 1, 4))->get('/change-password')->assertStatus(200);
        $before = (string) $this->db->table('ci4_users')->where('id', 9002)->get()->getRow('password_hash');

        $wrong = $this->postAs(9002, 2, 1, 4, '/change-password', [
            'current_password' => 'Wrong passphrase',
            'password' => 'Replacement passphrase',
            'password_confirmation' => 'Replacement passphrase',
        ]);
        $wrong->assertStatus(422);
        self::assertSame($before, $this->db->table('ci4_users')->where('id', 9002)->get()->getRow('password_hash'));

        $same = $this->postAs(9002, 2, 1, 4, '/change-password', [
            'current_password' => 'Synthetic passphrase',
            'password' => 'Synthetic passphrase',
            'password_confirmation' => 'Synthetic passphrase',
        ]);
        $same->assertStatus(409);
        self::assertSame($before, $this->db->table('ci4_users')->where('id', 9002)->get()->getRow('password_hash'));

        $changed = $this->postAs(9002, 2, 1, 4, '/change-password', [
            'current_password' => 'Synthetic passphrase',
            'password' => 'Replacement passphrase',
            'password_confirmation' => 'Replacement passphrase',
        ]);
        $changed->assertRedirectTo('/change-password?changed=1');
        $shadow = $this->db->table('ci4_users')->where('id', 9002)->get()->getRowArray();
        $legacy = $this->db->table('tbl_users')->where('userId', 9002)->get()->getRowArray();
        self::assertTrue(password_verify('Replacement passphrase', (string) $shadow['password_hash']));
        self::assertTrue(password_verify('Replacement passphrase', (string) $legacy['password']));
        self::assertSame(2, (int) $shadow['session_version']);
        self::assertSame(2, service('session')->get('sessionVersion'));
    }

    public function testLoginHistoryAndBranchBookJsonAreEscapedAndBranchScoped(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $this->db->table('tbl_last_login')->insert([
                'userId' => 9002, 'sessionData' => '{}', 'machineIp' => '127.0.0.' . $index,
                'userAgent' => 'Browser', 'agentString' => 'HIST-' . $index . '<script>', 'platform' => 'Test',
                'createdDtm' => sprintf('2026-08-%02d 09:00:00', $index),
            ]);
        }
        $history = $this->withSession($this->session(9002, 2, 1, 4))->get('/users/9002/history');
        $history->assertStatus(200);
        $history->assertSee('HIST-6&lt;script&gt;');
        $history->assertDontSee('HIST-1&lt;script&gt;');
        $history->assertDontSee('HIST-6<script>');
        $this->db->table('tbl_last_login')->insert([
            'userId' => 9003, 'sessionData' => '{}', 'machineIp' => '127.0.0.9',
            'userAgent' => 'Browser', 'agentString' => 'OTHER-USER-HISTORY', 'platform' => 'Test',
            'createdDtm' => '2026-08-09 09:00:00',
        ]);
        $ownHistory = $this->withSession($this->session(9002, 2, 1, 4))->get('/login-history');
        $ownHistory->assertStatus(200);
        $ownHistory->assertSee('HIST-6&lt;script&gt;');
        $ownHistory->assertDontSee('OTHER-USER-HISTORY');

        $adminBranches = $this->withSession($this->session(9001, 1, null, 1))->get('/api/branches');
        $adminBranches->assertStatus(200);
        $adminBranches->assertJSONExact(['branches' => [
            ['id' => 1, 'name' => 'BRANCH A'],
            ['id' => 2, 'name' => 'BRANCH B'],
        ]]);
        $operatorBranches = $this->withSession($this->session(9002, 2, 1, 4))->get('/api/branches');
        $operatorBranches->assertJSONExact(['branches' => [['id' => 1, 'name' => 'BRANCH A']]]);

        $books = $this->withSession($this->session(9002, 2, 1, 4))->get('/api/books?branch_id=1');
        $books->assertJSONExact(['books' => [['id' => 1, 'code' => 'BKA']]]);
        $this->withSession($this->session(9002, 2, 1, 4))->get('/api/books?branch_id=2')->assertStatus(404);
    }

    public function testUserRoutesDenyAnonymousViewerEscalationAndCrossBranchMutation(): void
    {
        $this->get('/users')->assertStatus(401);
        $viewerWrite = $this->postAs(9003, 3, 2, 4, '/users', [
            'username' => 'blocked', 'name' => 'BLOCKED', 'email' => 'blocked@example.invalid',
            'mobile' => '0000000000', 'group_id' => '4', 'role_id' => '3', 'branch_id' => '2',
            'password' => 'Synthetic passphrase', 'password_confirmation' => 'Synthetic passphrase',
        ]);
        $viewerWrite->assertStatus(403);

        try {
            $this->withSession($this->session(9002, 2, 1, 4))->get('/users/9003');
            self::fail('Expected cross-branch read denial.');
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
        }
        $cross = [
            'username' => 'operator-b', 'name' => 'HACKED', 'email' => 'b@example.invalid',
            'mobile' => '0000000000', 'group_id' => '4', 'role_id' => '2', 'branch_id' => '2',
        ];
        $this->postAs(9002, 2, 1, 4, '/users/9003', $cross)->assertStatus(404);
        $this->postAs(9002, 2, 1, 4, '/users/9003/delete', [])->assertStatus(404);
        self::assertSame('OPERATOR B', $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('name'));
        self::assertSame(0, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
        $ownEmail = $this->withSession($this->session(9002, 2, 1, 4))
            ->get('/users/email-exists?email=a%40example.invalid');
        $ownEmail->assertJSONExact(['exists' => true]);
        $crossBranchEmail = $this->withSession($this->session(9002, 2, 1, 4))
            ->get('/users/email-exists?email=b%40example.invalid');
        $crossBranchEmail->assertJSONExact(['exists' => false]);

        $escalate = [
            'username' => 'escalate', 'name' => 'ESCALATE', 'email' => 'escalate@example.invalid',
            'mobile' => '0000000000', 'group_id' => '1', 'role_id' => '1', 'branch_id' => '',
            'password' => 'Synthetic passphrase', 'password_confirmation' => 'Synthetic passphrase',
        ];
        $this->postAs(9002, 2, 1, 4, '/users', $escalate)->assertStatus(422);
        self::assertSame(3, $this->db->table('tbl_users')->countAllResults());
    }

    /** @param array<string, string> $payload */
    private function postAs(int $id, int $role, ?int $branch, int $group, string $path, array $payload)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session($id, $role, $branch, $group))->post($path, $payload);
    }

    /** @return array<string, int|bool|null|string> */
    private function session(int $id, int $role, ?int $branch, int $group): array
    {
        return [
            'userId' => $id, 'role' => $role, 'GroupID' => $group, 'BranchID' => $branch,
            'name' => 'Synthetic', 'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
    }

    private function seedUser(int $id, string $username, string $email, int $role, ?int $branch, int $group, string $name): void
    {
        $hash = password_hash('Synthetic passphrase', PASSWORD_DEFAULT);
        $this->db->table('tbl_users')->insert([
            'userId' => $id, 'email' => $email, 'username' => $username, 'password' => $hash,
            'name' => $name, 'mobile' => '0000000000', 'group_id' => $group, 'roleId' => $role,
            'branch_id' => $branch, 'branch_type_id' => $branch, 'isDeleted' => 0,
            'createdBy' => 9001, 'createdDtm' => '2026-08-22 09:00:00',
        ]);
        (new ShadowUserStore($this->db))->synchronizeLegacyUser(
            $id, $email, $hash, $role, $branch, $username, $name, $group,
            $role === 1 ? 'Administrator' : 'Operator', true,
        );
    }
}
