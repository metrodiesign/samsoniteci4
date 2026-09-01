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
        service('superglobals')->setGetArray([])->setPostArray([])->setFilesArray([]);
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

    public function testLegacyUserListingUsesCi3AuthenticationAndSafeMethodContracts(): void
    {
        foreach (['/userListing', '/userListing/50', '/user/userListing', '/User/userListing'] as $path) {
            $anonymousGet = $this->withSession([])->get($path);
            $anonymousGet->assertStatus(307);
            $anonymousGet->assertRedirectTo('/login');
            foreach (['POST', 'HEAD', 'OPTIONS'] as $method) {
                $anonymous = $this->withSession([])->call($method, $path);
                $anonymous->assertStatus(303);
                $anonymous->assertRedirectTo('/login');
            }
        }

        foreach (['GET', 'POST', 'HEAD', 'OPTIONS'] as $method) {
            $listing = $this->withSession($this->session(9002, 2, 1, 4))->call($method, '/userListing');
            $listing->assertStatus(200);
            self::assertStringContainsString('<title>Tracking : User Listing</title>', (string) $listing->getBody());
        }
        $offset = $this->withSession($this->session(9002, 2, 1, 4))->get('/userListing/50');
        $offset->assertStatus(200);
        self::assertStringContainsString('name="searchText" value=""', (string) $offset->getBody());
    }

    public function testLegacyUserListingUsesPostOnlyRawSearchAndCi3ZeroSemantics(): void
    {
        $admin = $this->session(9001, 1, null, 1);

        $getQuery = $this->withSession($admin)->get('/userListing?search=' . rawurlencode('OPERATOR A'));
        $getQuery->assertStatus(200);
        $getQuery->assertSee('ADMIN');
        $getQuery->assertSee('OPERATOR A');
        $getQuery->assertSee('OPERATOR B');
        self::assertStringContainsString('name="searchText" value=""', (string) $getQuery->getBody());

        $rawSpaces = $this->withSession($admin)->post('/userListing', ['searchText' => '   ']);
        $rawSpaces->assertStatus(200);
        self::assertSame(0, substr_count((string) $rawSpaces->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertStringContainsString('name="searchText" value="   "', (string) $rawSpaces->getBody());

        $zero = $this->withSession($admin)->post('/userListing', ['searchText' => '0']);
        $zero->assertStatus(200);
        self::assertSame(3, substr_count((string) $zero->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertStringContainsString('name="searchText" value="0"', (string) $zero->getBody());

        $mobile = $this->withSession($admin)->post('/userListing', ['searchText' => '0000000000']);
        self::assertSame(3, substr_count((string) $mobile->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
    }

    public function testLegacyUserListingKeepsCi3LikeWildcardSearchWithoutSqlInjection(): void
    {
        $admin = $this->session(9001, 1, null, 1);
        $percent = $this->withSession($admin)->post('/userListing', ['searchText' => '%']);
        $percent->assertStatus(200);
        self::assertSame(3, substr_count((string) $percent->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertStringContainsString('name="searchText" value="%"', (string) $percent->getBody());

        $underscore = $this->withSession($admin)->post('/userListing', ['searchText' => 'OPERATOR _']);
        $underscore->assertStatus(200);
        self::assertSame(2, substr_count((string) $underscore->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));

        $injection = $this->withSession($admin)->post('/userListing', [
            'searchText' => "%' OR 1=1 -- ",
        ]);
        $injection->assertStatus(200);
        self::assertSame(0, substr_count((string) $injection->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertSame(3, $this->db->table('tbl_users')->countAllResults());
    }

    public function testLegacyUserListingUsesFiftyRowOffsetPaginationAndNameOrdering(): void
    {
        $this->db->table('tbl_users')->update(['isDeleted' => 1]);
        $rows = [];
        $hash = password_hash('Synthetic passphrase', PASSWORD_DEFAULT);
        for ($number = 1; $number <= 101; $number++) {
            $rows[] = [
                'userId' => 10000 + $number,
                'email' => sprintf('legacy-%03d@example.invalid', $number),
                'username' => sprintf('legacy-%03d', $number),
                'password' => $hash,
                'name' => sprintf('LEGACY-%03d', $number),
                'mobile' => sprintf('000000%04d', $number),
                'group_id' => 4,
                'roleId' => 2,
                'branch_id' => 1,
                'branch_type_id' => 1,
                'isDeleted' => 0,
                'createdBy' => 9001,
                'createdDtm' => '2026-08-22 09:00:00',
            ];
        }
        $this->db->table('tbl_users')->insertBatch(array_reverse($rows));
        $session = $this->session(9001, 1, null, 1);

        $first = $this->withSession($session)->get('/userListing');
        self::assertSame(50, substr_count((string) $first->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        preg_match_all('/<td>([0-9]+)<\/td>\s*<td>LEGACY-[0-9]{3}<\/td>/', (string) $first->getBody(), $firstNumbers);
        self::assertSame(array_map('strval', range(1, 50)), $firstNumbers[1]);
        self::assertStringContainsString('/userListing/50', (string) $first->getBody());
        self::assertStringContainsString('data-ci-pagination-page="2" rel="next">Next</a>', (string) $first->getBody());

        $second = $this->withSession($session)->get('/userListing/50');
        self::assertSame(50, substr_count((string) $second->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        preg_match_all('/<td>([0-9]+)<\/td>\s*<td>LEGACY-[0-9]{3}<\/td>/', (string) $second->getBody(), $secondNumbers);
        self::assertSame(array_map('strval', range(51, 100)), $secondNumbers[1]);
        self::assertStringContainsString('data-ci-pagination-page="1" rel="prev">Previous</a>', (string) $second->getBody());
        self::assertStringContainsString('/userListing/100', (string) $second->getBody());

        $last = $this->withSession($session)->get('/userListing/100');
        self::assertSame(1, substr_count((string) $last->getBody(), 'class="btn btn-sm btn-danger deleteUser"'));
        self::assertMatchesRegularExpression('/<td>101<\/td>\s*<td>LEGACY-101<\/td>/', (string) $last->getBody());
    }

    public function testLegacyUserActionRoutesRenderCi3FormsMethodsAndControllerAliases(): void
    {
        foreach (['/addNew', '/addNewUser', '/editOld/9002', '/editUser', '/deleteUser', '/checkEmailExists'] as $path) {
            $anonymousGet = $this->withSession([])->get($path);
            $anonymousGet->assertStatus(307);
            $anonymousGet->assertRedirectTo('/login');
        }

        $admin = $this->session(9001, 1, null, 1);
        foreach (['GET', 'POST', 'HEAD', 'OPTIONS'] as $method) {
            $add = $this->withSession($admin)->call($method, '/addNew');
            $add->assertStatus(200);
            self::assertStringContainsString(
                '<form role="form" id="addUser" action="http://example.invalid/addNewUser" method="post"',
                (string) $add->getBody(),
            );
        }
        $edit = $this->withSession($admin)->get('/editOld/9002');
        $edit->assertStatus(200);
        self::assertStringContainsString('value="9002" name="userId" id="userId"', (string) $edit->getBody());
        $missingId = $this->withSession($admin)->get('/editOld');
        $missingId->assertStatus(307);
        $missingId->assertRedirectTo('/userListing');

        foreach (['/user/addNew', '/User/addNew'] as $path) {
            $alias = $this->withSession($admin)->get($path);
            $alias->assertStatus(200);
            self::assertStringContainsString('action="http://example.invalid/addNewUser"', (string) $alias->getBody());
        }
        foreach (['/user/editOld/9002', '/User/editOld/9002'] as $path) {
            $this->withSession($admin)->get($path)->assertStatus(200);
        }

        $branchAdd = $this->withSession($this->session(9002, 2, 1, 4))->get('/addNew');
        $branchAdd->assertStatus(200);
        self::assertStringNotContainsString('<option value="1">Administrator</option>', (string) $branchAdd->getBody());
        self::assertStringContainsString('<option value="2">Operator</option>', (string) $branchAdd->getBody());
    }

    public function testLegacyAddNewUserUsesCi3ValidationRedirectFeedbackAndBranchDerivedValues(): void
    {
        $session = $this->session(9002, 2, 1, 4);
        $invalid = $this->withSession($session)->post('/addNewUser', [
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $invalid->assertStatus(200);
        $invalidBody = html_entity_decode((string) $invalid->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach ([
            'The Full Name field is required.',
            'The Email field is required.',
            'The Password field is required.',
            'The Confirm Password field is required.',
            'The Role field is required.',
            'The Mobile Number field is required.',
        ] as $message) {
            self::assertStringContainsString($message, $invalidBody);
        }
        self::assertSame(3, $this->db->table('tbl_users')->countAllResults());

        $created = $this->withSession($session)->post('/addNewUser', [
            'fname' => 'jOhN DOE',
            'email' => 'legacy-new@example.invalid',
            'password' => 'Valid passphrase 1',
            'cpassword' => 'Valid passphrase 1',
            'role' => '2',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $created->assertStatus(303);
        $created->assertRedirectTo('/addNew');
        self::assertSame('New User created successfully', service('session')->getFlashdata('success'));
        $legacy = $this->db->table('tbl_users')->where('email', 'legacy-new@example.invalid')->get()->getRowArray();
        self::assertNotNull($legacy);
        self::assertSame('John Doe', $legacy['name']);
        self::assertSame('branch-a', $legacy['username']);
        self::assertSame(4, (int) $legacy['group_id']);
        self::assertSame(2, (int) $legacy['roleId']);
        self::assertSame(1, (int) $legacy['branch_id']);
        self::assertSame(1, (int) $legacy['branch_type_id']);
        self::assertSame(9002, (int) $legacy['createdBy']);
        self::assertTrue(password_verify('Valid passphrase 1', (string) $legacy['password']));
        self::assertSame(1, $this->db->table('ci4_users')->where('id', $legacy['userId'])->countAllResults());
    }

    public function testCentralAdminLegacyAddNewUserDerivesBranchTypeAndUsernameFromSelectedBranch(): void
    {
        $created = $this->withSession($this->session(9001, 1, null, 1))->post('/addNewUser', [
            'group_id' => '4',
            'branch_type' => '2',
            'branch_id' => '1',
            'fname' => 'CENTRAL CREATED USER',
            'email' => 'central-created@example.invalid',
            'password' => 'Valid passphrase 1',
            'cpassword' => 'Valid passphrase 1',
            'role' => '2',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $created->assertStatus(303);
        $created->assertRedirectTo('/addNew');
        $legacy = $this->db->table('tbl_users')->where('email', 'central-created@example.invalid')->get()->getRowArray();
        self::assertNotNull($legacy);
        self::assertSame('branch-a', $legacy['username']);
        self::assertSame(1, (int) $legacy['branch_id']);
        self::assertSame(1, (int) $legacy['branch_type_id']);
        self::assertSame(4, (int) $legacy['group_id']);
        self::assertSame(9001, (int) $legacy['createdBy']);
    }

    public function testLegacyAddNewUserPreservesCi3EmailCaseWhileNormalizingShadowIdentity(): void
    {
        $created = $this->withSession($this->session(9002, 2, 1, 4))->post('/addNewUser', [
            'fname' => 'EMAIL CASE USER',
            'email' => 'Case.User@Example.INVALID',
            'password' => 'Valid passphrase 1',
            'cpassword' => 'Valid passphrase 1',
            'role' => '2',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $created->assertStatus(303);
        $legacy = $this->db->table('tbl_users')->where('email', 'Case.User@Example.INVALID')->get()->getRowArray();
        self::assertNotNull($legacy);
        self::assertSame('Case.User@Example.INVALID', $legacy['email']);
        self::assertSame(
            'case.user@example.invalid',
            $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('email'),
        );
    }

    public function testLegacyAddNewUserAcceptsCi3TrimmedNumericRoleValue(): void
    {
        $created = $this->withSession($this->session(9002, 2, 1, 4))->post('/addNewUser', [
            'fname' => 'TRIMMED ROLE USER',
            'email' => 'trimmed-role@example.invalid',
            'password' => 'Valid passphrase 1',
            'cpassword' => 'Valid passphrase 1',
            'role' => ' 2 ',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $created->assertStatus(303);
        self::assertSame('New User created successfully', service('session')->getFlashdata('success'));
        self::assertSame(
            2,
            (int) $this->db->table('tbl_users')->where('email', 'trimmed-role@example.invalid')->get()->getRow('roleId'),
        );
    }

    public function testLegacyAddNewUserAllowsCi3BranchUsernameReuseWithUniqueShadowIdentity(): void
    {
        $this->db->table('tbl_users')->where('userId', 9002)->update(['username' => 'branch-a']);
        $this->db->table('ci4_users')->where('id', 9002)->update(['username' => 'branch-a']);
        $created = $this->withSession($this->session(9002, 2, 1, 4))->post('/addNewUser', [
            'fname' => 'SECOND BRANCH USER',
            'email' => 'second-branch@example.invalid',
            'password' => 'Valid passphrase 1',
            'cpassword' => 'Valid passphrase 1',
            'role' => '2',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $created->assertStatus(303);
        self::assertSame('New User created successfully', service('session')->getFlashdata('success'));
        $legacy = $this->db->table('tbl_users')->where('email', 'second-branch@example.invalid')->get()->getRowArray();
        self::assertNotNull($legacy);
        self::assertSame('branch-a', $legacy['username']);
        $shadowUsername = $this->db->table('ci4_users')->where('id', $legacy['userId'])->get()->getRow('username');
        self::assertSame('legacy-' . $legacy['userId'], $shadowUsername);
    }

    public function testLegacyEditUserUsesCi3ValidationRedirectFeedbackAndPasswordSemantics(): void
    {
        $session = $this->session(9002, 2, 1, 4);
        $invalid = $this->withSession($session)->post('/editUser', [
            'userId' => '9002',
            'fname' => '',
            'email' => 'bad-email',
            'password' => '',
            'cpassword' => '',
            'role' => '',
            'mobile' => '123',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $invalid->assertStatus(200);
        $invalidBody = html_entity_decode((string) $invalid->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach ([
            'The Full Name field is required.',
            'The Email field must contain a valid email address.',
            'The Role field is required.',
            'The Mobile Number field must be at least 10 characters in length.',
        ] as $message) {
            self::assertStringContainsString($message, $invalidBody);
        }
        $beforeHash = (string) $this->db->table('tbl_users')->where('userId', 9002)->get()->getRow('password');

        $updated = $this->withSession($session)->post('/editUser', [
            'userId' => '9002',
            'fname' => 'jANE DOE',
            'email' => 'a@example.invalid',
            'password' => '',
            'cpassword' => '',
            'role' => '2',
            'mobile' => '0123456789',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $updated->assertStatus(303);
        $updated->assertRedirectTo('/userListing');
        self::assertSame('User updated successfully', service('session')->getFlashdata('success'));
        $legacy = $this->db->table('tbl_users')->where('userId', 9002)->get()->getRowArray();
        self::assertSame('Jane Doe', $legacy['name']);
        self::assertSame('0123456789', $legacy['mobile']);
        self::assertSame($beforeHash, $legacy['password']);
        self::assertSame(9002, (int) $legacy['updatedBy']);
        self::assertSame('Jane Doe', $this->db->table('ci4_users')->where('id', 9002)->get()->getRow('display_name'));
    }

    public function testLegacyDeleteUserAndEmailCheckKeepCi3JsonAndBooleanContracts(): void
    {
        $admin = $this->session(9001, 1, null, 1);
        $safeGet = $this->withSession($admin)->get('/deleteUser');
        $safeGet->assertStatus(200);
        self::assertSame('{"status":false}', (string) $safeGet->response()->getBody());

        $deleted = $this->withSession($admin)->post('/deleteUser', [
            'userId' => '9003',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $deleted->assertStatus(200);
        self::assertSame('{"status":true}', (string) $deleted->response()->getBody());
        self::assertNotSame('', $deleted->response()->getHeaderLine('X-CSRF-TOKEN'));
        self::assertSame(1, (int) $this->db->table('tbl_users')->where('userId', 9003)->get()->getRow('isDeleted'));
        self::assertSame(0, (int) $this->db->table('ci4_users')->where('id', 9003)->get()->getRow('is_active'));

        $replayed = $this->withSession($admin)->post('/deleteUser', [
            'userId' => '9003',
            'csrf_test_name' => $deleted->response()->getHeaderLine('X-CSRF-TOKEN'),
        ]);
        self::assertSame('{"status":false}', (string) $replayed->response()->getBody());

        $self = $this->withSession($this->session(9002, 2, 1, 4))->post('/deleteUser', [
            'userId' => '9002',
            'csrf_test_name' => service('security')->getHash(),
        ]);
        self::assertSame('{"status":false}', (string) $self->response()->getBody());
        self::assertSame(0, (int) $this->db->table('tbl_users')->where('userId', 9002)->get()->getRow('isDeleted'));

        foreach ([
            [['email' => 'a@example.invalid'], 'false'],
            [['email' => 'available@example.invalid'], 'true'],
            [['email' => 'a@example.invalid', 'userId' => '9002'], 'true'],
        ] as [$payload, $expected]) {
            $response = $this->withSession($this->session(9002, 2, 1, 4))->post('/checkEmailExists', [
                ...$payload,
                'csrf_test_name' => service('security')->getHash(),
            ]);
            $response->assertStatus(200);
            self::assertSame($expected, (string) $response->response()->getBody());
        }
        $alias = $this->withSession($admin)->get('/user/checkEmailExists');
        $alias->assertStatus(200);
        self::assertSame('true', (string) $alias->response()->getBody());
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

    public function testLegacyLoginHistoryUsesCi3AuthenticationMethodsRoutesAndAliases(): void
    {
        foreach ([
            '/login-history',
            '/login-history/9002',
            '/login-history/9002/5',
            '/user/loginHistoy',
            '/User/loginHistoy',
            '/menu/loginHistoy',
            '/Menu/loginHistoy',
            '/user/loginHistoy/9002',
            '/User/loginHistoy/9002',
            '/menu/loginHistoy/9002',
            '/Menu/loginHistoy/9002',
        ] as $path) {
            $anonymousGet = $this->withSession([])->get($path);
            $anonymousGet->assertStatus(307);
            $anonymousGet->assertRedirectTo('/login');
            foreach (['POST', 'HEAD', 'OPTIONS'] as $method) {
                $anonymous = $this->withSession([])->call($method, $path);
                $anonymous->assertStatus(303);
                $anonymous->assertRedirectTo('/login');
            }
        }

        $session = $this->session(9002, 2, 1, 4);
        foreach (['GET', 'POST', 'HEAD', 'OPTIONS'] as $method) {
            $own = $this->withSession($session)->call($method, '/login-history');
            $own->assertStatus(200);
            self::assertStringContainsString('<title>Tracking : User Login History</title>', (string) $own->getBody());
        }
        foreach (['/login-history/9002', '/login-history/9002/5', '/user/loginHistoy/9002'] as $path) {
            $response = $this->withSession($session)->get($path);
            $response->assertStatus(200);
            self::assertStringContainsString('OPERATOR A : a@example.invalid', (string) $response->getBody());
        }
    }

    public function testLegacyLoginHistoryDisplaysPostSearchWithoutFilteringAndUsesExactOffsets(): void
    {
        for ($index = 1; $index <= 7; $index++) {
            $this->db->table('tbl_last_login')->insert([
                'userId' => 9002,
                'sessionData' => '{"index":' . $index . '}',
                'machineIp' => '127.0.0.' . $index,
                'userAgent' => 'Browser',
                'agentString' => 'LEGACY-HIST-' . $index,
                'platform' => 'Test',
                'createdDtm' => sprintf('2026-08-%02d 09:00:00', $index),
            ]);
        }
        $session = $this->session(9002, 2, 1, 4);
        $searched = $this->withSession($session)->post('/login-history', ['searchText' => 'NO-MATCH']);
        $searched->assertStatus(200);
        $searchedBody = (string) $searched->getBody();
        self::assertStringContainsString('name="searchText" value="NO-MATCH"', $searchedBody);
        self::assertMatchesRegularExpression('/LEGACY-HIST-7.*LEGACY-HIST-3/s', $searchedBody);
        self::assertStringNotContainsString('LEGACY-HIST-2', $searchedBody);
        self::assertStringContainsString('/login-history/9002/5', $searchedBody);
        self::assertStringContainsString('data-ci-pagination-page="2" rel="next">Next</a>', $searchedBody);

        $getQuery = $this->withSession($session)->get('/login-history?searchText=NO-MATCH');
        self::assertStringContainsString('name="searchText" value=""', (string) $getQuery->getBody());
        self::assertStringContainsString('LEGACY-HIST-7', (string) $getQuery->getBody());

        $offsetOne = $this->withSession($session)->get('/login-history/9002/1');
        $offsetOneBody = (string) $offsetOne->getBody();
        self::assertMatchesRegularExpression('/LEGACY-HIST-6.*LEGACY-HIST-2/s', $offsetOneBody);
        self::assertStringNotContainsString('LEGACY-HIST-7', $offsetOneBody);
        self::assertStringNotContainsString('LEGACY-HIST-1', $offsetOneBody);

        $offsetFive = $this->withSession($session)->get('/login-history/9002/5');
        self::assertMatchesRegularExpression('/LEGACY-HIST-2.*LEGACY-HIST-1/s', (string) $offsetFive->getBody());
        self::assertStringContainsString(
            'data-ci-pagination-page="1" rel="prev">Previous</a>',
            (string) $offsetFive->getBody(),
        );

        $controllerAlias = $this->withSession($session)->get('/user/loginHistoy/9002');
        $controllerAlias->assertStatus(200);
        self::assertStringContainsString('OPERATOR A : a@example.invalid', (string) $controllerAlias->getBody());
        self::assertStringNotContainsString('LEGACY-HIST-', (string) $controllerAlias->getBody());
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
