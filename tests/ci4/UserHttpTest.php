<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
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
            'tbl_roles' => 'roleId INTEGER PRIMARY KEY, role VARCHAR(64) NOT NULL',
            'group_menu' => 'id INTEGER PRIMARY KEY, group_type VARCHAR(250) NOT NULL, name VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'branch_type' => 'branch_type_id INTEGER PRIMARY KEY, branch_type_details VARCHAR(250) NOT NULL',
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
        $this->db->table('tbl_roles')->insertBatch([
            ['roleId' => 1, 'role' => 'Administrator'],
            ['roleId' => 2, 'role' => 'Operator'],
            ['roleId' => 3, 'role' => 'Viewer'],
        ]);
        $this->db->table('group_menu')->insertBatch([
            ['id' => 1, 'group_type' => '1,2', 'name' => 'CENTRAL', 'cdate' => '2026-08-22 09:00:00'],
            ['id' => 4, 'group_type' => '1,3', 'name' => 'BRANCH', 'cdate' => '2026-08-22 09:00:00'],
        ]);
        $this->db->table('branch_type')->insertBatch([
            ['branch_type_id' => 1, 'branch_type_details' => 'TYPE A'],
            ['branch_type_id' => 2, 'branch_type_details' => 'TYPE B'],
        ]);
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

        $legacyHash = (string) $legacy['password'];
        $shadowHash = (string) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('password_hash');
        unset($payload['password'], $payload['password_confirmation']);
        $payload['name'] = 'UPDATED OPERATOR';
        $this->postAs(9001, 1, null, 1, '/users/' . $legacy['userId'], $payload)->assertRedirectTo('/users');
        self::assertSame('UPDATED OPERATOR', $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('name'));
        self::assertSame('UPDATED OPERATOR', $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('display_name'));
        self::assertSame($legacyHash, $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('password'));
        self::assertSame($shadowHash, $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('password_hash'));
        self::assertSame(1, (int) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('session_version'));

        $payload['password'] = 'Replacement passphrase';
        $payload['password_confirmation'] = 'Replacement passphrase';
        $this->postAs(9001, 1, null, 1, '/users/' . $legacy['userId'], $payload)->assertRedirectTo('/users');
        self::assertTrue(password_verify('Replacement passphrase', (string) $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('password')));
        self::assertTrue(password_verify('Replacement passphrase', (string) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('password_hash')));
        self::assertSame(2, (int) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('session_version'));

        $this->postAs(9001, 1, null, 1, '/users/' . $legacy['userId'] . '/delete', [])->assertStatus(204);
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', $legacy['userId'])->get()->getRow('isDeleted'));
        self::assertSame(0, (int) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('is_active'));
        self::assertSame(3, (int) $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('session_version'));
    }

    public function testUserListingSearchMatchesMobileAndDropsUsername(): void
    {
        $admin = $this->session(9001, 1, null, 1);

        // AC-5: mobile is searchable.
        $byMobile = $this->withSession($admin)->get('/users?search=' . rawurlencode('0000000000'));
        $byMobile->assertStatus(200);
        $byMobile->assertSee('OPERATOR A');

        // AC-5: username is no longer searchable.
        $byUsername = $this->withSession($admin)->get('/users?search=' . rawurlencode('operator-a'));
        $byUsername->assertStatus(200);
        $byUsername->assertDontSee('OPERATOR A');
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
        $changePage = $this->withSession($this->session(9002, 2, 1, 4))->get('/change-password');
        $changePage->assertStatus(200);
        self::assertStringContainsString('type="reset"', $changePage->getBody()); // t5 AC-6
        foreach ([
            'class="background-form"',
            'action="http://example.invalid/changePassword" method="post"',
            'id="inputOldPassword"',
            'name="oldPassword"',
            'id="inputPassword1"',
            'name="newPassword"',
            'id="inputPassword2"',
            'name="cNewPassword"',
        ] as $contract) {
            self::assertStringContainsString($contract, $changePage->getBody(), $contract);
        }
        $legacyPage = $this->withSession($this->session(9002, 2, 1, 4))->get('/loadChangePass');
        $legacyPage->assertStatus(200);
        self::assertStringContainsString(
            'action="http://example.invalid/changePassword" method="post"',
            (string) $legacyPage->getBody(),
        );
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

        $legacy = $this->postAs(9002, 2, 1, 4, '/changePassword', [
            'oldPassword' => 'Replacement passphrase',
            'newPassword' => 'Another replacement passphrase',
            'cNewPassword' => 'Another replacement passphrase',
        ], 2);
        $legacy->assertRedirectTo('/loadChangePass?changed=1');
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

    public function testLoginHistoryCarriesCi3ColumnsSearchAndNumberedPager(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $this->db->table('tbl_last_login')->insert([
                'userId' => 9002, 'sessionData' => '{"role":2,"BranchID":1}', 'machineIp' => '127.0.0.' . $index,
                'userAgent' => 'Browser', 'agentString' => 'HIST-' . $index, 'platform' => 'Test',
                'createdDtm' => sprintf('2026-08-%02d 09:00:00', $index),
            ]);
        }
        $body = (string) $this->withSession($this->session(9002, 2, 1, 4))->get('/users/9002/history')->getBody();
        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // CI3 column set, including the Session Data column CI4 used to drop entirely.
        foreach (['Session Data', 'IP Address', 'User Agent', 'Agent Full String', 'Platform', 'Date-Time'] as $header) {
            self::assertMatchesRegularExpression('/<th>\s*' . preg_quote($header, '/') . '\s*<\/th>/s', $decoded, $header);
        }
        self::assertMatchesRegularExpression('/<td>\s*\{"role":2,"BranchID":1\}\s*<\/td>/s', $decoded);
        self::assertStringContainsString('name="searchText"', $body);
        // 6 rows over a page size of 5 gives CI3's two numbered links plus Next.
        self::assertStringContainsString('/login-history/9002/5', $body);
        self::assertStringContainsString('>Next</a>', $body);

        $filtered = (string) $this->withSession($this->session(9002, 2, 1, 4))
            ->get('/users/9002/history/1?searchText=127.0.0.3')
            ->getBody();
        self::assertStringContainsString('HIST-3', $filtered);
        self::assertStringNotContainsString('HIST-6', $filtered);
        // A filter that narrows to one page must drop the pager with it.
        self::assertStringNotContainsString('>Next</a>', $filtered);

    }

    public function testUserRoutesDenyAnonymousViewerEscalationAndCrossBranchMutation(): void
    {
        $this->get('/users')->assertStatus(401);
        // CI3 parity: role 3 may write, but only inside its own branch — a
        // cross-branch create (viewer in branch 2, target branch 1) must fail.
        $viewerWrite = $this->postAs(9003, 3, 2, 4, '/users', [
            'username' => 'blocked', 'name' => 'BLOCKED', 'email' => 'blocked@example.invalid',
            'mobile' => '0000000000', 'group_id' => '4', 'role_id' => '3', 'branch_id' => '1',
            'password' => 'Synthetic passphrase', 'password_confirmation' => 'Synthetic passphrase',
        ]);
        $viewerWrite->assertStatus(422);

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

    public function testUserListingUsesCi3TableRoleLabelsAndEscapesEveryColumn(): void
    {
        $this->db->table('tbl_roles')->where('roleId', 3)->update(['role' => '<img src=x onerror=alert(1)>']);
        $this->db->table('tbl_users')->where('userId', 9003)->update([
            'name' => '<script>alert(1)</script>',
            'email' => '<svg onload=alert(1)>',
            'mobile' => '<b>mobile</b>',
        ]);
        $this->db->table('tbl_users')->insert([
            'userId' => 9010, 'email' => 'missing-role@example.invalid', 'username' => 'missing-role',
            'password' => password_hash('Synthetic passphrase', PASSWORD_DEFAULT), 'name' => 'MISSING ROLE',
            'mobile' => 'not-a-phone', 'group_id' => 4, 'roleId' => 99, 'branch_id' => 1,
            'branch_type_id' => 1, 'isDeleted' => 0, 'createdBy' => 9001,
            'createdDtm' => '2026-08-22 09:00:00',
        ]);

        $page = $this->withSession($this->session(9001, 1, null, 1))->get('/users');
        $page->assertStatus(200);
        $body = $page->getBody();
        self::assertStringContainsString('href="http://example.invalid/addNew"', $body);
        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (['No', 'Name', 'Email', 'Mobile', 'Role', 'Actions'] as $heading) {
            self::assertMatchesRegularExpression('/<th(?: class="text-center")?>\s*' . preg_quote($heading, '/') . '\s*<\/th>/s', $decoded);
        }
        self::assertStringNotContainsString('id="user-username"', $body);
        self::assertStringNotContainsString('name="password"', $body);
        self::assertMatchesRegularExpression('/<td>\s*Administrator\s*<\/td>/s', $body);
        self::assertMatchesRegularExpression('/<td>\s*Operator\s*<\/td>/s', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        self::assertStringContainsString('&lt;svg onload=alert(1)&gt;', $body);
        self::assertStringContainsString('&lt;b&gt;mobile&lt;/b&gt;', $body);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('<svg onload=alert(1)>', $body);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);
        self::assertMatchesRegularExpression('/<td>MISSING ROLE<\/td>\s*<td>missing-role@example\.invalid<\/td>\s*<td>not-a-phone<\/td>\s*<td><\/td>/', $body);
        self::assertStringNotContainsString('<td>99</td>', $body);
    }

    public function testUserListingHandlesZeroOneAndOneHundredRowsWithNormalizedSearch(): void
    {
        $operator = $this->withSession($this->session(9002, 2, 1, 4))->get('/users');
        $operator->assertStatus(200);
        self::assertSame(1, substr_count($operator->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertStringContainsString('<td>1</td>', $operator->getBody());

        $this->db->table('tbl_users')->update(['isDeleted' => 1]);
        $empty = $this->withSession($this->session(9001, 1, null, 1))->get('/users');
        $empty->assertStatus(200);
        self::assertSame(0, substr_count($empty->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertMatchesRegularExpression('/<th>\s*No\s*<\/th>/s', html_entity_decode((string) $empty->getBody()));

        $rows = [];
        $hash = password_hash('Synthetic passphrase', PASSWORD_DEFAULT);
        for ($number = 1; $number <= 100; $number++) {
            $rows[] = [
                'userId' => 10000 + $number, 'email' => sprintf('user-%03d@example.invalid', $number),
                'username' => sprintf('user-%03d', $number), 'password' => $hash,
                'name' => sprintf('USER-%03d', $number), 'mobile' => sprintf('000000%04d', $number),
                'group_id' => 4, 'roleId' => 2, 'branch_id' => 1, 'branch_type_id' => 1,
                'isDeleted' => 0, 'createdBy' => 9001, 'createdDtm' => '2026-08-22 09:00:00',
            ];
        }
        $this->db->table('tbl_users')->insertBatch($rows);

        $full = $this->withSession($this->session(9001, 1, null, 1))->get('/users');
        $full->assertStatus(200);
        self::assertSame(100, substr_count($full->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        preg_match_all('/<td>([0-9]+)<\/td>\s*<td>USER-[0-9]{3}<\/td>/', $full->getBody(), $matches);
        self::assertSame(array_map('strval', range(1, 100)), $matches[1]);

        $found = $this->withSession($this->session(9001, 1, null, 1))->get('/users?search=%20USER-050%20');
        self::assertSame(1, substr_count($found->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        $found->assertSee('USER-050');
        $found->assertDontSee('USER-049');

        $missing = $this->withSession($this->session(9001, 1, null, 1))->get('/users?search=NO-SUCH-USER');
        self::assertSame(0, substr_count($missing->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));

        $invalid = $this->withSession($this->session(9001, 1, null, 1))->get('/users?search=' . str_repeat('x', 129));
        self::assertSame(100, substr_count($invalid->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertStringContainsString('name="searchText" value=""', $invalid->getBody());
    }

    public function testUserAddAndEditFormsAreSeparatedAndAvailableToEveryWritableRole(): void
    {
        $this->get('/users/new')->assertStatus(401);

        foreach ([
            [$this->session(9001, 1, null, 1), 9002, 'OPERATOR A'],
            [$this->session(9002, 2, 1, 4), 9002, 'OPERATOR A'],
            [$this->session(9003, 3, 2, 4), 9003, 'OPERATOR B'],
        ] as [$session, $targetId, $targetName]) {
            $add = $this->withSession($session)->get('/users/new');
            $add->assertStatus(200);
            $addBody = (string) $add->getBody();
            self::assertStringContainsString(
                '<form role="form" id="addUser" action="http://example.invalid/addNewUser" method="post"',
                $addBody,
            );
            self::assertStringNotContainsString('name="username"', $addBody);
            foreach (['fname', 'email', 'mobile', 'password', 'cpassword'] as $field) {
                self::assertStringContainsString('name="' . $field . '"', $addBody);
            }
            self::assertStringContainsString('id="role" name="role"', $addBody);
            if ($session['BranchID'] === null) {
                foreach (['group_id', 'branch_type', 'branch_id'] as $field) {
                    self::assertStringContainsString('name="' . $field . '"', $addBody);
                }
            } else {
                self::assertStringNotContainsString('name="group_id"', $addBody);
                self::assertStringNotContainsString('name="branch_type"', $addBody);
                self::assertStringNotContainsString('name="branch_id"', $addBody);
            }
            self::assertStringContainsString('type="reset" class="btn btn-default" value="Reset"', $addBody);
            self::assertStringContainsString('type="submit" class="btn btn-primary" value="Submit"', $addBody);
            self::assertStringNotContainsString('<table', $addBody);
            self::assertStringNotContainsString('class="deleteUser"', $addBody);

            $edit = $this->withSession($session)->get('/users/' . $targetId);
            $edit->assertStatus(200);
            $editBody = (string) $edit->getBody();
            self::assertStringContainsString(
                '<form role="form" action="http://example.invalid/editUser" method="post" id="editUser"',
                $editBody,
            );
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*name="fname")(?=[^>]*value="' . preg_quote($targetName, '/') . '")[^>]*>/s',
                $editBody,
            );
            self::assertStringContainsString('value="' . $targetId . '" name="userId" id="userId"', $editBody);
            self::assertStringContainsString('name="password"', $editBody);
            self::assertStringNotContainsString('name="password" required', $editBody);
            self::assertStringNotContainsString('<table', $editBody);
            self::assertStringNotContainsString('class="deleteUser"', $editBody);
        }
    }

    public function testUserDeleteMarkupUsesThePinnedCi3AnchorAndCommonJsContract(): void
    {
        $page = $this->withSession($this->session(9001, 1, null, 1))->get('/users');
        $page->assertStatus(200);
        $body = (string) $page->getBody();
        self::assertSame(3, substr_count($body, 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertSame(3, substr_count($body, 'data-userid="'));
        self::assertStringContainsString('assets/js/common.js', $body);
        self::assertStringNotContainsString('class="user-delete"', $body);
        self::assertStringNotContainsString('<form class="user-delete"', $body);
        self::assertStringNotContainsString('fetch(form.action', $body);

        $common = (string) file_get_contents(PUBLICPATH . 'assets/js/common.js');
        foreach ([
            'jQuery(document).on("click", ".deleteUser"',
            '$(this).data("userid")',
            'hitURL = baseURL + "deleteUser"',
            'confirm("Are you sure to delete this user ?")',
            'data : { userId : userId }',
            "currentRow.parents('tr').remove();",
        ] as $sourceContract) {
            self::assertStringContainsString($sourceContract, $common);
        }
    }

    public function testUserDeleteRefreshesCsrfBetweenConsecutiveRequests(): void
    {
        $session = $this->session(9001, 1, null, 1);
        $page = $this->withSession($session)->get('/users');
        self::assertSame(1, preg_match('/name="csrf_test_name" value="([^"]+)"/', $page->getBody(), $matches));
        $renderedToken = $matches[1];

        $first = $this->withSession($session)->post('/users/9002/delete', ['csrf_test_name' => $renderedToken]);
        $first->assertStatus(204);
        $nextToken = $first->response()->getHeaderLine('X-CSRF-TOKEN');
        self::assertNotSame('', $nextToken);
        self::assertNotSame($renderedToken, $nextToken);

        $second = $this->withSession($session)->post('/users/9003/delete', ['csrf_test_name' => $nextToken]);
        $second->assertStatus(204);
        self::assertNotSame('', $second->response()->getHeaderLine('X-CSRF-TOKEN'));
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', 9002)->get()->getRow('isDeleted'));
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
    }

    public function testUserDeletePreservesStateForSelfMissingCsrfAndTransactionFailure(): void
    {
        $self = $this->postAs(9002, 2, 1, 4, '/users/9002/delete', []);
        $self->assertStatus(409);
        $self->assertJSONExact(['error' => 'user_delete_forbidden']);
        self::assertNotSame('', $self->response()->getHeaderLine('X-CSRF-TOKEN'));
        self::assertSame(0, (int) $this->db->table('tbl_users')->where('userId', 9002)->get()->getRow('isDeleted'));
        self::assertSame(1, (int) $this->db->table('ci4_users')->where('id', 9002)->get()->getRow('is_active'));

        $missing = $this->postAs(9001, 1, null, 1, '/users/9999/delete', []);
        $missing->assertStatus(404);
        self::assertNotSame('', $missing->response()->getHeaderLine('X-CSRF-TOKEN'));

        foreach ([[], ['csrf_test_name' => 'wrong']] as $payload) {
            try {
                $this->withSession($this->session(9001, 1, null, 1))->post('/users/9003/delete', $payload);
                self::fail('Expected CSRF rejection.');
            } catch (SecurityException $exception) {
                self::assertSame('The action you requested is not allowed.', $exception->getMessage());
            }
        }
        self::assertSame(0, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
        self::assertSame(1, (int) $this->db->table('ci4_users')->where('id', 9003)->get()->getRow('is_active'));

        $shadowTable = $this->db->escapeIdentifiers($this->db->prefixTable('ci4_users'));
        $this->db->query("CREATE TRIGGER fail_user_deactivate BEFORE UPDATE ON {$shadowTable} WHEN OLD.id = 9003 BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        $retryToken = '';
        try {
            $failed = $this->postAs(9001, 1, null, 1, '/users/9003/delete', []);
            $failed->assertStatus(503);
            $failed->assertJSONExact(['error' => 'user_unavailable']);
            $retryToken = $failed->response()->getHeaderLine('X-CSRF-TOKEN');
            self::assertNotSame('', $retryToken);
            self::assertSame(0, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
            self::assertSame(1, (int) $this->db->table('ci4_users')->where('id', 9003)->get()->getRow('is_active'));
            self::assertSame(1, (int) $this->db->table('ci4_users')->where('id', 9003)->get()->getRow('session_version'));
        } finally {
            $this->db->query('DROP TRIGGER IF EXISTS fail_user_deactivate');
            $this->db->resetTransStatus();
        }

        $retry = $this->withSession($this->session(9001, 1, null, 1))
            ->post('/users/9003/delete', ['csrf_test_name' => $retryToken]);
        $retry->assertStatus(204);
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
        self::assertSame(0, (int) $this->db->table('ci4_users')->where('id', 9003)->get()->getRow('is_active'));
    }

    /** @param array<string, string> $payload */
    private function postAs(int $id, int $role, ?int $branch, int $group, string $path, array $payload, ?int $sessionVersion = null)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        $session = $this->session($id, $role, $branch, $group);
        if ($sessionVersion !== null) {
            $session['sessionVersion'] = $sessionVersion;
        }

        return $this->withSession($session)->post($path, $payload);
    }

    public function testC3LabelParityUserFormUsesCi3Text(): void
    {
        $page = $this->withSession($this->session(9001, 1, null, 1))->get('/users/new');
        $page->assertStatus(200);
        $page->assertSee('Full Name');
        $page->assertSee('Email address');
        $page->assertSee('Mobile Number');
        $page->assertSee('User Group');
        $page->assertSee('>Role</label>');
        $page->assertDontSee('>name</label>');
        $page->assertDontSee('>mobile</label>');
        self::assertStringContainsString('type="reset"', $page->getBody());

        $edit = $this->withSession($this->session(9001, 1, null, 1))->get('/users/9002');
        $edit->assertStatus(200);
        self::assertStringContainsString('type="reset"', $edit->getBody());
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
