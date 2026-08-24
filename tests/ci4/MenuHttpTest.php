<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Master\MenuStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class MenuHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'group_menu' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, group_type VARCHAR(250) NOT NULL, name VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'tbl_menu' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, menu_name VARCHAR(250) NOT NULL, menu_link VARCHAR(250) NOT NULL, group_type INTEGER NOT NULL, cdate DATETIME NOT NULL',
            'request_order' => 'request_id INTEGER PRIMARY KEY, branchID INTEGER, action_status INTEGER',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->resetDataCache();
        $now = '2026-08-22 09:00:00';
        $this->db->table('group_menu')->insertBatch([
            ['id' => 1, 'group_type' => '1,2', 'name' => 'CENTRAL', 'cdate' => $now],
            ['id' => 4, 'group_type' => '1,3', 'name' => 'BRANCH', 'cdate' => $now],
        ]);
        $this->db->table('tbl_menu')->insertBatch([
            ['id' => 1, 'menu_name' => 'DASH LINK', 'menu_link' => 'dashboard', 'group_type' => 1, 'cdate' => $now],
            ['id' => 2, 'menu_name' => 'ADMIN MASTER LINK', 'menu_link' => 'brandListing', 'group_type' => 2, 'cdate' => $now],
            ['id' => 3, 'menu_name' => 'BRANCH ORDER LINK', 'menu_link' => 'orders', 'group_type' => 3, 'cdate' => $now],
            ['id' => 4, 'menu_name' => 'REPORT LINK', 'menu_link' => 'ReportTrackingListing', 'group_type' => 2, 'cdate' => $now],
            ['id' => 5, 'menu_name' => 'RETIRED TEST LINK', 'menu_link' => 'ReportTrackingListingTest', 'group_type' => 2, 'cdate' => $now],
        ]);
        $users = new ShadowUserStore($this->db);
        $this->adminId = $users->create('menu-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null);
        $this->branchId = $users->create('menu-branch@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1);
    }

    public function testAdminCanListCreateAndEditMenuGroupsWithValidation(): void
    {
        $listing = $this->withSession($this->session($this->adminId, 1, 1, null))->get('/menu');
        $listing->assertStatus(200);
        $listing->assertSee('CENTRAL');
        $listing->assertSee('BRANCH');

        $this->postAsAdmin('/menu', ['name' => 'REPORTING', 'group_type' => ['1', '3']])
            ->assertRedirectTo('/menu');
        $row = $this->db->table('group_menu')->where('name', 'REPORTING')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('1,3', $row['group_type']);

        $this->postAsAdmin('/menu/' . $row['id'], ['name' => 'REPORT ONLY', 'group_type' => ['1']])
            ->assertRedirectTo('/menu');
        self::assertSame('1', $this->db->table('group_menu')->where('id', $row['id'])->get()->getRow('group_type'));

        $this->postAsAdmin('/menu/' . $row['id'], ['name' => 'BROKEN', 'group_type' => ['1', '3 OR 1=1']])
            ->assertStatus(422);
        self::assertSame('REPORT ONLY', $this->db->table('group_menu')->where('id', $row['id'])->get()->getRow('name'));
    }

    public function testMenuListingSearchFiltersByNameAndPrefills(): void
    {
        $session = $this->session($this->adminId, 1, 1, null);

        $match = $this->withSession($session)->get('/menu?search=CENTRAL');
        $match->assertStatus(200);
        $match->assertSee('CENTRAL');
        $match->assertDontSee('BRANCH');
        self::assertStringContainsString('value="CENTRAL"', $match->getBody());

        $overlong = $this->withSession($session)->get('/menu?search=' . str_repeat('x', 129));
        $overlong->assertStatus(200);
        $overlong->assertSee('CENTRAL');
        $overlong->assertSee('BRANCH');
    }

    public function testSidebarUsesOnlyCurrentGroupCsvSelection(): void
    {
        $visible = (new MenuStore($this->db))->visible(1);
        self::assertSame(['DASH LINK', 'ADMIN MASTER LINK', 'REPORT LINK'], array_column($visible, 'menu_name'));
        self::assertSame(['dashboard', 'master/brand', 'ReportTrackingListing'], array_column($visible, 'menu_link'));
        $admin = $this->withSession($this->session($this->adminId, 1, 1, null))->get('/dashboard');
        $admin->assertStatus(200);
        $admin->assertSee('ADMIN MASTER LINK');
        self::assertStringContainsString('/master/brand', $admin->getBody());
        $admin->assertDontSee('RETIRED TEST LINK');
        $admin->assertDontSee('BRANCH ORDER LINK');

        $branch = $this->withSession($this->session($this->branchId, 2, 4, 1))->get('/dashboard');
        $branch->assertStatus(200);
        $branch->assertSee('BRANCH ORDER LINK');
        $branch->assertDontSee('ADMIN MASTER LINK');
    }

    /** @param array<string, mixed> $payload */
    private function postAsAdmin(string $path, array $payload)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session($this->adminId, 1, 1, null))->post($path, $payload);
    }

    /** @return array<string, int|bool|null|string> */
    private function session(int $userId, int $role, int $groupId, ?int $branchId): array
    {
        return [
            'userId' => $userId, 'role' => $role, 'GroupID' => $groupId, 'BranchID' => $branchId,
            'name' => 'Synthetic', 'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
    }
}
