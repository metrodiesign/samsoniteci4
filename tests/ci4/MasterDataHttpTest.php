<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class MasterDataHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->adminId = (new ShadowUserStore($this->db))->create(
            'master-admin@example.invalid',
            password_hash('Synthetic passphrase', PASSWORD_DEFAULT),
            1,
            null,
        );
    }

    public function testAllTenMasterTypesSupportValidatedCrudAndRejectDuplicates(): void
    {
        foreach ($this->definitions() as $type => $definition) {
            $seed = $this->payload($type, 'SEED');
            unset($seed['csrf_test_name']);
            $seed['cdate'] = '2026-08-22 09:00:00';
            $this->db->table($definition['table'])->insert($seed);

            $listing = $this->getAsAdmin('/master/' . $type);
            $listing->assertStatus(200);
            $listing->assertSee($seed[$definition['label']]);

            $created = $this->payload($type, 'NEW');
            $this->postAsAdmin('/master/' . $type, $created)
                ->assertRedirectTo('/master/' . $type);
            self::assertSame(2, $this->db->table($definition['table'])->countAllResults(), $type);
            $newId = (int) $this->db->insertID();

            $duplicate = $created;
            $duplicate['csrf_test_name'] = service('security')->getHash();
            $this->postAsAdmin('/master/' . $type, $duplicate)->assertStatus(409);
            self::assertSame(2, $this->db->table($definition['table'])->countAllResults(), $type);

            $edit = $this->getAsAdmin('/master/' . $type . '/' . $newId);
            $edit->assertStatus(200);
            $edit->assertSee('NEW');

            $updated = $this->payload($type, 'UPDATED');
            $this->postAsAdmin('/master/' . $type . '/' . $newId, $updated)
                ->assertRedirectTo('/master/' . $type);
            self::assertSame(
                $updated[$definition['label']],
                $this->db->table($definition['table'])
                    ->where($definition['pk'], $newId)
                    ->get()
                    ->getRow($definition['label']),
                $type,
            );

            $invalid = $this->payload($type, 'INVALID');
            $invalid[$definition['required']] = '';
            $this->postAsAdmin('/master/' . $type, $invalid)->assertStatus(422);
            self::assertSame(2, $this->db->table($definition['table'])->countAllResults(), $type);

            $this->postAsAdmin('/master/' . $type . '/' . $newId . '/delete', [
                'csrf_test_name' => service('security')->getHash(),
            ])->assertStatus(204);
            self::assertSame(1, $this->db->table($definition['table'])->countAllResults(), $type);
        }
    }

    public function testReferencedMasterCannotBeDeleted(): void
    {
        $this->db->table('brand')->insert([
            'brand_details' => 'REFERENCED BRAND',
            'cdate'         => '2026-08-22 09:00:00',
        ]);
        $brandId = (int) $this->db->insertID();
        $name = $this->db->escapeIdentifiers($this->db->prefixTable('request_order'));
        $this->db->query("DROP TABLE IF EXISTS {$name}");
        $this->db->query("CREATE TABLE {$name} (request_id INTEGER PRIMARY KEY, detailBrandId INTEGER, action_status INTEGER, branchID INTEGER)");
        $this->db->resetDataCache();
        $this->db->table('request_order')->insert([
            'request_id'   => 91001,
            'detailBrandId' => $brandId,
            'action_status' => 1,
            'branchID'     => 1,
        ]);

        $this->postAsAdmin('/master/brand/' . $brandId . '/delete', [
            'csrf_test_name' => service('security')->getHash(),
        ])->assertStatus(409);

        self::assertSame(1, $this->db->table('brand')->where('brand_id', $brandId)->countAllResults());
        self::assertSame(1, $this->db->table('request_order')->where('request_id', 91001)->countAllResults());
    }

    public function testBranchTypeUploadAcceptsRealPngAndRejectsDisguisedFile(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'wp00c-png-');
        $bad = tempnam(sys_get_temp_dir(), 'wp00c-bad-');
        self::assertIsString($png);
        self::assertIsString($bad);
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        file_put_contents($bad, '<?php echo "not an image";');

        service('superglobals')->setFilesArray(['branch_type_image' => [
            'name' => 'branch.png', 'type' => 'image/png', 'tmp_name' => $png,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($png),
        ]]);
        $this->postAsAdmin('/master/branchtype', [
            'branch_type_details' => 'WITH IMAGE',
        ])->assertRedirectTo('/master/branchtype');

        $row = $this->db->table('branch_type')->where('branch_type_details', 'WITH IMAGE')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', (string) $row['branch_type_image']);
        $stored = WRITEPATH . 'uploads/branch-types/' . $row['branch_type_image'];
        self::assertFileExists($stored);
        self::assertSame(0, fileperms($stored) & 0111);
        self::assertFalse(str_starts_with(realpath($stored) ?: '', realpath(PUBLICPATH) ?: PUBLICPATH));
        $before = glob(WRITEPATH . 'uploads/branch-types/*') ?: [];

        service('superglobals')->setFilesArray(['branch_type_image' => [
            'name' => 'payload.png', 'type' => 'image/png', 'tmp_name' => $bad,
            'error' => UPLOAD_ERR_OK, 'size' => filesize($bad),
        ]]);
        $this->postAsAdmin('/master/branchtype', [
            'branch_type_details' => 'DISGUISED',
        ])->assertStatus(422);
        self::assertSame(0, $this->db->table('branch_type')->where('branch_type_details', 'DISGUISED')->countAllResults());
        self::assertSame($before, glob(WRITEPATH . 'uploads/branch-types/*') ?: []);

        service('superglobals')->setFilesArray([]);
    }

    public function testBookStatusZeroIsSavedAndReadBack(): void
    {
        $payload = $this->payload('book', 'ZER');
        $payload['status'] = '0';
        $this->postAsAdmin('/master/book', $payload)->assertRedirectTo('/master/book');

        $row = $this->db->table('book')->where('book_detail', 'ZER')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['status']);

        $edit = $this->getAsAdmin('/master/book/' . (int) $row['book_id']);
        $edit->assertStatus(200);
        $edit->assertSee('value="0"');
    }

    public function testStatusTypeSuccessZeroIsSaved(): void
    {
        $payload = $this->payload('statustype', 'ZEROSUCCESS');
        $payload['success'] = '0';
        $this->postAsAdmin('/master/statustype', $payload)->assertRedirectTo('/master/statustype');

        $row = $this->db->table('tracking_status')->where('description_en', 'ZEROSUCCESS')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['success']);
    }

    public function testListingRendersEveryDefinitionFieldAsColumn(): void
    {
        $seed = $this->payload('book', 'COL');
        unset($seed['csrf_test_name']);
        $seed['status'] = 0;
        $seed['cdate'] = '2026-08-22 09:00:00';
        $this->db->table('book')->insert($seed);

        $listing = $this->getAsAdmin('/master/book');
        $listing->assertStatus(200);
        $listing->assertSee('book_id');
        $listing->assertSee('branch_id');
        $listing->assertSee('bunber_limit');
        $listing->assertSee('status');
    }

    public function testListingRendersDeleteControlPerRow(): void
    {
        $seed = $this->payload('brand', 'DELCTRL');
        unset($seed['csrf_test_name']);
        $seed['cdate'] = '2026-08-22 09:00:00';
        $this->db->table('brand')->insert($seed);
        $brandId = (int) $this->db->insertID();

        $listing = $this->getAsAdmin('/master/brand');
        $listing->assertStatus(200);
        $listing->assertSee('/master/brand/' . $brandId . '/delete');
    }

    private function getAsAdmin(string $path)
    {
        return $this->withSession($this->session())->get($path);
    }

    /** @param array<string, string> $payload */
    private function postAsAdmin(string $path, array $payload)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->withSession($this->session())->post($path, $payload);
    }

    /** @return array<string, int|bool|null> */
    private function session(): array
    {
        return [
            'userId'         => $this->adminId,
            'role'           => 1,
            'BranchID'       => null,
            'sessionVersion' => 1,
            'isLoggedIn'     => true,
        ];
    }

    /** @return array<string, string> */
    private function payload(string $type, string $label): array
    {
        $payloads = [
            'branch' => [
                'branch_type'     => '1',
                'branch_user_name' => 'wp00c-' . strtolower($label),
                'branch_name'     => $label,
                'branch_details'  => $label . ' DETAILS',
                'default_suffix'  => 'WPC',
                'book_order'      => 'WPC',
                'customer_ref'    => 'WPC',
            ],
            'branchtype' => ['branch_type_details' => $label],
            'statustype' => [
                'description_th' => 'สถานะ ' . $label,
                'description_en' => $label,
                'success' => '1',
            ],
            'producttype' => ['type_details' => $label],
            'book' => ['branch_id' => '1', 'book_detail' => substr($label, 0, 3), 'status' => '1', 'bunber_limit' => '999'],
            'brand' => ['brand_details' => $label],
            'condition' => ['condition_details' => $label],
            'estimateprice' => ['estimateprice_details' => $label],
            'fixed' => ['fixed_details' => $label],
            'provider' => ['provider_name' => $label, 'provider_tel' => '0000000000', 'provider_datail' => $label . ' DETAILS'],
        ];

        return ['csrf_test_name' => service('security')->getHash(), ...$payloads[$type]];
    }

    /** @return array<string, array{table: string, pk: string, label: string, required: string}> */
    private function definitions(): array
    {
        return [
            'branch'        => ['table' => 'branch', 'pk' => 'branch_id', 'label' => 'branch_name', 'required' => 'branch_name'],
            'branchtype'    => ['table' => 'branch_type', 'pk' => 'branch_type_id', 'label' => 'branch_type_details', 'required' => 'branch_type_details'],
            'statustype'    => ['table' => 'tracking_status', 'pk' => 'status_id', 'label' => 'description_en', 'required' => 'description_en'],
            'producttype'   => ['table' => 'type', 'pk' => 'type_id', 'label' => 'type_details', 'required' => 'type_details'],
            'book'          => ['table' => 'book', 'pk' => 'book_id', 'label' => 'book_detail', 'required' => 'book_detail'],
            'brand'         => ['table' => 'brand', 'pk' => 'brand_id', 'label' => 'brand_details', 'required' => 'brand_details'],
            'condition'     => ['table' => 'condition', 'pk' => 'condition_id', 'label' => 'condition_details', 'required' => 'condition_details'],
            'estimateprice' => ['table' => 'estimateprice', 'pk' => 'estimateprice_id', 'label' => 'estimateprice_details', 'required' => 'estimateprice_details'],
            'fixed'         => ['table' => 'fixed', 'pk' => 'fixed_id', 'label' => 'fixed_details', 'required' => 'fixed_details'],
            'provider'      => ['table' => 'provider', 'pk' => 'provider_id', 'label' => 'provider_name', 'required' => 'provider_name'],
        ];
    }

    private function createTables(): void
    {
        foreach (['request_order', 'request_order_delete', 'tbl_users', 'status_log', 'uploadstaus'] as $table) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
        }
        $tables = [
            'branch'        => 'branch_id INTEGER PRIMARY KEY AUTOINCREMENT, branch_type INTEGER NOT NULL, branch_user_name VARCHAR(100), branch_name VARCHAR(250) NOT NULL, branch_details VARCHAR(250) NOT NULL, default_suffix VARCHAR(10) NOT NULL, book_order VARCHAR(10) NOT NULL, customer_ref VARCHAR(50), cdate DATETIME NOT NULL, udate DATETIME',
            'branch_type'   => 'branch_type_id INTEGER PRIMARY KEY AUTOINCREMENT, branch_type_details VARCHAR(250) NOT NULL, branch_type_image VARCHAR(250), cdate DATETIME NOT NULL',
            'tracking_status' => 'status_id INTEGER PRIMARY KEY AUTOINCREMENT, description_th VARCHAR(250) NOT NULL, description_en VARCHAR(250) NOT NULL, success INTEGER NOT NULL, cdate DATETIME NOT NULL',
            'type'          => 'type_id INTEGER PRIMARY KEY AUTOINCREMENT, type_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'book'          => 'book_id INTEGER PRIMARY KEY AUTOINCREMENT, branch_id INTEGER NOT NULL, book_detail VARCHAR(3) NOT NULL, status INTEGER NOT NULL, bunber_limit INTEGER, cdate DATETIME NOT NULL',
            'brand'         => 'brand_id INTEGER PRIMARY KEY AUTOINCREMENT, brand_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'condition'     => 'condition_id INTEGER PRIMARY KEY AUTOINCREMENT, condition_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'estimateprice' => 'estimateprice_id INTEGER PRIMARY KEY AUTOINCREMENT, estimateprice_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'fixed'         => 'fixed_id INTEGER PRIMARY KEY AUTOINCREMENT, fixed_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'provider'      => 'provider_id INTEGER PRIMARY KEY AUTOINCREMENT, provider_name VARCHAR(250) NOT NULL, provider_tel VARCHAR(50) NOT NULL, provider_datail TEXT NOT NULL, cdate DATETIME NOT NULL',
        ];
        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->resetDataCache();
    }
}
