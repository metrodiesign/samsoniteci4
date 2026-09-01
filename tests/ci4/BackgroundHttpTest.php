<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class BackgroundHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private int $branchId;
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        $name = $this->db->escapeIdentifiers($this->db->prefixTable('tbl_background_web'));
        $columns = implode(', ', array_map(
            static fn (string $field): string => $field . ' VARCHAR(250)',
            $this->fields(),
        ));
        $this->db->query("DROP TABLE IF EXISTS {$name}");
        $this->db->query("CREATE TABLE {$name} (id INTEGER PRIMARY KEY AUTOINCREMENT, {$columns}, status INTEGER, date DATETIME NOT NULL)");
        $this->db->resetDataCache();
        $users = new ShadowUserStore($this->db);
        $this->adminId = $users->create(
            'background-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null,
        );
        $this->branchId = $users->create(
            'background-branch@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1,
        );
        $png = tempnam(sys_get_temp_dir(), 'wp00c-bg-');
        self::assertIsString($png);
        $this->png = $png;
        file_put_contents($this->png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }

    public function testBackgroundCrudKeepsPrivateImageRouteWhileCi3PublicViewOwnsItsMarkup(): void
    {
        $this->withSession($this->session())->get('/backgrounds')->assertStatus(200);

        $created = $this->postWithImage('/backgrounds', 'hero.png', ['status' => '1']);
        $created->assertRedirectTo('/backgrounds');
        $row = $this->db->table('tbl_background_web')->get()->getRowArray();
        self::assertNotNull($row);
        $filename = (string) $row['image_track_laptop'];
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $filename);
        self::assertFileExists(WRITEPATH . 'uploads/backgrounds/' . $filename);

        $public = $this->get('/track');
        $public->assertStatus(200);
        // Pinned CI3 en/track.php does not inject the CMS filename into the form markup.
        $public->assertDontSee('/background-image/' . $filename);
        $image = $this->get('/background-image/' . $filename);
        $image->assertStatus(200);
        self::assertSame('image/png', $image->response()->getHeaderLine('Content-Type'));
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $image->response()->getBody());

        $id = (int) $row['id'];
        $this->postWithoutImage('/backgrounds/' . $id, ['status' => '2'])->assertRedirectTo('/backgrounds');
        $this->get('/track')->assertDontSee('/background-image/' . $filename);

        $beforeFiles = glob(WRITEPATH . 'uploads/backgrounds/*') ?: [];
        $this->postWithImage('/backgrounds', 'hero.jpg', ['status' => '1'])->assertStatus(422);
        self::assertSame(1, $this->db->table('tbl_background_web')->countAllResults());
        self::assertSame($beforeFiles, glob(WRITEPATH . 'uploads/backgrounds/*') ?: []);

        $this->postWithoutImage('/backgrounds/' . $id . '/delete', [])->assertStatus(204);
        self::assertSame(0, $this->db->table('tbl_background_web')->countAllResults());
        self::assertFileDoesNotExist(WRITEPATH . 'uploads/backgrounds/' . $filename);
        $this->get('/background-image/' . $filename)->assertStatus(404);
        service('superglobals')->setFilesArray([]);
    }

    public function testC3LabelParityAddAndEditModesUseDistinctCi3Text(): void
    {
        // add mode: /backgrounds/new shows the add-mode CI3 labels (t3 AC-3); the form moved
        // off the listing in t5 so the add-mode labels now live on the dedicated add page.
        $add = $this->withSession($this->session())->get('/backgrounds/new');
        $add->assertStatus(200);
        $add->assertSee('image track aptop (en)');    // CI3 typo "aptop" preserved
        $add->assertSee('image track mobile  (en)');  // CI3 doubled space preserved
        $add->assertSee('image contact laptop  (en)');
        $add->assertDontSee('>image_track_laptop<');   // AC-1: no raw column as label text
        $add->assertDontSee('laptop size 1920px (en)'); // edit-mode text absent in add mode

        // edit mode: /backgrounds/<id> shows the edit-mode CI3 labels (AC-3).
        $this->db->table('tbl_background_web')->insert(['status' => 1, 'date' => '2026-08-25 00:00:00']);
        $id = (int) $this->db->insertID();
        $edit = $this->withSession($this->session())->get('/backgrounds/' . $id);
        $edit->assertStatus(200);
        $edit->assertSee('laptop size 1920px (en)');
        $edit->assertSee('mobile size 480px (en)');
        $edit->assertDontSee('image track aptop (en)'); // add-mode text absent in edit mode
    }

    public function testBackgroundListingUsesCi3TableRawPreviewPathsAndOnlyEditAction(): void
    {
        $track = str_repeat('a', 32) . '.png';
        $trackStatus = str_repeat('b', 32) . '.png';
        $contact = str_repeat('c', 32) . '.png';
        $this->db->table('tbl_background_web')->insertBatch([
            [
                'image_track_laptop' => $track,
                'image_trackstatus_laptop' => $trackStatus,
                'image_contact_laptop' => $contact,
                'status' => 1,
                'date' => '2026-08-25 00:00:00',
            ],
            [
                'image_track_laptop' => '../../secret.png',
                'image_trackstatus_laptop' => '',
                'image_contact_laptop' => 'not-a-contract-name.png',
                'status' => 2,
                'date' => '2026-08-25 00:00:00',
            ],
            [
                'image_track_laptop' => null,
                'image_trackstatus_laptop' => null,
                'image_contact_laptop' => null,
                'status' => 9,
                'date' => '2026-08-25 00:00:00',
            ],
        ]);
        $body = (string) $this->withSession($this->session())->get('/backgrounds')->getBody();
        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertMatchesRegularExpression(
            '/<th>\s*ฺId\s*<\/th>\s*<th>\s*Track\s*<\/th>\s*<th>\s*Tracks tatus\s*<\/th>'
                . '\s*<th>\s*Contact\s*<\/th>\s*<th>\s*Status\s*<\/th>\s*<th class="text-center">\s*Actions\s*<\/th>/s',
            $decoded,
        );
        // Pinned CI3 master/background_web.php concatenates base_url() with the stored value.
        self::assertStringContainsString('src="http://example.invalid/' . $track . '"', $body);
        self::assertStringContainsString('src="http://example.invalid/' . $trackStatus . '"', $body);
        self::assertStringContainsString('src="http://example.invalid/' . $contact . '"', $body);
        self::assertStringContainsString('src="http://example.invalid/../../secret.png"', $body);
        self::assertStringContainsString('src="http://example.invalid/not-a-contract-name.png"', $body);
        self::assertSame(1, substr_count($body, '<td>Publishing</td>'));
        self::assertSame(2, substr_count($body, '<td>Unpublish</td>'));
        self::assertStringContainsString('href="http://example.invalid/editBackgroundOld/1" title="Edit"', $body);
        self::assertStringNotContainsString('Add New', $body);
        self::assertStringNotContainsString('href="/backgrounds/new"', $body);
        self::assertStringNotContainsString('Delete', $body);
        self::assertStringNotContainsString('/delete', $body);
        self::assertStringNotContainsString('<form method="post"', $body);
        self::assertStringNotContainsString('type="reset"', $body);
    }

    public function testBackgroundAddPageShowsFormWithResetButNoListing(): void
    {
        // AC-4 (AC-2 analogue): /backgrounds/new renders an entity form with a reset button
        // and no listing, even when rows exist.
        $this->db->table('tbl_background_web')->insert(['status' => 1, 'date' => '2026-08-25 00:00:00']);
        $id = (int) $this->db->insertID();
        $body = (string) $this->withSession($this->session())->get('/backgrounds/new')->getBody();
        self::assertStringContainsString('<form role="form" id="addbackground" action="http://example.invalid/addBackground"', $body);
        self::assertStringContainsString('method="post"', $body);
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('type="submit" class="btn btn-primary" value="Submit"', $body);
        self::assertStringNotContainsString('href="/backgrounds/' . $id . '"', $body); // no row link
    }

    public function testBackgroundEditPageShowsFormWithResetButNoListing(): void
    {
        // AC-4 (AC-3 analogue): /backgrounds/<id> renders the entity form with a reset button
        // and no other-row listing link.
        $this->db->table('tbl_background_web')->insert(['status' => 1, 'date' => '2026-08-25 00:00:00']);
        $edited = (int) $this->db->insertID();
        $this->db->table('tbl_background_web')->insert(['status' => 1, 'date' => '2026-08-25 00:00:00']);
        $other = (int) $this->db->insertID();
        $body = (string) $this->withSession($this->session())->get('/backgrounds/' . $edited)->getBody();
        self::assertStringContainsString('<form role="form" id="editBackground" action="http://example.invalid/editBackground"', $body);
        self::assertStringContainsString('method="post"', $body);
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('value="' . $edited . '" name="background_id" id="background_id"', $body);
        self::assertStringNotContainsString('href="/backgrounds/' . $other . '"', $body); // no other-row link
    }

    public function testBackgroundAddPageDeniedWithoutAdminLikeEditPage(): void
    {
        // AC-5: guest and non-admin are refused /backgrounds/new with the same result as
        // /backgrounds/<id>.
        $this->db->table('tbl_background_web')->insert(['status' => 1, 'date' => '2026-08-25 00:00:00']);
        $id = (int) $this->db->insertID();

        // Guest denial is the web-auth filter (a response); non-admin denial is the controller's
        // assertAdmin() (a PageNotFoundException). The probe captures either so the parity holds
        // across both mechanisms; dropping web-auth from backgrounds/new breaks the guest branch.
        $probe = function (array $session, string $path): string {
            try {
                return 'status:' . $this->withSession($session)->get($path)->response()->getStatusCode();
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                return 'not-found';
            }
        };

        self::assertNotSame('status:200', $probe([], '/backgrounds/new'));
        self::assertSame($probe([], '/backgrounds/' . $id), $probe([], '/backgrounds/new'));

        // A real seeded role-2 user (matchesActiveSession passes the web-auth filter) so the
        // denial comes from the controller's assertAdmin() throw, not a filter 401. Using adminId
        // here would mismatch role_id in the DB and get cut at the filter before assertAdmin() runs.
        $branch = [
            'userId' => $this->branchId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
        self::assertSame('not-found', $probe($branch, '/backgrounds/new'));
        self::assertSame($probe($branch, '/backgrounds/' . $id), $probe($branch, '/backgrounds/new'));
    }

    public function testLegacyBackgroundListingUsesCi3AuthenticationRolesMethodsAliasesAndOrder(): void
    {
        for ($id = 1; $id <= 3; $id++) {
            $this->db->table('tbl_background_web')->insert([
                'image_track_laptop' => 'SYNTHETIC-TRACK-' . $id . '.png',
                'status' => $id === 1 ? 1 : 0,
                'date' => '2026-08-25 0' . $id . ':00:00',
            ]);
        }
        $users = new ShadowUserStore($this->db);
        $providerId = $users->create(
            'background-provider@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 3, 1,
        );
        $profiles = [
            $this->session(),
            [
                'userId' => $this->branchId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
                'sessionVersion' => 1, 'isLoggedIn' => true,
            ],
            [
                'userId' => $providerId, 'role' => 3, 'GroupID' => 4, 'BranchID' => 1,
                'sessionVersion' => 1, 'isLoggedIn' => true,
            ],
        ];

        $this->get('/BackgroundListing')->assertRedirectTo('/login');
        foreach ($profiles as $session) {
            foreach ([
                '/BackgroundListing',
                '/BackgroundListing/50',
                '/background_web/BackgroundListing',
                '/Background_web/BackgroundListing/50',
                '/BACKGROUND_WEB/BACKGROUNDLISTING/legacy/50',
            ] as $path) {
                $body = (string) $this->withSession($session)->get($path)->getBody();
                self::assertLessThan(strpos($body, 'SYNTHETIC-TRACK-2.png'), strpos($body, 'SYNTHETIC-TRACK-1.png'));
                self::assertLessThan(strpos($body, 'SYNTHETIC-TRACK-3.png'), strpos($body, 'SYNTHETIC-TRACK-2.png'));
                $this->withSession($session)->post($path, [])->assertStatus(200);
                $this->withSession($session)->call('head', $path)->assertStatus(200);
                $this->withSession($session)->call('options', $path)->assertStatus(200);
                $this->withSession($session)->call('put', $path)->assertStatus(200);
                $this->withSession($session)->call('patch', $path)->assertStatus(200);
                $this->withSession($session)->call('delete', $path)->assertStatus(200);
            }
        }
    }

    public function testLegacyBackgroundListingDoesNotApplyModernHundredRowLimit(): void
    {
        $rows = [];
        for ($id = 1; $id <= 105; $id++) {
            $rows[] = [
                'image_track_laptop' => sprintf('SYNTHETIC-BACKGROUND-%03d.png', $id),
                'status' => $id % 2,
                'date' => '2026-08-25 05:00:00',
            ];
        }
        $this->db->table('tbl_background_web')->insertBatch($rows);

        $body = (string) $this->withSession($this->session())->get('/BackgroundListing')->getBody();
        $first = strpos($body, 'SYNTHETIC-BACKGROUND-001.png');
        $last = strpos($body, 'SYNTHETIC-BACKGROUND-105.png');
        self::assertNotFalse($first);
        self::assertNotFalse($last);
        self::assertLessThan($last, $first);
        self::assertStringNotContainsString('<ul class="pagination">', $body);
    }

    public function testLegacyBackgroundFormsUseCi3RolesMethodsAliasesMissingAndUnknownIds(): void
    {
        $this->db->table('tbl_background_web')->insert([
            'image_track_laptop' => 'SYNTHETIC-EDIT.png',
            'status' => 1,
            'date' => '2026-08-25 04:00:00',
        ]);
        $id = (int) $this->db->insertID();
        $branch = [
            'userId' => $this->branchId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];

        $this->get('/BackgroundNew')->assertRedirectTo('/login');
        $this->get('/editBackgroundOld/' . $id)->assertRedirectTo('/login');
        foreach ([$this->session(), $branch] as $session) {
            foreach (['/BackgroundNew', '/background_web/BackgroundNew', '/BACKGROUND_WEB/BACKGROUNDNEW/legacy'] as $path) {
                foreach (['get', 'post', 'head', 'options', 'put', 'patch', 'delete'] as $method) {
                    $this->withSession($session)->call($method, $path)->assertStatus(200);
                }
            }
            foreach (['get', 'post', 'head', 'options', 'put', 'patch', 'delete'] as $method) {
                $missing = $this->withSession($session)->call($method, '/editBackgroundOld');
                $missing->assertRedirectTo('/BackgroundListing');
                $missing->assertStatus($method === 'get' ? 307 : 303);
            }
            foreach ([
                '/editBackgroundOld/' . $id,
                '/editBackgroundOld/999999',
                '/background_web/editBackgroundOld/' . $id,
                '/BACKGROUND_WEB/EDITBACKGROUNDOLD/999999/legacy',
            ] as $path) {
                foreach (['get', 'post', 'head', 'options', 'put', 'patch', 'delete'] as $method) {
                    $response = $this->withSession($session)->call($method, $path);
                    $response->assertStatus(200);
                    if ($method === 'get' && str_contains($path, '999999')) {
                        self::assertStringContainsString(
                            'value="" name="background_id" id="background_id"',
                            (string) $response->getBody(),
                        );
                    }
                }
            }
        }
    }

    public function testLegacyBackgroundStatusOnlyCreateUpdateAndDeleteMatchCi3ContractsForAllRoles(): void
    {
        $branch = [
            'userId' => $this->branchId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];

        $adminCreate = $this->withSession($this->session())->post('/addBackground', [
            'csrf_test_name' => service('security')->getHash(),
            'status' => '0',
        ]);
        $adminCreate->assertRedirectTo('/BackgroundListing');
        $adminRow = $this->db->table('tbl_background_web')->orderBy('id', 'ASC')->get()->getRowArray();
        self::assertNotNull($adminRow);
        self::assertSame(0, (int) $adminRow['status']);
        self::assertNull($adminRow['image_track_laptop']);

        $branchCreate = $this->withSession($branch)->post('/BACKGROUND_WEB/ADDBACKGROUND/legacy', [
            'csrf_test_name' => service('security')->getHash(),
            'status' => '1',
        ]);
        $branchCreate->assertRedirectTo('/BackgroundListing');
        self::assertSame(2, $this->db->table('tbl_background_web')->countAllResults());

        $id = (int) $adminRow['id'];
        $updated = $this->withSession($branch)->post('/background_web/editBackground/legacy', [
            'csrf_test_name' => service('security')->getHash(),
            'background_id' => (string) $id,
            'status' => '1',
        ]);
        $updated->assertRedirectTo('/BackgroundListing');
        self::assertSame(1, (int) $this->db->table('tbl_background_web')->where('id', $id)->get()->getRow('status'));

        $deleted = $this->withSession($branch)->post('/deleteBackground', [
            'csrf_test_name' => service('security')->getHash(),
            'Backgroundid' => (string) $id,
        ]);
        $deleted->assertStatus(200);
        self::assertSame('{"status":true}', (string) $deleted->response()->getBody());
        self::assertSame('text/html; charset=UTF-8', $deleted->response()->getHeaderLine('Content-Type'));
        self::assertSame(1, $this->db->table('tbl_background_web')->countAllResults());

        $replayed = $this->withSession($branch)->post('/background_web/deleteBackground/legacy', [
            'csrf_test_name' => service('security')->getHash(),
            'Backgroundid' => (string) $id,
        ]);
        $replayed->assertStatus(200);
        self::assertSame('{"status":false}', (string) $replayed->response()->getBody());
    }

    public function testLegacyBackgroundPngUploadUsesCi3PreviewUrlWithPrivateValidatedStorage(): void
    {
        $legacyImages = [
            'image_track_laptop' => 'track_laptop.png',
            'image_track_mobile' => 'track_mobile.png',
            'image_trackstatus_laptop' => 'trackstatus_laptop.png',
            'image_trackstatus_mobile' => 'trackstatus_mobile.png',
            'image_contact_laptop' => 'contact_laptop.png',
            'image_contact_mobile' => 'contact_mobile.png',
        ];
        $files = [];
        foreach ($legacyImages as $field => $alias) {
            $files[$field] = [
                'name' => $alias, 'type' => 'image/png', 'tmp_name' => $this->png,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($this->png),
            ];
        }
        service('superglobals')->setFilesArray($files);
        $created = $this->postWithoutImage('/addBackground', ['status' => '1'], false);
        $created->assertRedirectTo('/BackgroundListing');
        $row = $this->db->table('tbl_background_web')->get()->getRowArray();
        self::assertNotNull($row);

        $listing = (string) $this->withSession($this->session())->get('/BackgroundListing')->getBody();
        $edit = (string) $this->withSession($this->session())
            ->get('/editBackgroundOld/' . (int) $row['id'])
            ->getBody();
        foreach ($legacyImages as $field => $alias) {
            self::assertSame('uploads/web/' . $alias, $row[$field]);
            self::assertStringContainsString('src="' . base_url('uploads/web/' . $alias) . '"', $edit);
            $image = $this->get('/uploads/web/' . $alias);
            $image->assertStatus(200);
            self::assertSame('image/png', $image->response()->getHeaderLine('Content-Type'));
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $image->response()->getBody());
            @unlink(WRITEPATH . 'uploads/backgrounds/legacy-' . $alias);
        }
        foreach (['track_laptop.png', 'trackstatus_laptop.png', 'contact_laptop.png'] as $alias) {
            self::assertStringContainsString('src="' . base_url('uploads/web/' . $alias) . '"', $listing);
        }
        service('superglobals')->setFilesArray([]);
    }

    public function testLegacyBackgroundUppercasePngExtensionKeepsCi3PreviewPathCase(): void
    {
        $created = $this->postWithImage('/addBackground', 'hero.PNG', ['status' => '1']);
        $created->assertRedirectTo('/BackgroundListing');
        $row = $this->db->table('tbl_background_web')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('uploads/web/track_laptop.PNG', $row['image_track_laptop']);
        $listing = (string) $this->withSession($this->session())->get('/BackgroundListing')->getBody();
        self::assertStringContainsString(
            'src="' . base_url('uploads/web/track_laptop.PNG') . '"',
            $listing,
        );
        $this->get('/uploads/web/track_laptop.PNG')->assertStatus(200);

        @unlink(WRITEPATH . 'uploads/backgrounds/legacy-track_laptop.PNG');
        service('superglobals')->setFilesArray([]);
    }

    public function testLegacyBackgroundValidatedJpegUploadKeepsCi3ExtensionAndServesReencodedJpeg(): void
    {
        $jpeg = tempnam(sys_get_temp_dir(), 'wp00c-bg-jpeg-');
        self::assertIsString($jpeg);
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(\GdImage::class, $image);
        imagejpeg($image, $jpeg, 90);
        imagedestroy($image);
        service('superglobals')->setFilesArray(['image_track_laptop' => [
            'name' => 'hero.JPG', 'type' => 'image/jpeg', 'tmp_name' => $jpeg,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($jpeg),
        ]]);

        $created = $this->postWithoutImage('/addBackground', ['status' => '1'], false);
        $created->assertRedirectTo('/BackgroundListing');
        $row = $this->db->table('tbl_background_web')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('uploads/web/track_laptop.JPG', $row['image_track_laptop']);
        $listing = (string) $this->withSession($this->session())->get('/BackgroundListing')->getBody();
        self::assertStringContainsString('src="' . base_url('uploads/web/track_laptop.JPG') . '"', $listing);
        $served = $this->get('/uploads/web/track_laptop.JPG');
        $served->assertStatus(200);
        self::assertSame('image/jpeg', $served->response()->getHeaderLine('Content-Type'));
        self::assertStringStartsWith("\xFF\xD8", (string) $served->response()->getBody());

        @unlink($jpeg);
        @unlink(WRITEPATH . 'uploads/backgrounds/legacy-track_laptop.JPG');
        service('superglobals')->setFilesArray([]);
    }

    /** @param array<string, string> $payload */
    private function postWithImage(string $path, string $clientName, array $payload)
    {
        service('superglobals')->setFilesArray(['image_track_laptop' => [
            'name' => $clientName, 'type' => 'image/png', 'tmp_name' => $this->png,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($this->png),
        ]]);

        return $this->postWithoutImage($path, $payload, false);
    }

    /** @param array<string, string> $payload */
    private function postWithoutImage(string $path, array $payload, bool $clearFiles = true)
    {
        if ($clearFiles) {
            service('superglobals')->setFilesArray([]);
        }
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session())->post($path, $payload);
    }

    /** @return array<string, int|bool|null> */
    private function session(): array
    {
        return [
            'userId' => $this->adminId, 'role' => 1, 'GroupID' => 1, 'BranchID' => null,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
    }

    /** @return list<string> */
    private function fields(): array
    {
        return [
            'image_track_laptop', 'image_track_mobile', 'image_trackstatus_laptop', 'image_trackstatus_mobile',
            'image_contact_laptop', 'image_contact_mobile', 'image_track_laptop_th', 'image_track_mobile_th',
            'image_trackstatus_laptop_th', 'image_trackstatus_mobile_th', 'image_contact_laptop_th', 'image_contact_mobile_th',
        ];
    }
}
