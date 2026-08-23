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
        $this->adminId = (new ShadowUserStore($this->db))->create(
            'background-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null,
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
