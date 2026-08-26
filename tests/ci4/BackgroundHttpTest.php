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

    public function testBackgroundCrudDrivesPublicPageAndRejectsAlternateExtension(): void
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
        $public->assertSee('/background-image/' . $filename);
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

    public function testBackgroundListingUsesCi3TableSafePreviewsAndOnlyEditAction(): void
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
        $decoded = (string) preg_replace('/\s+/', ' ', html_entity_decode($body));

        self::assertStringContainsString(
            '<th>ฺId</th> <th>Track</th> <th>Tracks tatus</th> <th>Contact</th> <th>Status</th> <th>Actions</th>',
            $decoded,
        );
        self::assertStringContainsString('/background-image/' . $track, $body);
        self::assertStringContainsString('/background-image/' . $trackStatus, $body);
        self::assertStringContainsString('/background-image/' . $contact, $body);
        self::assertSame(3, substr_count($body, '/background-image/'));
        self::assertStringNotContainsString('../../secret.png', $body);
        self::assertStringNotContainsString('not-a-contract-name.png', $body);
        self::assertSame(1, substr_count($body, '<td>Publishing</td>'));
        self::assertSame(2, substr_count($body, '<td>Unpublish</td>'));
        self::assertStringContainsString('<a href="/backgrounds/1">Edit</a>', $body);
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
        self::assertStringContainsString('<form method="post"', $body);
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('>Submit</button>', $body);
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
        self::assertStringContainsString('<form method="post"', $body);
        self::assertStringContainsString('type="reset"', $body);
        self::assertStringContainsString('action="/backgrounds/' . $edited . '"', $body);
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
