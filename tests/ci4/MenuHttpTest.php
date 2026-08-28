<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Master\MenuStore;
use App\Presentation\AdminLayoutPresenter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\AssertionFailedError;

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
            'group_type' => 'group_type_id INTEGER PRIMARY KEY, group_type_name VARCHAR(250) NOT NULL, icon_menu VARCHAR(250) NOT NULL',
            'tbl_menu' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, menu_name VARCHAR(250) NOT NULL, menu_link VARCHAR(250) NOT NULL, group_type INTEGER NOT NULL, cdate DATETIME NOT NULL',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_name VARCHAR(250) NOT NULL, branch_user_name VARCHAR(250) NOT NULL',
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
        $this->db->table('group_type')->insertBatch([
            ['group_type_id' => 1, 'group_type_name' => 'DASHBOARD', 'icon_menu' => 'fa fa-dashboard'],
            ['group_type_id' => 2, 'group_type_name' => 'MASTER ADMIN', 'icon_menu' => 'fa fa-cogs'],
            ['group_type_id' => 3, 'group_type_name' => 'ORDER', 'icon_menu' => 'fa fa-shopping-cart'],
        ]);
        $this->db->table('branch')->insert([
            'branch_id' => 1,
            'branch_name' => 'SYNTHETIC BRANCH',
            'branch_user_name' => 'synthetic-branch',
        ]);
        $this->db->table('tbl_menu')->insertBatch([
            ['id' => 1, 'menu_name' => 'DASH LINK', 'menu_link' => 'dashboard', 'group_type' => 1, 'cdate' => $now],
            ['id' => 2, 'menu_name' => 'ADMIN MASTER LINK', 'menu_link' => 'brandListing', 'group_type' => 2, 'cdate' => $now],
            ['id' => 3, 'menu_name' => 'BRANCH ORDER LINK', 'menu_link' => 'orders', 'group_type' => 3, 'cdate' => $now],
            ['id' => 4, 'menu_name' => 'REPORT LINK', 'menu_link' => 'ReportTrackingListing', 'group_type' => 2, 'cdate' => $now],
            ['id' => 5, 'menu_name' => 'RETIRED TEST LINK', 'menu_link' => 'ReportTrackingListingTest', 'group_type' => 2, 'cdate' => $now],
            ['id' => 6, 'menu_name' => 'TRANSPORTING', 'menu_link' => 'TrackingListing', 'group_type' => 3, 'cdate' => $now],
            ['id' => 7, 'menu_name' => 'STATUS REPAIR', 'menu_link' => 'TrackingcloseListing', 'group_type' => 3, 'cdate' => $now],
            ['id' => 8, 'menu_name' => 'DELIVERED', 'menu_link' => 'TrackingreturnListing', 'group_type' => 3, 'cdate' => $now],
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
        self::assertStringNotContainsString('<td>BRANCH</td>', $match->getBody());
        $match->assertSee('Menu Group name');
        $match->assertSee('>Edit</a>');
        // 'Add, Edit, Delete' is CI3's page subtitle; assert the absence of a delete control,
        // not of the word.
        self::assertStringNotContainsString('>Delete<', $match->getBody());
        self::assertStringContainsString('value="CENTRAL"', $match->getBody());

        $missing = $this->withSession($session)->get('/menu?search=ABSENT');
        $missing->assertStatus(200);
        $missing->assertSee('Menu Group name');
        $missing->assertDontSee('CENTRAL');
        self::assertStringContainsString('value="ABSENT"', $missing->getBody());

        $overlong = $this->withSession($session)->get('/menu?search=' . str_repeat('x', 129));
        $overlong->assertStatus(200);
        $overlong->assertSee('CENTRAL');
        $overlong->assertSee('BRANCH');
    }

    public function testAdminLayoutPresenterMapsTheRealLoginSessionContract(): void
    {
        $data = (new AdminLayoutPresenter(new MenuStore($this->db)))->present([
            'isLoggedIn' => true,
            'name' => 'Synthetic presenter',
            'roleText' => 'Admin',
            'lastLogin' => '2026-08-27 08:15:00',
            'GroupID' => 1,
            'BranchID' => null,
        ], 'Presenter title', '<p>Presenter content</p>');

        self::assertSame('Presenter title', $data['pageTitle']);
        self::assertSame('Presenter title', $data['title']);
        self::assertSame('<p>Presenter content</p>', $data['content']);
        self::assertSame('Synthetic presenter', $data['name']);
        self::assertSame('Admin', $data['role_text']);
        self::assertSame('2026-08-27 08:15:00', $data['last_login']);
        self::assertSame(1, $data['GroupID']);
        self::assertNull($data['BranchID']);
        self::assertSame('', $data['BranchName']);
        self::assertTrue($data['showBranchAutocomplete']);
        self::assertSame([
            ['label' => 'SYNTHETIC BRANCH', 'value' => base_url('ReportTrackingListing/0/1')],
        ], $data['branchOptions']);
        self::assertSame('admin', $data['layoutProfile']);
        self::assertSame([1, 2], array_column($data['menuItems'], 'group_id'));

        $branchData = (new AdminLayoutPresenter(new MenuStore($this->db)))->present([
            'isLoggedIn' => true,
            'name' => 'Synthetic branch presenter',
            'GroupID' => 4,
            'BranchID' => 1,
        ], 'Branch title', '<p>Branch content</p>');

        self::assertSame('SYNTHETIC BRANCH', $branchData['BranchName']);
        self::assertFalse($branchData['showBranchAutocomplete']);
        self::assertSame([], $branchData['branchOptions']);
    }

    public function testAdminShellRestoresCi3HierarchyAssetsAndScripts(): void
    {
        $body = (string) $this
            ->withSession($this->session($this->adminId, 1, 1, null))
            ->get('/dashboard')
            ->getBody();

        $this->assertFragmentsInOrder($body, [
            '<!DOCTYPE html>',
            'assets/bootstrap/css/bootstrap.min.css',
            'assets/datatables/1.10.16/css/jquery.dataTables.min.css',
            'assets/datatables-fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css',
            'assets/font-awesome/css/font-awesome.min.css',
            'assets/dist/css/AdminLTE.min.css',
            'assets/dist/css/CustomAdmin.css',
            'assets/css/main.css',
            'assets/css/multifreezer.css',
            'assets/dist/css/skins/_all-skins.min.css',
            'assets/js/jquerydatepicker/jquery-1.10.2.min.js',
            'assets/js/jquerydatepicker/jquery-ui.css',
            'assets/js/jquerydatepicker/jquery-ui-timepicker-addon.css',
            'assets/js/jquerydatepicker/jquery-ui.min.js',
            'assets/js/jquerydatepicker/jquery-ui-timepicker-addon.js',
            'assets/js/jquerydatepicker/jquery-ui-sliderAccess.js',
            'var baseURL =',
            'assets/html5shiv/3.7.2/html5shiv.min.js',
            'assets/respond/1.4.2/respond.min.js',
            '<body class="skin-blue sidebar-mini">',
            '<div class="wrapper">',
            '<header class="main-header">',
            'class="logo"',
            'assets/images/print-logo.jpg',
            '<nav class="navbar navbar-static-top"',
            'class="sidebar-toggle" data-toggle="offcanvas"',
            'onclick="history.back(-1)"',
            '<div class="navbar-custom-menu">',
            '<aside class="main-sidebar">',
            '<section class="sidebar">',
            '<ul class="sidebar-menu">',
            '<div class="content-wrapper">',
            '<footer class="main-footer">',
            '<section id="footer">',
            'assets/images/img-footer.png',
            'assets/bootstrap/js/bootstrap.min.js',
            'assets/dist/js/app.min.js',
            'assets/js/jquery.validate.js',
            'assets/js/validation.js',
            'var windowURL = window.location.href;',
            'assets/datatables/1.10.16/js/jquery.dataTables.min.js',
            'assets/datatables-fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js',
            "var table = $('#example').DataTable({",
        ]);

        self::assertMatchesRegularExpression('#<form action="[^"]*/logout" method="post"#', $body);
        self::assertStringContainsString('<button type="submit"', $body);
        self::assertMatchesRegularExpression(
            '#<form action="[^"]*/logout" method="post".*name="csrf_test_name".*<button type="submit"#s',
            $body,
        );
        self::assertStringNotContainsString('href="/logout"', $body);
        self::assertMatchesRegularExpression(
            '#<img class="" src="[^"]*/assets/images/img-footer\.png">#',
            $body,
        );
        $dataTablesInitialization = implode("\n", [
            '    $(document).ready(function() {',
            "        var table = $('#example').DataTable({",
            '            scrollY: "300px",',
            '            scrollX: true,',
            '            responsive: true,',
            "            className: 'mdl-data-table__cell--non-numeric',",
            '            scrollCollapse: true,',
            '            paging: true,',
            "            buttons: ['colvis'],",
            '            fixedColumns: {',
            '                leftColumns: 1,',
            '                leftColumns: 2,',
            '                leftColumns: 3',
            '            }',
            '        });',
            '    });',
        ]);
        self::assertStringContainsString($dataTablesInitialization, $body);

        foreach (['<svg', 'id="sidebar-toggle"', 'class="topbar"', '<body class="admin">'] as $replacement) {
            self::assertStringNotContainsString($replacement, $body, $replacement);
        }
    }

    public function testSharedRuntimeAssetClosureExistsAndIsGitTracked(): void
    {
        $documents = [];
        foreach (['/login', '/forgot-password', '/reset-password', '/contact', '/contact-th', '/tracking', '/tracking-th'] as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $documents[] = (string) $response->getBody();
        }
        $documents[] = (string) $this
            ->withSession($this->session($this->adminId, 1, 1, null))
            ->get('/dashboard')
            ->getBody();
        $documents[] = view('layout_public', [
            'content' => '',
            'title' => 'Samsonite',
            'language' => 'en',
            'legacyContactProfile' => false,
            'legacyTrackingProfile' => false,
        ]);
        $documents[] = view('access_denied');
        $documents[] = view('errors/html/error_404');

        $entrypoints = [];
        foreach ($documents as $document) {
            $entrypoints = array_merge($entrypoints, $this->runtimeAssetReferences($document));
        }
        $closure = $this->runtimeAssetClosure($entrypoints);
        self::assertCount(109, $closure);

        foreach ([
            'public/assets/datatables/1.10.16/images/sort_both.png',
            'public/assets/font-awesome/4.3.0/fonts/fontawesome-webfont.woff2',
            'public/assets/font-awesome/4.7.0/fonts/fontawesome-webfont.woff2',
            'public/assets/fonts/source-sans-pro/SourceSansPro-Regular.ttf',
            'public/assets/js/jquerydatepicker/images/animated-overlay.gif',
            'public/assets/images/bg-login.jpg',
            'public/uploads/web/contact_mobile.png',
            'public/uploads/web/track_mobile.png',
        ] as $recursiveAsset) {
            self::assertContains($recursiveAsset, $closure, $recursiveAsset);
        }

        $trackedFiles = [
            ...$closure,
            'public/assets/fonts/source-sans-pro/OFL.txt',
            'public/assets/licenses/bootstrap-3.3.7-LICENSE',
            'public/assets/licenses/datatables-1.10.16-license.txt',
            'public/assets/licenses/fixedcolumns-3.2.4-License.txt',
            'public/assets/licenses/respond-1.4.2-LICENSE-MIT',
            'public/assets/licenses/MIT.txt',
            'public/assets/licenses/OFL-1.1.txt',
            'tests/ci4/MenuHttpTest.php',
            'outputs/reference/2026-08-27_tpl01-asset-closure_v1.md',
        ];
        $trackedFiles = array_values(array_unique($trackedFiles));
        sort($trackedFiles);
        $process = proc_open(
            ['/usr/bin/env', 'git', 'ls-files', '--error-unmatch', '--', ...$trackedFiles],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            ROOTPATH,
        );
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), trim($error));

        $process = proc_open(
            ['/usr/bin/env', 'git', 'diff', '--quiet', '--no-ext-diff', '--', ...$trackedFiles],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            ROOTPATH,
        );
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), trim($error) ?: 'Git index blob differs from worktree runtime input.');
    }

    public function testRuntimeAssetReferencesScansStyleAttributes(): void
    {
        self::assertSame(
            ['/assets/images/bg-login.jpg'],
            $this->runtimeAssetReferences('<div style="background-image: url(\'/assets/images/bg-login.jpg\')"></div>'),
        );
    }

    public function testCssAssetParserKeepsQuotedImportAndEveryUrlAndSkipsEmbeddedData(): void
    {
        self::assertSame(
            ['a.css', 'b.png?cache=1#icon', 'c.png'],
            $this->cssAssetReferences(
                '@import "a.css"; .a{background:url(b.png?cache=1#icon)} .b{src:url(DATA:image/png;base64,AA)} .c{background:url("c.png")}',
            ),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mismatchedRuntimeOrigins')]
    public function testRuntimeAssetPathRejectsMismatchedOrigin(string $reference): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->runtimeAssetPath($reference, null);
    }

    /** @return iterable<string, array{string}> */
    public static function mismatchedRuntimeOrigins(): iterable
    {
        return [
            'scheme' => ['https://example.invalid/assets/app.css'],
            'host'   => ['http://cdn.example.invalid/assets/app.css'],
            'port'   => ['http://example.invalid:8080/assets/app.css'],
            'protocol relative' => ['//example.invalid/assets/app.css'],
        ];
    }

    public function testRuntimeAssetPathAcceptsOnlyStaticEntrypointPrefixesAndStripsQueryFragment(): void
    {
        self::assertSame('public/assets/app.css', $this->runtimeAssetPath('/assets/app.css?v=1#theme', null));
        self::assertSame('public/uploads/web/banner.png', $this->runtimeAssetPath('/uploads/web/banner.png?x=1#hero', null));
        self::assertNull($this->runtimeAssetPath('/background-image/assets/app.css', null));
        self::assertNull($this->runtimeAssetPath('/uploads/website/banner.png', null));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('escapingRuntimePaths')]
    public function testRuntimeAssetPathRejectsTraversalOutsidePublic(string $reference, ?string $parent): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->runtimeAssetPath($reference, $parent);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function escapingRuntimePaths(): iterable
    {
        return [
            'absolute' => ['/assets/../../outside.css', null],
            'relative' => ['../../../outside.css', 'public/assets/css/app.css'],
            'leave and re-enter' => ['/assets/../../public/secret.css', null],
        ];
    }

    public function testRuntimeAssetClosureStopsAtCssCycles(): void
    {
        $first = ROOTPATH . 'public/assets/css/.asset-closure-cycle-a.css';
        $second = ROOTPATH . 'public/assets/css/.asset-closure-cycle-b.css';
        file_put_contents($first, '@import ".asset-closure-cycle-b.css";');
        file_put_contents($second, '@import ".asset-closure-cycle-a.css";');

        try {
            self::assertSame(
                [
                    'public/assets/css/.asset-closure-cycle-a.css',
                    'public/assets/css/.asset-closure-cycle-b.css',
                ],
                $this->runtimeAssetClosure(['/assets/css/.asset-closure-cycle-a.css']),
            );
        } finally {
            unlink($first);
            unlink($second);
        }
    }

    public function testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts(): void
    {
        foreach ([
            'assets/bootstrap/css/bootstrap.min.css' => 'f75e846cc83bd11432f4b1e21a45f31bc85283d11d372f7b19accd1bf6a2635c',
            'assets/bootstrap/css/bootstrap.css' => '7e630d90c7234b0df1729f62b8f9e4bbfaf293d91a5a0ac46df25f2a6759e39a',
            'assets/bootstrap/js/bootstrap.min.js' => '53964478a7c634e8dad34ecc303dd8048d00dce4993906de1bacf67f663486ef',
            'assets/datatables/1.10.16/css/jquery.dataTables.min.css' => '618d62ceaca1223e16de2c8939a1963a95c34b0ac75852f835f93e5b42f20871',
            'assets/datatables/1.10.16/js/jquery.dataTables.min.js' => 'a9c575c2bf9b9f836806dc58aa0866cb558806fc5ea1ef2f4250a8c0b1be7278',
            'assets/datatables/1.10.16/images/sort_asc.png' => '595704c3f3cf4cb65c7d9c8508a99e7480e150095473faed31a07c21b13389b8',
            'assets/datatables/1.10.16/images/sort_asc_disabled.png' => 'a65b8f4f84d6427a81c360282fc5394d51bf99dada5f159e6aa0fce3c396825c',
            'assets/datatables/1.10.16/images/sort_both.png' => '3e016c23ae51417382b640ae2d19eb48047532c37ad53894bd185586559ccffb',
            'assets/datatables/1.10.16/images/sort_desc.png' => 'd08ed0e21f187dd309030d465224da8085119a15a17d616ba0e477bb50c6f10d',
            'assets/datatables/1.10.16/images/sort_desc_disabled.png' => '6c0f0c1b21ef6807057afc8ddc1a925d1dbd21cb11e9270ec84ff4ac40d9a3fa',
            'assets/datatables-fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css' => '2cac99438be2f9aacaf1a63f220f5a4e0fb5f54d443ecde09652a650b0509f8b',
            'assets/datatables-fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js' => 'e44ec8df1b3ae7c386f670b1e9d4b4cad0b55fa28f934f31fd9a893c81c50298',
            'assets/font-awesome/css/font-awesome.min.css' => '0fb1bbca73646e8e2b93c82e8d8b219647b13d4b440c48e338290b9a685b8de1',
            'assets/font-awesome/4.3.0/css/font-awesome.min.css' => '541ac58217a8ade1a5e292a65a0661dc9db7a49ae13654943817a4fbc6761afd',
            'assets/font-awesome/4.7.0/css/font-awesome.min.css' => '799aeb25cc0373fdee0e1b1db7ad6c2f6a0e058dfadaa3379689f583213190bd',
            'assets/fontawesome/css/font-awesome.css' => '36e0a7e08bee65774168528938072c536437669c1b7458ac77976ec788e4439c',
            'assets/html5shiv/3.7.2/html5shiv.min.js' => 'e0eac80838c161f29e7c46d54fbc044d12cd164baae13255e562c6be3aa91809',
            'assets/respond/1.4.2/respond.min.js' => '83a8807ef669fa70d0d9375347f5552897f76c6ae8e2e6f97ef592595462d8d1',
            'assets/fonts/source-sans-pro/stylesheet.css' => '31105045a28207422c3da95d2dbade1d4b26790035a903d8c5263fe999675f8f',
            'assets/fonts/source-sans-pro/OFL.txt' => 'fce9f9e2fb268507a89fceea0b3eccc044f39fc3492968a04fd9e04df5ae95fa',
            'uploads/web/contact_laptop.png' => '2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f',
            'uploads/web/contact_mobile.png' => '2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f',
            'uploads/web/track_laptop.png' => '16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a',
            'uploads/web/track_mobile.png' => '16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a',
            'assets/licenses/bootstrap-3.3.7-LICENSE' => '8a68c27ad022244b78dc5c9adb4cc5a4c92a994e3e11d356b291f227b2b04eaf',
            'assets/licenses/datatables-1.10.16-license.txt' => 'c6a873f21550ed804f76013c36e14225704c1aa551fdb870e0c626eb91c19247',
            'assets/licenses/fixedcolumns-3.2.4-License.txt' => 'e8e92f97216f9ea00cb2735b933a91ec8e3869bed37b6d63a90f76f41508f2de',
            'assets/licenses/respond-1.4.2-LICENSE-MIT' => '96ef890049d3089064f9965e661057d5c3d42be86421c10cc8e9400a104f0b36',
            'assets/licenses/MIT.txt' => 'b05785f9f18e6716bab63424b11454513b9943a222595b70411009202fc592b5',
            'assets/licenses/OFL-1.1.txt' => '8eea8287e5876b539670cadb82e99f9a7afddec6f6730811be1daf25d2e9bcfd',
        ] as $path => $checksum) {
            self::assertSame($checksum, hash_file('sha256', PUBLICPATH . $path), $path);
        }

        foreach ([
            'assets/bootstrap/css/bootstrap.min.css' => 'Bootstrap v3.3.7',
            'assets/font-awesome/css/font-awesome.min.css' => 'Font Awesome 4.2.0',
            'assets/font-awesome/4.3.0/css/font-awesome.min.css' => 'Font Awesome 4.3.0',
            'assets/font-awesome/4.7.0/css/font-awesome.min.css' => 'Font Awesome 4.7.0',
        ] as $path => $version) {
            self::assertStringContainsString($version, (string) file_get_contents(PUBLICPATH . $path), $path);
        }
    }

    public function testAdminShellUsesDatabaseBranchDataForHeaderAndAutocomplete(): void
    {
        $adminBody = (string) $this
            ->withSession($this->session($this->adminId, 1, 1, null))
            ->get('/dashboard')
            ->getBody();
        self::assertStringContainsString('id="autocomplete"', $adminBody);
        self::assertStringContainsString('"label":"SYNTHETIC BRANCH"', $adminBody);
        self::assertStringContainsString('ReportTrackingListing/0/1', $adminBody);

        $branchBody = (string) $this
            ->withSession($this->session($this->branchId, 2, 4, 1))
            ->get('/dashboard')
            ->getBody();
        self::assertStringContainsString('<b>BRANCH SYNTHETIC BRANCH</b>', $branchBody);
        self::assertStringNotContainsString('id="autocomplete"', $branchBody);
    }

    public function testCentralGroupKeepsAutocompleteWhenBranchListIsEmpty(): void
    {
        $this->db->table('branch')->truncate();

        $adminBody = (string) $this
            ->withSession($this->session($this->adminId, 1, 1, null))
            ->get('/dashboard')
            ->getBody();
        self::assertStringContainsString('<i class="fa fa-university"></i>', $adminBody);
        self::assertStringContainsString('var xsource = [];', $adminBody);
        self::assertStringContainsString('id="autocomplete"', $adminBody);

        $branchBody = (string) $this
            ->withSession($this->session($this->branchId, 2, 4, 1))
            ->get('/dashboard')
            ->getBody();
        self::assertStringNotContainsString('id="autocomplete"', $branchBody);
    }

    public function testBranchAutocompleteHexEscapesMaliciousDatabaseLabels(): void
    {
        $malicious = 'Campus "</script><script>alert(\'x\')</script><b>unsafe</b>';
        $this->db->table('branch')->insert([
            'branch_id' => 2,
            'branch_name' => $malicious,
            'branch_user_name' => 'malicious-fixture',
        ]);

        $body = (string) $this
            ->withSession($this->session($this->adminId, 1, 1, null))
            ->get('/dashboard')
            ->getBody();

        self::assertStringNotContainsString($malicious, $body);
        self::assertStringNotContainsString('</script><script>', $body);
        self::assertStringNotContainsString('<b>unsafe</b>', $body);
        self::assertStringContainsString('\\u003C/script\\u003E', $body);
        self::assertSame(1, preg_match('/var xsource = (\[[^\n]*\]);/', $body, $match));
        $source = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $index = array_search($malicious, array_column($source, 'label'), true);
        self::assertNotFalse($index);
        self::assertSame(base_url('ReportTrackingListing/0/2'), $source[$index]['value']);
    }

    public function testSidebarUsesOnlyCurrentGroupCsvSelection(): void
    {
        $groups = (new MenuStore($this->db))->visible(1);
        // Group order follows the CSV '1,2' of group_menu id 1; names/icons come from group_type.
        self::assertSame([1, 2], array_column($groups, 'group_id'));
        self::assertSame(['DASHBOARD', 'MASTER ADMIN'], array_column($groups, 'group_name'));

        $flatNames = [];
        $flatLinks = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $flatNames[] = $item['menu_name'];
                $flatLinks[] = $item['menu_link'];
            }
        }
        self::assertSame(['DASH LINK', 'ADMIN MASTER LINK', 'REPORT LINK'], $flatNames);
        self::assertSame(['dashboard', 'master/brand', 'ReportTrackingListing'], $flatLinks);

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
        // AC-5: pin the $branchId the controller forwards into visible(). Mutating
        // BaseController::layout() to pass null instead of $branchId lets these two hidden
        // order queues render for a branch user, turning both assertions red.
        $branch->assertDontSee('TRANSPORTING');
        $branch->assertDontSee('STATUS REPAIR');
    }

    public function testBranchUserHidesOrderQueuesAndRenumbersContinuously(): void
    {
        $store = new MenuStore($this->db);

        // Central user (BranchID null) sees the full ORDER group numbered 1..4.
        $central = $this->groupItems($store->visible(4, null), 3);
        self::assertSame(
            ['1. BRANCH ORDER LINK', '2. TRANSPORTING', '3. STATUS REPAIR', '4. DELIVERED'],
            array_column($central, 'menu_name'),
        );

        // Branch user (BranchID set) loses the two hidden queues; numbering stays 1..2
        // with no skipped number (hide happens before renumbering).
        $branch = $this->groupItems($store->visible(4, 1), 3);
        self::assertSame(['1. BRANCH ORDER LINK', '2. DELIVERED'], array_column($branch, 'menu_name'));
        self::assertNotContains('TrackingListing', array_column($branch, 'menu_link'));
        self::assertNotContains('TrackingcloseListing', array_column($branch, 'menu_link'));
    }

    public function testDedupIsPerGroupNotGlobalSoSharedLinksShowInEachGroup(): void
    {
        // AC-1 / AC-3: a link belonging to two group_types in the same CSV selection must show
        // in BOTH groups (CI3 parity), while a link repeated WITHIN one group is still shown
        // once. Reverting $seen in MenuStore::visible() to a global set (declared once outside
        // the per-type loop) drops the group-3 occurrence and turns assertContains red.
        $now = '2026-08-22 09:00:00';
        $this->db->table('tbl_menu')->insertBatch([
            ['id' => 9, 'menu_name' => 'SHARED LINK', 'menu_link' => 'dashboard', 'group_type' => 3, 'cdate' => $now],
            ['id' => 10, 'menu_name' => 'SHARED LINK DUP', 'menu_link' => 'dashboard', 'group_type' => 3, 'cdate' => $now],
        ]);

        $groups = (new MenuStore($this->db))->visible(4, null);
        $group1Links = array_column($this->groupItems($groups, 1), 'menu_link');
        $group3Links = array_column($this->groupItems($groups, 3), 'menu_link');

        // Cross-group: 'dashboard' kept in group 1 AND reappears in group 3 (per-group dedup).
        self::assertContains('dashboard', $group1Links);
        self::assertContains('dashboard', $group3Links);
        // Within group 3 the two duplicate 'dashboard' rows collapse to one.
        self::assertSame(1, count(array_keys($group3Links, 'dashboard', true)));
    }

    public function testGroupWithNoVisibleItemsIsNotRendered(): void
    {
        // AC-4: a CSV group_type that resolves to zero items (group_type 5 has no tbl_menu rows)
        // must not produce an empty sidebar heading. Removing the `$items === []` guard in
        // MenuStore::visible() pushes a headed-but-empty group and turns this red.
        $this->db->table('group_menu')->insert([
            'id' => 7, 'group_type' => '1,5', 'name' => 'HAS EMPTY', 'cdate' => '2026-08-22 09:00:00',
        ]);

        $groups = (new MenuStore($this->db))->visible(7, null);
        self::assertSame([1], array_column($groups, 'group_id'));
    }

    public function testSidebarAnchorCountMatchesVisibleItems(): void
    {
        $store = new MenuStore($this->db);
        $expected = 0;
        foreach ($store->visible(1, null) as $group) {
            $expected += count($group['items']);
        }

        $body = $this->withSession($this->session($this->adminId, 1, 1, null))->get('/dashboard')->getBody();
        self::assertGreaterThan(0, preg_match_all('#<ul class="treeview-menu"[^>]*>(.*?)</ul>#s', $body, $menus));
        self::assertSame($expected, substr_count(implode('', $menus[1]), '<a '));
    }

    public function testDashboardRendersCi3ContentHeaderInsideContentWrapper(): void
    {
        $body = $this->withSession($this->session($this->adminId, 1, 1, null))->get('/dashboard')->getBody();
        self::assertSame(1, preg_match(
            '#<div class="content-wrapper">\s*<section class="content-header">\s*<h1>\s*Dashboard\s*<small>Control panel</small>#s',
            $body,
        ));
        self::assertSame(1, substr_count($body, '<h1>'));
        self::assertStringNotContainsString('class="page-header"', $body);
        self::assertStringNotContainsString('id="page-title"', $body);
        self::assertSame(0, substr_count($body, 'id="dashboard-title"'));
    }

    public function testAnonymousLayoutRendersNoNavigationAndNeedsNoMenuTables(): void
    {
        // AC-2 / AC-5: an unauthenticated page that uses the layout must render no
        // navigation and must not depend on the menu tables. Drop them first so a
        // page that still returns 200 proves the anonymous path never queries them.
        foreach (['tbl_menu', 'group_menu', 'group_type'] as $table) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
        }
        $this->db->resetDataCache();

        $page = $this->get('/login');
        $page->assertStatus(200);
        $body = $page->getBody();
        // Mutation-check target: layout.php `if ($isLoggedIn)` gate. If removed, the
        // <nav> block renders for anonymous users and these assertions go red.
        self::assertStringNotContainsString('<nav', $body);
        self::assertStringNotContainsString('Main navigation', $body);
        self::assertStringNotContainsString('Sign out', $body);
        // Parity with CI3: the sign-in page carries the banner, the Tracking wordmark
        // and the Forgot Password entry point. Without the link there is no route into
        // password reset from the UI at all.
        self::assertStringContainsString('login-banner', $body);
        self::assertStringContainsString('>Tracking<', $body);
        self::assertStringContainsString('Forgot Password', $body);
        self::assertStringContainsString('forgot-password', $body);
    }

    public function testMenuListingUsesCi3TableEscapesRowsAndHasOnlyEditAction(): void
    {
        $this->db->table('group_menu')->insert([
            'group_type' => '1', 'name' => '<script>alert(1)</script>', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $escapedId = (int) $this->db->insertID();
        $body = (string) $this->withSession($this->session($this->adminId, 1, 1, null))->get('/menu')->getBody();
        $decoded = (string) preg_replace('/\s+/', ' ', html_entity_decode($body));

        self::assertStringContainsString('<table>', $body);
        self::assertStringContainsString('<th>ฺId</th> <th>Menu Group name</th> <th>Actions</th>', $decoded);
        self::assertStringContainsString('<td>1</td>', $body);
        self::assertStringContainsString('<td>CENTRAL</td>', $body);
        self::assertStringContainsString('<a href="/menu/1">Edit</a>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('<a href="/menu/' . $escapedId . '">Edit</a>', $body);
        self::assertStringContainsString('Add New', $body);
        self::assertStringContainsString('href="/menu/new"', $body);
        self::assertStringNotContainsString('>Delete<', $body);
        self::assertStringNotContainsString('title="Delete"', $body);
        self::assertStringNotContainsString('<form method="post"', $body);
        self::assertStringNotContainsString('type="reset"', $body);
    }

    public function testMenuAddPageShowsBlankFormWithResetButNoListing(): void
    {
        // AC-2: /menu/new renders a blank entity form with a reset button and no menu listing.
        $body = (string) $this->withSession($this->session($this->adminId, 1, 1, null))->get('/menu/new')->getBody();
        self::assertStringContainsString('<form method="post"', $body);
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('>Submit</button>', $body);
        self::assertStringContainsString('name="name" value=""', $body); // blank form
        // No listing: the BRANCH row link (id 4) only appears on the list page.
        self::assertStringNotContainsString('href="/menu/4"', $body);
    }

    public function testMenuEditPageShowsFilledFormWithCheckedGroupsButNoListing(): void
    {
        // AC-3: /menu/<id> renders the row's values (name + checked group_type boxes) with a
        // reset button and no listing. Group_menu id 1 has group_type '1,2'.
        $body = (string) $this->withSession($this->session($this->adminId, 1, 1, null))->get('/menu/1')->getBody();
        self::assertStringContainsString('value="CENTRAL"', $body);          // name prefilled
        self::assertStringContainsString('value="1" checked', $body);        // group 1 selected
        self::assertStringContainsString('value="2" checked', $body);        // group 2 selected
        self::assertStringNotContainsString('value="3" checked', $body);     // group 3 not selected
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('action="/menu/1"', $body);
        self::assertStringNotContainsString('href="/menu/4"', $body);        // no other-row link
    }

    public function testMenuAddPageDeniedWithoutAdminLikeEditPage(): void
    {
        // AC-5: guest and non-admin are refused /menu/new with the same result as /menu/<id>.
        // Guest denial comes from the web-auth filter (a response); non-admin denial comes from
        // the controller's assertAdmin() (a PageNotFoundException). The probe captures either so
        // the parity holds across both mechanisms. Dropping web-auth from menu/new flips the
        // guest branch from a filter response to a controller throw, breaking this parity.
        $probe = function (array $session, string $path): string {
            try {
                return 'status:' . $this->withSession($session)->get($path)->response()->getStatusCode();
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                return 'not-found';
            }
        };

        self::assertNotSame('status:200', $probe([], '/menu/new'));
        self::assertSame($probe([], '/menu/1'), $probe([], '/menu/new'));

        $branch = $this->session($this->branchId, 2, 4, 1);
        self::assertNotSame('status:200', $probe($branch, '/menu/new'));
        self::assertSame($probe($branch, '/menu/1'), $probe($branch, '/menu/new'));
    }

    public function testMenuListingSearchFormCarriesNoResetButton(): void
    {
        // AC-7: the search/filter form on the listing must not carry a reset button.
        $body = (string) $this->withSession($this->session($this->adminId, 1, 1, null))->get('/menu')->getBody();
        self::assertStringContainsString('<form method="get" action="/menu">', $body);
        self::assertStringNotContainsString('type="reset"', $body);
    }

    /** @return list<string> */
    private function runtimeAssetReferences(string $html): array
    {
        preg_match_all(
            '/<(?:script|img|link)\b[^>]*\b(?:src|href)\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s"\'=<>`]+))/i',
            $html,
            $tags,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        $references = [];
        foreach ($tags as $tag) {
            $references[] = $tag[1] ?? $tag[2] ?? $tag[3];
        }
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $styles);
        foreach ($styles[1] as $css) {
            $references = array_merge($references, $this->cssAssetReferences($css));
        }
        preg_match_all(
            '/\bstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i',
            $html,
            $styleAttributes,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        foreach ($styleAttributes as $styleAttribute) {
            $references = array_merge(
                $references,
                $this->cssAssetReferences($styleAttribute[1] ?? $styleAttribute[2] ?? $styleAttribute[3]),
            );
        }

        return $references;
    }

    /** @param list<string> $entrypoints @return list<string> */
    private function runtimeAssetClosure(array $entrypoints): array
    {
        $pending = [];
        foreach ($entrypoints as $reference) {
            $path = $this->runtimeAssetPath($reference, null);
            if ($path !== null) {
                $pending[] = $path;
            }
        }
        $seen = [];

        while ($pending !== []) {
            $path = array_pop($pending);
            if (isset($seen[$path])) {
                continue;
            }
            self::assertFileExists(ROOTPATH . $path, $path);
            $seen[$path] = true;
            if (! str_ends_with(strtolower($path), '.css')) {
                continue;
            }
            foreach ($this->cssAssetReferences((string) file_get_contents(ROOTPATH . $path)) as $reference) {
                $dependency = $this->runtimeAssetPath($reference, $path);
                if ($dependency !== null) {
                    $pending[] = $dependency;
                }
            }
        }

        $closure = array_keys($seen);
        sort($closure);

        return $closure;
    }

    /** @return list<string> */
    private function cssAssetReferences(string $css): array
    {
        preg_match_all(
            '/@import\s+(?:url\(\s*)?(?:"([^"]+)"|\'([^\']+)\'|([^\s;)]+))\s*\)?|url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s)]+))\s*\)/i',
            $css,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        $references = [];
        foreach ($matches as $match) {
            $reference = trim($match[1] ?? $match[2] ?? $match[3] ?? $match[4] ?? $match[5] ?? $match[6]);
            if ($reference !== '' && preg_match('/^(?:data:|#)/i', $reference) !== 1) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    private function runtimeAssetPath(string $reference, ?string $parent): ?string
    {
        self::assertDoesNotMatchRegularExpression('#^//#', $reference, $reference);
        $base = parse_url(base_url());
        $origin = parse_url($reference);
        self::assertIsArray($origin, $reference);
        if (isset($origin['host'])) {
            self::assertSame(strtolower((string) $base['scheme']), strtolower((string) ($origin['scheme'] ?? '')), $reference);
            self::assertSame(strtolower((string) $base['host']), strtolower((string) $origin['host']), $reference);
            self::assertSame($base['port'] ?? null, $origin['port'] ?? null, $reference);
        }
        $path = $origin['path'] ?? null;
        self::assertIsString($path, $reference);
        $path = rawurldecode($path);
        if ($path === '') {
            return null;
        }
        if ($parent === null && ! str_starts_with($path, '/assets/') && ! str_starts_with($path, '/uploads/web/')) {
            return null;
        }

        $candidate = str_starts_with($path, '/')
            ? 'public/' . ltrim($path, '/')
            : dirname((string) $parent) . '/' . $path;
        self::assertStringStartsWith('public/', $candidate, $reference);
        $segments = [];
        foreach (explode('/', $candidate) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                self::assertGreaterThan(1, count($segments), $reference);
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $resolved = implode('/', $segments);
        self::assertStringStartsWith('public/', $resolved, $reference);

        return $resolved;
    }

    /** @param list<string> $fragments */
    private function assertFragmentsInOrder(string $body, array $fragments): void
    {
        $offset = 0;
        foreach ($fragments as $fragment) {
            $position = strpos($body, $fragment, $offset);
            self::assertNotFalse($position, $fragment);
            $offset = $position + strlen($fragment);
        }
    }

    /**
     * @param list<array{group_id: int, group_name: string, icon: string, items: list<array{menu_name: string, menu_link: string}>}> $groups
     * @return list<array{menu_name: string, menu_link: string}>
     */
    private function groupItems(array $groups, int $groupId): array
    {
        foreach ($groups as $group) {
            if ($group['group_id'] === $groupId) {
                return $group['items'];
            }
        }

        return [];
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
