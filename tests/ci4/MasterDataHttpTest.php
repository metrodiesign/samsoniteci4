<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Master\MasterCatalog;
use App\Master\MasterDataStore;
use App\Presentation\LegacyViewRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
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

    public function testMasterDataSearchMatchesJoinedAndExtraColumnsWithoutLeak(): void
    {
        $store = new MasterDataStore($this->db);

        // branch: joined branch_type_details and local default_suffix are searchable (AC-6).
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 7, 'branch_type_details' => 'BTYPE ZED', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $this->db->table('branch')->insert([
            'branch_id' => 3, 'branch_type' => 7, 'branch_name' => 'BR NORTH',
            'branch_details' => 'D', 'default_suffix' => 'ZSUF', 'book_order' => 'A',
            'cdate' => '2026-08-22 09:00:00',
        ]);
        foreach (['BTYPE ZED', 'ZSUF'] as $term) {
            $rows = $store->all('branch', $term);
            self::assertCount(1, $rows, $term);
            self::assertSame(3, (int) $rows[0]['branch_id'], $term);
            // AC-8: joined columns do not leak into the result shape.
            self::assertArrayNotHasKey('branch_type_details', $rows[0], $term);
        }

        // statustype: description_th is searchable alongside the label description_en (AC-6).
        $this->db->table('tracking_status')->insert([
            'status_id' => 5, 'description_th' => 'THAI ZED', 'description_en' => 'EN ZED',
            'success' => 1, 'cdate' => '2026-08-22 09:00:00',
        ]);
        $statusRows = $store->all('statustype', 'THAI ZED');
        self::assertCount(1, $statusRows);
        self::assertSame(5, (int) $statusRows[0]['status_id']);

        // book: joined branch_name is searchable (AC-6).
        $this->db->table('book')->insert([
            'book_id' => 9, 'branch_id' => 3, 'book_detail' => 'ZBK', 'status' => 1,
            'bunber_limit' => 1, 'cdate' => '2026-08-22 09:00:00',
        ]);
        $bookRows = $store->all('book', 'BR NORTH');
        self::assertCount(1, $bookRows);
        self::assertSame(9, (int) $bookRows[0]['book_id']);
        self::assertArrayNotHasKey('branch_name', $bookRows[0]);
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

    public function testCi3BranchTypePayloadCreatesUpdatesAndRerendersExactValidation(): void
    {
        $invalid = $this->postAsAdmin('/addNewBranchtype', ['branch_type_name' => '']);
        $invalid->assertStatus(200);
        $invalid->assertSee('id="addBranchtype"');
        $invalid->assertSee('The Branch Name field is required.');
        $invalid->assertDontSee('Invalid master data.');
        self::assertSame(0, $this->db->table('branch_type')->countAllResults());

        $created = $this->postAsAdmin('/addNewBranchtype', ['branch_type_name' => 'SOURCE BRANCH TYPE']);
        $created->assertRedirectTo('/add_new_branchtype');
        $row = $this->db->table('branch_type')->where('branch_type_details', 'SOURCE BRANCH TYPE')->get()->getRowArray();
        self::assertNotNull($row);
        $id = (int) $row['branch_type_id'];

        $invalidEdit = $this->postAsAdmin('/editBranchtype', [
            'branch_type_id' => (string) $id,
            'branch_type_name' => '',
        ]);
        $invalidEdit->assertStatus(200);
        $invalidEdit->assertSee('id="addBranchtype"');
        $invalidEdit->assertSee('The Branch Name field is required.');
        $invalidEdit->assertSee('value="SOURCE BRANCH TYPE"');
        self::assertSame(
            'SOURCE BRANCH TYPE',
            $this->db->table('branch_type')->where('branch_type_id', $id)->get()->getRow('branch_type_details'),
        );

        $updated = $this->postAsAdmin('/editBranchtype', [
            'branch_type_id' => (string) $id,
            'branch_type_name' => 'UPDATED SOURCE TYPE',
        ]);
        $updated->assertRedirectTo('/branchtypeListing');
        self::assertSame(
            'UPDATED SOURCE TYPE',
            $this->db->table('branch_type')->where('branch_type_id', $id)->get()->getRow('branch_type_details'),
        );
    }

    public function testLegacyBranchTypeUploadUsesSourceFieldAndServedPreviewRoute(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'legacy-branchtype-png-');
        $bad = tempnam(sys_get_temp_dir(), 'legacy-branchtype-bad-');
        self::assertIsString($png);
        self::assertIsString($bad);
        $jpeg = imagecreatetruecolor(1, 1);
        self::assertInstanceOf(\GdImage::class, $jpeg);
        self::assertTrue(imagejpeg($jpeg, $png));
        imagedestroy($jpeg);
        file_put_contents($bad, '<?php echo "not an image";');
        $stored = null;

        try {
            // The legacy browser may supply any common raster image. CI4 determines the
            // content from bytes and re-encodes a non-executable PNG outside the public root.
            service('superglobals')->setFilesArray(['branch_type_image' => [
                'name' => 'legacy-photo.jpg', 'type' => 'image/jpeg', 'tmp_name' => $png,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($png),
            ]]);
            $this->postAsAdmin('/addNewBranchtype', [
                'branch_type_name' => 'LEGACY IMAGE TYPE',
            ])->assertRedirectTo('/add_new_branchtype');

            $row = $this->db->table('branch_type')->where('branch_type_details', 'LEGACY IMAGE TYPE')->get()->getRowArray();
            self::assertNotNull($row);
            $stored = (string) $row['branch_type_image'];
            self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $stored);
            self::assertFileExists(WRITEPATH . 'uploads/branch-types/' . $stored);

            foreach (['/branchtypeListing', '/editBranchtypeOld/' . (int) $row['branch_type_id']] as $path) {
                $body = (string) $this->getAsAdmin($path)->getBody();
                self::assertStringContainsString(
                    'src="' . base_url('branch-type-image/' . $stored) . '"',
                    $body,
                    $path,
                );
            }
            $served = $this->get('/branch-type-image/' . $stored);
            $served->assertStatus(200);
            $served->assertHeader('Content-Type', 'image/png');

            // CORRECT_AND_REBASELINE: unlike CI3, a disguised executable is never retained.
            service('superglobals')->setFilesArray(['branch_type_image' => [
                'name' => 'payload.php', 'type' => 'application/x-php', 'tmp_name' => $bad,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($bad),
            ]]);
            $this->postAsAdmin('/addNewBranchtype', [
                'branch_type_name' => 'DISGUISED LEGACY IMAGE',
            ])->assertStatus(422);
            self::assertSame(0, $this->db->table('branch_type')->where('branch_type_details', 'DISGUISED LEGACY IMAGE')->countAllResults());
        } finally {
            service('superglobals')->setFilesArray([]);
            if (is_string($stored)) {
                @unlink(WRITEPATH . 'uploads/branch-types/' . $stored);
            }
            @unlink($png);
            @unlink($bad);
        }
    }

    public function testLegacyBranchTypeDeletePreservesReferencesAndDeletesUnreferencedRow(): void
    {
        $this->db->table('branch_type')->insertBatch([
            ['branch_type_id' => 1, 'branch_type_details' => 'REFERENCED TYPE', 'cdate' => '2026-08-22 09:00:00'],
            ['branch_type_id' => 2, 'branch_type_details' => 'FREE TYPE', 'cdate' => '2026-08-22 09:00:00'],
        ]);
        $this->db->table('branch')->insert([
            'branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'guard-user',
            'branch_name' => 'GUARD BRANCH', 'branch_details' => 'D', 'default_suffix' => 'G',
            'book_order' => 'G', 'customer_ref' => 'G', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $page = (string) $this->getAsAdmin('/branchtypeListing')->getBody();
        self::assertSame(1, preg_match('/name="csrf_test_name" value="([^"]+)"/', $page, $matches));

        // CORRECT_AND_REBASELINE: CI3 leaves dangling branch references. CI4 keeps
        // the row and returns a non-2xx response so the legacy .done() callback cannot hide it.
        $referenced = $this->withSession($this->session())->post('/deleteBranchtype', [
            'branchid' => '1', 'csrf_test_name' => $matches[1],
        ]);
        $referenced->assertStatus(409);
        $referenced->assertJSONExact(['status' => false, 'error' => 'master_referenced']);
        self::assertSame(1, $this->db->table('branch_type')->where('branch_type_id', 1)->countAllResults());
        $nextToken = $referenced->response()->getHeaderLine('X-CSRF-TOKEN');
        self::assertNotSame('', $nextToken);

        $deleted = $this->withSession($this->session())->post('/deleteBranchtype', [
            'branchid' => '2', 'csrf_test_name' => $nextToken,
        ]);
        $deleted->assertStatus(200);
        $deleted->assertJSONExact(['status' => true]);
        self::assertSame(0, $this->db->table('branch_type')->where('branch_type_id', 2)->countAllResults());

        $script = (string) file_get_contents(PUBLICPATH . 'assets/js/legacy-csrf.js');
        self::assertStringContainsString("responseJSON.error === 'master_referenced'", $script);
        self::assertStringContainsString("window.alert('branch deletion failed')", $script);
    }

    public function testCi3ProductTypeValidationAndFlashFeedbackMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewProducttype', ['type_details' => '   ']);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The Product type  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="type_details")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('type')->countAllResults());

            $created = $this->postAsAdmin('/addNewProducttype', ['type_details' => 'SOURCE PRODUCT TYPE']);
            $created->assertRedirectTo('/add_new_producttype');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_producttype');
            self::assertStringContainsString('New Product type created successfully', $createFeedback);
            $row = $this->db->table('type')->where('type_details', 'SOURCE PRODUCT TYPE')->get()->getRowArray();
            self::assertNotNull($row);
            $typeId = (int) $row['type_id'];

            $invalidEdit = $this->postAsAdmin('/editProducttype', [
                'type_id' => (string) $typeId,
                'type_details' => '',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The Product type Name field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE PRODUCT TYPE"', $invalidEditBody);
            self::assertSame(
                'SOURCE PRODUCT TYPE',
                $this->db->table('type')->where('type_id', $typeId)->get()->getRow('type_details'),
            );

            $updated = $this->postAsAdmin('/editProducttype', [
                'type_id' => (string) $typeId,
                'type_details' => 'UPDATED PRODUCT TYPE',
            ]);
            $updated->assertRedirectTo('/producttypeListing');
            self::assertSame('branch type updated successfully', service('session')->getFlashdata('success'));
            self::assertSame(
                'UPDATED PRODUCT TYPE',
                $this->db->table('type')->where('type_id', $typeId)->get()->getRow('type_details'),
            );
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3BrandValidationFlashAndTimestampMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewBrand', ['brand_details' => '   ']);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The brand  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="brand_details")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('brand')->countAllResults());

            $created = $this->postAsAdmin('/addNewBrand', ['brand_details' => 'SOURCE BRAND']);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_brand');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_brand');
            self::assertStringContainsString('New brand created successfully', $createFeedback);
            $row = $this->db->table('brand')->where('brand_details', 'SOURCE BRAND')->get()->getRowArray();
            self::assertNotNull($row);
            $brandId = (int) $row['brand_id'];
            $this->db->table('brand')->where('brand_id', $brandId)->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editBrand', [
                'brand_id' => (string) $brandId,
                'brand_details' => '',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The Product type Name field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE BRAND"', $invalidEditBody);

            $updated = $this->postAsAdmin('/editBrand', [
                'brand_id' => (string) $brandId,
                'brand_details' => 'UPDATED BRAND',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/brandListing');
            self::assertSame('brand updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('brand')->where('brand_id', $brandId)->get()->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('UPDATED BRAND', $updatedRow['brand_details']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3BrandMissingEditIdUsesSourceRedirectStatus(): void
    {
        $missingGet = $this->getAsAdmin('/editBrandOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/brandListing');

        $missingPost = $this->postAsAdmin('/editBrand', ['brand_details' => '']);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/brandListing');
        self::assertSame(0, $this->db->table('brand')->countAllResults());
    }

    public function testCi3ConditionValidationFlashAndTimestampMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewCondition', ['condition_details' => '   ']);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The brand  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="condition_details")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('condition')->countAllResults());

            $created = $this->postAsAdmin('/addNewCondition', ['condition_details' => 'SOURCE CONDITION']);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_condition');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_condition');
            self::assertStringContainsString('New condition created successfully', $createFeedback);
            $row = $this->db->table('condition')
                ->where('condition_details', 'SOURCE CONDITION')
                ->get()
                ->getRowArray();
            self::assertNotNull($row);
            $conditionId = (int) $row['condition_id'];
            $this->db->table('condition')
                ->where('condition_id', $conditionId)
                ->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editCondition', [
                'condition_id' => (string) $conditionId,
                'condition_details' => '',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The condition type Name field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE CONDITION"', $invalidEditBody);

            $updated = $this->postAsAdmin('/editCondition', [
                'condition_id' => (string) $conditionId,
                'condition_details' => 'UPDATED CONDITION',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/conditionListing');
            self::assertSame('condition updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('condition')
                ->where('condition_id', $conditionId)
                ->get()
                ->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('UPDATED CONDITION', $updatedRow['condition_details']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3ConditionMissingEditIdUsesSourceRedirectStatus(): void
    {
        $missingGet = $this->getAsAdmin('/editConditionOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/conditionListing');

        $missingPost = $this->postAsAdmin('/editCondition', ['condition_details' => '']);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/conditionListing');
        self::assertSame(0, $this->db->table('condition')->countAllResults());
    }

    public function testCi3EstimatepriceValidationFlashAndTimestampMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewEstimateprice', ['estimateprice_details' => '   ']);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The brand  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="estimateprice_details")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('estimateprice')->countAllResults());

            $created = $this->postAsAdmin('/addNewEstimateprice', [
                'estimateprice_details' => 'SOURCE ESTIMATEPRICE',
            ]);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_estimateprice');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_estimateprice');
            self::assertStringContainsString('New estimateprice created successfully', $createFeedback);
            $row = $this->db->table('estimateprice')
                ->where('estimateprice_details', 'SOURCE ESTIMATEPRICE')
                ->get()
                ->getRowArray();
            self::assertNotNull($row);
            $estimatepriceId = (int) $row['estimateprice_id'];
            $this->db->table('estimateprice')
                ->where('estimateprice_id', $estimatepriceId)
                ->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editEstimateprice', [
                'estimateprice_id' => (string) $estimatepriceId,
                'estimateprice_details' => '',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The estimateprice type Name field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE ESTIMATEPRICE"', $invalidEditBody);

            $updated = $this->postAsAdmin('/editEstimateprice', [
                'estimateprice_id' => (string) $estimatepriceId,
                'estimateprice_details' => 'UPDATED ESTIMATEPRICE',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/estimatepriceListing');
            self::assertSame('estimateprice updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('estimateprice')
                ->where('estimateprice_id', $estimatepriceId)
                ->get()
                ->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('UPDATED ESTIMATEPRICE', $updatedRow['estimateprice_details']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3EstimatepriceMissingEditIdUsesSourceRedirectStatus(): void
    {
        $missingGet = $this->getAsAdmin('/editEstimatepriceOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/estimatepriceListing');

        $missingPost = $this->postAsAdmin('/editEstimateprice', ['estimateprice_details' => '']);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/estimatepriceListing');
        self::assertSame(0, $this->db->table('estimateprice')->countAllResults());
    }

    public function testCi3FixedValidationFlashAndTimestampMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewFixed', ['fixed_details' => '   ']);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The brand  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="fixed_details")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('fixed')->countAllResults());

            $created = $this->postAsAdmin('/addNewFixed', ['fixed_details' => 'SOURCE FIXED']);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_fixed');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_fixed');
            self::assertStringContainsString('New fixed created successfully', $createFeedback);
            $row = $this->db->table('fixed')
                ->where('fixed_details', 'SOURCE FIXED')
                ->get()
                ->getRowArray();
            self::assertNotNull($row);
            $fixedId = (int) $row['fixed_id'];
            $this->db->table('fixed')
                ->where('fixed_id', $fixedId)
                ->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editFixed', [
                'fixed_id' => (string) $fixedId,
                'fixed_details' => '',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The fixed Name field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE FIXED"', $invalidEditBody);

            $updated = $this->postAsAdmin('/editFixed', [
                'fixed_id' => (string) $fixedId,
                'fixed_details' => 'UPDATED FIXED',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/fixedListing');
            self::assertSame('fixed updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('fixed')
                ->where('fixed_id', $fixedId)
                ->get()
                ->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('UPDATED FIXED', $updatedRow['fixed_details']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3FixedMissingEditIdUsesSourceRedirectStatus(): void
    {
        $missingGet = $this->getAsAdmin('/editFixedOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/fixedListing');

        $missingPost = $this->postAsAdmin('/editFixed', ['fixed_details' => '']);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/fixedListing');
        self::assertSame(0, $this->db->table('fixed')->countAllResults());
    }

    public function testCi3ProviderValidationOptionalDetailFlashAndTimestampMatchSource(): void
    {
        try {
            $invalidCreate = $this->postAsAdmin('/addNewProvider', [
                'provider_name' => '   ',
                'provider_tel' => '   ',
                'provider_details' => 'SHOULD NOT BE REPOPULATED',
            ]);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The provider_name  field is required.', $invalidCreateBody);
            self::assertStringContainsString('The provider tel  field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="provider_name")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="provider_tel")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertStringNotContainsString('SHOULD NOT BE REPOPULATED', $invalidCreateBody);
            self::assertSame(0, $this->db->table('provider')->countAllResults());

            $created = $this->postAsAdmin('/addNewProvider', [
                'provider_name' => 'SOURCE PROVIDER',
                'provider_tel' => '0123456789',
                'provider_details' => '',
            ]);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_provider');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_provider');
            self::assertStringContainsString('New provider created successfully', $createFeedback);
            $row = $this->db->table('provider')
                ->where('provider_name', 'SOURCE PROVIDER')
                ->get()
                ->getRowArray();
            self::assertNotNull($row);
            self::assertSame('', $row['provider_datail']);
            $providerId = (int) $row['provider_id'];
            $this->db->table('provider')
                ->where('provider_id', $providerId)
                ->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editProvider', [
                'provider_id' => (string) $providerId,
                'provider_name' => '',
                'provider_tel' => '',
                'provider_details' => 'IGNORED INVALID DETAIL',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The provider_name  field is required.', $invalidEditBody);
            self::assertStringContainsString('The provider tel  field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            self::assertStringContainsString('value="SOURCE PROVIDER"', $invalidEditBody);
            self::assertStringContainsString('value="0123456789"', $invalidEditBody);
            self::assertStringNotContainsString('IGNORED INVALID DETAIL', $invalidEditBody);

            $updated = $this->postAsAdmin('/editProvider', [
                'provider_id' => (string) $providerId,
                'provider_name' => 'UPDATED PROVIDER',
                'provider_tel' => '9876543210',
                'provider_details' => '  PADDED DETAIL  ',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/providerListing');
            self::assertSame('provider updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('provider')
                ->where('provider_id', $providerId)
                ->get()
                ->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('UPDATED PROVIDER', $updatedRow['provider_name']);
            self::assertSame('9876543210', $updatedRow['provider_tel']);
            self::assertSame('  PADDED DETAIL  ', $updatedRow['provider_datail']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3ProviderMissingEditIdUsesSourceRedirectStatus(): void
    {
        $missingGet = $this->getAsAdmin('/editProviderOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/providerListing');

        $missingPost = $this->postAsAdmin('/editProvider', [
            'provider_name' => '',
            'provider_tel' => '',
            'provider_details' => '',
        ]);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/providerListing');
        self::assertSame(0, $this->db->table('provider')->countAllResults());
    }

    public function testCi3StatusTypeValidationOptionalSuccessFlashAndTimestampMatchSource(): void
    {
        try {
            // CORRECT_AND_REBASELINE: CI3 declares these two rules but its invalid-create
            // branch calls the nonexistent add_new_branchtype() method. Render the intended
            // validation response instead of reproducing that source 500.
            $invalidCreate = $this->postAsAdmin('/addNewStatustype', [
                'description_th' => '   ',
                'description_en' => '   ',
                'success' => '1',
            ]);
            $invalidCreate->assertStatus(200);
            $invalidCreateBody = (string) $invalidCreate->getBody();
            self::assertStringContainsString('The description th field is required.', $invalidCreateBody);
            self::assertStringContainsString('The description en field is required.', $invalidCreateBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidCreateBody);
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="description_th")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="description_en")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="success")(?=[^>]*\bvalue="")[^>]*>/s',
                $invalidCreateBody,
            );
            self::assertSame(0, $this->db->table('tracking_status')->countAllResults());

            $created = $this->postAsAdmin('/addNewStatustype', [
                'description_th' => 'สถานะต้นฉบับ',
                'description_en' => 'SOURCE STATUS',
                'success' => '',
            ]);
            $created->assertStatus(303);
            $created->assertRedirectTo('/add_new_statustype');
            $createFeedback = (new LegacyViewRenderer(service('session')))->render('master/add_statustype');
            self::assertStringContainsString('New status type created successfully', $createFeedback);
            $row = $this->db->table('tracking_status')
                ->where('description_en', 'SOURCE STATUS')
                ->get()
                ->getRowArray();
            self::assertNotNull($row);
            self::assertSame(0, (int) $row['success']);
            $statusId = (int) $row['status_id'];
            $this->db->table('tracking_status')
                ->where('status_id', $statusId)
                ->update(['cdate' => '2000-01-01 00:00:00']);

            $invalidEdit = $this->postAsAdmin('/editStatustype', [
                'status_id' => (string) $statusId,
                'description_th' => '',
                'description_en' => '',
                'success' => '1',
            ]);
            $invalidEdit->assertStatus(200);
            $invalidEditBody = (string) $invalidEdit->getBody();
            self::assertStringContainsString('The description th field is required.', $invalidEditBody);
            self::assertStringContainsString('The description en field is required.', $invalidEditBody);
            self::assertStringNotContainsString('Invalid master data.', $invalidEditBody);
            $decodedInvalidEditBody = html_entity_decode($invalidEditBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            self::assertStringContainsString('value="สถานะต้นฉบับ"', $decodedInvalidEditBody);
            self::assertStringContainsString('value="SOURCE STATUS"', $decodedInvalidEditBody);

            $updated = $this->postAsAdmin('/editStatustype', [
                'status_id' => (string) $statusId,
                'description_th' => 'สถานะปรับปรุง',
                'description_en' => 'UPDATED STATUS',
            ]);
            $updated->assertStatus(303);
            $updated->assertRedirectTo('/statustypeListing');
            self::assertSame('status type updated successfully', service('session')->getFlashdata('success'));
            $updatedRow = $this->db->table('tracking_status')
                ->where('status_id', $statusId)
                ->get()
                ->getRowArray();
            self::assertNotNull($updatedRow);
            self::assertSame('สถานะปรับปรุง', $updatedRow['description_th']);
            self::assertSame('UPDATED STATUS', $updatedRow['description_en']);
            self::assertNull($updatedRow['success']);
            self::assertNotSame('2000-01-01 00:00:00', $updatedRow['cdate']);
        } finally {
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testCi3StatusTypeMissingAndUnknownEditIdsRenderSourceResponses(): void
    {
        $missingGet = $this->getAsAdmin('/editStatustypeOld');
        $missingGet->assertStatus(307);
        $missingGet->assertRedirectTo('/statustypeListing');

        $missingPost = $this->postAsAdmin('/editStatustype', [
            'description_th' => '',
            'description_en' => '',
            'success' => '1',
        ]);
        $missingPost->assertStatus(303);
        $missingPost->assertRedirectTo('/statustypeListing');

        $unknown = $this->getAsAdmin('/editStatustypeOld/999999');
        $unknown->assertStatus(200);
        $unknownBody = (string) $unknown->getBody();
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*\bname="description_th")(?=[^>]*\bvalue="")[^>]*>/s',
            $unknownBody,
        );
        self::assertMatchesRegularExpression(
            '/<input(?=[^>]*\bname="description_en")(?=[^>]*\bvalue="")[^>]*>/s',
            $unknownBody,
        );
        self::assertSame(0, $this->db->table('tracking_status')->countAllResults());
    }

    public function testCi3BrandSearchRawOffsetsAndPaginationMatchSource(): void
    {
        for ($start = 1; $start <= 351; $start += 100) {
            $rows = [];
            for ($id = $start; $id <= min($start + 99, 351); $id++) {
                $rows[] = [
                    'brand_id' => $id,
                    'brand_details' => sprintf('PARITY BRAND %03d', $id),
                    'cdate' => '2026-08-22 09:00:00',
                ];
            }
            $this->db->table('brand')->insertBatch($rows);
        }

        $spaces = $this->postAsAdmin('/brandListing', ['searchText' => '   ']);
        $spaces->assertStatus(200);
        $spacesBody = (string) $spaces->getBody();
        self::assertStringContainsString('name="searchText" value="   "', $spacesBody);
        self::assertStringNotContainsString('PARITY BRAND 001', $spacesBody);

        $padded = $this->postAsAdmin('/brandListing', ['searchText' => ' PARITY BRAND 001 ']);
        $padded->assertStatus(200);
        $paddedBody = (string) $padded->getBody();
        self::assertStringContainsString('value=" PARITY BRAND 001 "', $paddedBody);
        self::assertStringNotContainsString('PARITY BRAND 001</td>', $paddedBody);

        $longSearch = str_repeat('X', 129);
        $long = $this->postAsAdmin('/brandListing', ['searchText' => $longSearch]);
        $long->assertStatus(200);
        $longBody = (string) $long->getBody();
        self::assertStringContainsString('value="' . $longSearch . '"', $longBody);
        self::assertStringNotContainsString('PARITY BRAND 001', $longBody);
        service('incomingrequest')->setGlobal('post', []);
        service('superglobals')->setPostArray([]);

        $page1Body = (string) $this->getAsAdmin('/brandListing')->getBody();
        self::assertStringContainsString('PARITY BRAND 001', $page1Body);
        self::assertStringContainsString('PARITY BRAND 050', $page1Body);
        self::assertStringNotContainsString('PARITY BRAND 051', $page1Body);
        self::assertStringContainsString(
            'href="http://example.invalid/brandListing/50" data-ci-pagination-page="2">2</a>',
            $page1Body,
        );
        self::assertStringContainsString('data-ci-pagination-page="2" rel="next">Next</a>', $page1Body);
        self::assertStringContainsString('data-ci-pagination-page="8">Last</a>', $page1Body);
        self::assertStringNotContainsString('data-ci-pagination-page="7">7</a>', $page1Body);

        $offsetOneBody = (string) $this->getAsAdmin('/brandListing/1')->getBody();
        self::assertStringNotContainsString('PARITY BRAND 001', $offsetOneBody);
        self::assertStringContainsString('PARITY BRAND 002', $offsetOneBody);
        self::assertStringContainsString('PARITY BRAND 051', $offsetOneBody);

        $offsetFortyNineBody = (string) $this->getAsAdmin('/brandListing/49')->getBody();
        self::assertStringNotContainsString('PARITY BRAND 049', $offsetFortyNineBody);
        self::assertStringContainsString('PARITY BRAND 050', $offsetFortyNineBody);
        self::assertStringContainsString('PARITY BRAND 099', $offsetFortyNineBody);

        $page2Body = (string) $this->getAsAdmin('/brandListing/50')->getBody();
        self::assertStringNotContainsString('PARITY BRAND 050', $page2Body);
        self::assertStringContainsString('PARITY BRAND 051', $page2Body);
        self::assertStringContainsString('PARITY BRAND 100', $page2Body);
        self::assertStringContainsString('data-ci-pagination-page="1" rel="prev">Previous</a>', $page2Body);

        $clickedPageBody = (string) $this->postAsAdmin('/brandListing/50', [
            'searchText' => 'PARITY BRAND',
        ])->getBody();
        self::assertStringContainsString('name="searchText" value="PARITY BRAND"', $clickedPageBody);
        self::assertStringNotContainsString('PARITY BRAND 001', $clickedPageBody);
        self::assertStringContainsString('PARITY BRAND 051', $clickedPageBody);
        self::assertStringContainsString('PARITY BRAND 100', $clickedPageBody);
        service('incomingrequest')->setGlobal('post', []);
        service('superglobals')->setPostArray([]);

        $lastPageBody = (string) $this->getAsAdmin('/brandListing/350')->getBody();
        self::assertStringContainsString('PARITY BRAND 351', $lastPageBody);
        self::assertStringContainsString('data-ci-pagination-page="1" rel="start">First</a>', $lastPageBody);
        self::assertStringContainsString('data-ci-pagination-page="7" rel="prev">Previous</a>', $lastPageBody);
        self::assertStringContainsString('data-ci-pagination-page="3">3</a>', $lastPageBody);
        self::assertStringNotContainsString('data-ci-pagination-page="2">2</a>', $lastPageBody);
        self::assertStringNotContainsString('>Next</a>', $lastPageBody);
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

    public function testBranchAndBookListingsUseCi3ColumnsAndProjectedValues(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 98765, 'branch_type_details' => 'DISPLAY TYPE',
            'cdate' => '2026-08-22 09:00:00',
        ]);
        $this->db->table('branch')->insertBatch([
            [
                'branch_id' => 31001, 'branch_type' => 98765, 'branch_user_name' => 'display-user',
                'branch_name' => 'DISPLAY BRANCH', 'branch_details' => 'FORM ONLY DETAILS',
                'default_suffix' => 'DSP', 'book_order' => 'FORMONLY', 'customer_ref' => 'REF-DISPLAY',
                'cdate' => '2026-08-22 09:00:00',
            ],
            [
                'branch_id' => 31002, 'branch_type' => 87654, 'branch_user_name' => 'missing-user',
                'branch_name' => 'MISSING TYPE BRANCH', 'branch_details' => 'HIDDEN DETAILS',
                'default_suffix' => 'MIS', 'book_order' => 'HIDDENORD', 'customer_ref' => 'REF-MISSING',
                'cdate' => '2026-08-22 09:00:00',
            ],
        ]);
        $this->db->table('book')->insertBatch([
            ['book_id' => 41001, 'branch_id' => 31001, 'book_detail' => 'ONE', 'status' => 1, 'bunber_limit' => 777771, 'cdate' => '2026-08-22 09:00:00'],
            ['book_id' => 41002, 'branch_id' => 31001, 'book_detail' => 'ZER', 'status' => 0, 'bunber_limit' => 777772, 'cdate' => '2026-08-22 09:00:00'],
            ['book_id' => 41003, 'branch_id' => 31001, 'book_detail' => 'TWO', 'status' => 2, 'bunber_limit' => 777773, 'cdate' => '2026-08-22 09:00:00'],
            ['book_id' => 41004, 'branch_id' => 76543, 'book_detail' => 'MIS', 'status' => 1, 'bunber_limit' => 777774, 'cdate' => '2026-08-22 09:00:00'],
        ]);

        $branchBody = html_entity_decode((string) $this->getAsAdmin('/master/branch')->getBody());
        $branchBody = (string) preg_replace('/\s+/', ' ', $branchBody);
        self::assertStringContainsString(
            '<th>Id</th> <th>Branch type</th> <th>Branch User</th> <th>Branch name</th> <th>Branch suffix</th> <th>Ref</th> <th class="text-center">Actions</th>',
            $branchBody,
        );
        self::assertMatchesRegularExpression('#<td>31001</td> <td>DISPLAY TYPE</td> <td>display-user</td>#', $branchBody);
        self::assertMatchesRegularExpression('#<td>31002</td> <td></td> <td>missing-user</td>#', $branchBody);
        self::assertStringNotContainsString('98765', $branchBody);
        self::assertStringNotContainsString('87654', $branchBody);
        self::assertStringNotContainsString('FORM ONLY DETAILS', $branchBody);
        self::assertStringNotContainsString('HIDDENORD', $branchBody);

        $bookBody = html_entity_decode((string) $this->getAsAdmin('/master/book')->getBody());
        $bookBody = (string) preg_replace('/\s+/', ' ', $bookBody);
        self::assertStringContainsString(
            '<th>ฺBookId</th> <th>Branch name</th> <th>Book Details</th> <th>Status</th> <th class="text-center">Actions</th>',
            $bookBody,
        );
        self::assertMatchesRegularExpression('#<td>41001</td> <td>DISPLAY BRANCH</td> <td>ONE</td> <td>Publishing</td>#', $bookBody);
        self::assertMatchesRegularExpression('#<td>41002</td> <td>DISPLAY BRANCH</td> <td>ZER</td> <td>Unpublish</td>#', $bookBody);
        self::assertMatchesRegularExpression('#<td>41003</td> <td>DISPLAY BRANCH</td> <td>TWO</td> <td>Unpublish</td>#', $bookBody);
        self::assertMatchesRegularExpression('#<td>41004</td> <td></td> <td>MIS</td> <td>Publishing</td>#', $bookBody);
        self::assertStringNotContainsString('76543', $bookBody);
        self::assertStringNotContainsString('77777', $bookBody);
        self::assertStringNotContainsString('Number Limit', $bookBody);
    }

    public function testEightUncustomizedMasterListingsStillRenderEveryDefinitionField(): void
    {
        foreach (['branchtype', 'statustype', 'producttype', 'brand', 'condition', 'estimateprice', 'fixed', 'provider'] as $type) {
            $definition = MasterCatalog::definition($type);
            self::assertNotNull($definition);
            $body = html_entity_decode((string) $this->getAsAdmin('/master/' . $type)->getBody());
            foreach ($definition['fields'] as $field => $rule) {
                self::assertStringContainsString('<th>' . ($rule['listText'] ?? $field) . '</th>', $body, $type . ':' . $field);
            }
        }
    }

    public function testBranchFieldsFollowCi3ValidationAndBookInternalLimitStaysOutOfCi3Form(): void
    {
        $branch = $this->payload('branch', 'FORMBRANCH');
        $branch['branch_details'] = 'ORIGINAL DETAILS';
        $branch['book_order'] = 'ORIGINAL';
        $this->postAsAdmin('/master/branch', $branch)->assertRedirectTo('/master/branch');
        $branchId = (int) $this->db->insertID();
        $branchRow = $this->db->table('branch')->where('branch_id', $branchId)->get()->getRowArray();
        self::assertNotNull($branchRow);
        self::assertSame('ORIGINAL DETAILS', $branchRow['branch_details']);
        self::assertSame('ORIGINAL', $branchRow['book_order']);

        foreach (['/master/branch/new', '/master/branch/' . $branchId] as $path) {
            $body = (string) $this->getAsAdmin($path)->getBody();
            self::assertStringContainsString('name="branch_details"', $body, $path);
            self::assertStringContainsString('name="book_order"', $body, $path);
        }
        $branch['branch_details'] = 'UPDATED DETAILS';
        $branch['book_order'] = 'UPDATED';
        $this->postAsAdmin('/master/branch/' . $branchId, $branch)->assertRedirectTo('/master/branch');
        $branchRow = $this->db->table('branch')->where('branch_id', $branchId)->get()->getRowArray();
        self::assertNotNull($branchRow);
        self::assertSame('UPDATED DETAILS', $branchRow['branch_details']);
        self::assertSame('UPDATED', $branchRow['book_order']);
        // CI3 validates only branch_type and branch_name on the server. The remaining
        // legacy fields are allowed to persist as empty strings despite their NOT NULL schema.
        $branch['branch_details'] = '';
        $branch['default_suffix'] = '';
        $branch['book_order'] = '';
        $this->postAsAdmin('/master/branch/' . $branchId, $branch)->assertRedirectTo('/master/branch');
        $blankBranch = $this->db->table('branch')->where('branch_id', $branchId)->get()->getRowArray();
        self::assertNotNull($blankBranch);
        self::assertSame('', $blankBranch['branch_details']);
        self::assertSame('', $blankBranch['default_suffix']);
        self::assertSame('', $blankBranch['book_order']);

        $book = $this->payload('book', 'FRM');
        $book['bunber_limit'] = '321';
        $this->postAsAdmin('/master/book', $book)->assertRedirectTo('/master/book');
        $bookId = (int) $this->db->insertID();
        self::assertSame(321, (int) $this->db->table('book')->where('book_id', $bookId)->get()->getRow('bunber_limit'));
        foreach (['/master/book/new', '/master/book/' . $bookId] as $path) {
            self::assertStringNotContainsString('name="bunber_limit"', (string) $this->getAsAdmin($path)->getBody(), $path);
        }
        $book['bunber_limit'] = '654';
        $this->postAsAdmin('/master/book/' . $bookId, $book)->assertRedirectTo('/master/book');
        self::assertSame(654, (int) $this->db->table('book')->where('book_id', $bookId)->get()->getRow('bunber_limit'));
        $book['bunber_limit'] = '';
        $this->postAsAdmin('/master/book/' . $bookId, $book)->assertStatus(422);
        self::assertSame(654, (int) $this->db->table('book')->where('book_id', $bookId)->get()->getRow('bunber_limit'));
    }

    public function testC3LabelParityBranchUsesCi3TextNotRawColumns(): void
    {
        // After the add/edit/listing split, the entity form lives on /master/<type>/new;
        // form labels are copied verbatim from CI3 master/add_branch.php (C3, AC-2).
        $form = $this->getAsAdmin('/master/branch/new');
        $form->assertStatus(200);
        $form->assertSee('Branch User');
        $form->assertSee('PREFIX');
        $form->assertSee('book order');
        $form->assertSee('Customer Ref');
        // AC-1: no raw DB column name is rendered as a label text node
        $form->assertDontSee('>branch_user_name<');
        $form->assertDontSee('>default_suffix<');
        $form->assertDontSee('>customer_ref<');

        // Listing headers (CI3 master/branch.php <th>) differ from the form text (C3, AC-1).
        $listing = $this->getAsAdmin('/master/branch');
        $listing->assertStatus(200);
        $listing->assertSee('>Id</th>');
        $listing->assertSee('>Branch type</th>');
        $listing->assertSee('>Branch suffix</th>');
        $listing->assertSee('>Ref</th>');
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
        $listing->assertSee('class="btn btn-sm btn-danger deleteBrand"');
        $listing->assertSee('deleteBrand');
    }

    public function testCi3DeleteAliasesAcceptSourcePayloadAndReturnJsonStatus(): void
    {
        $seed = $this->payload('brand', 'LEGACYDEL');
        unset($seed['csrf_test_name']);
        $seed['cdate'] = '2026-08-22 09:00:00';
        $this->db->table('brand')->insert($seed);
        $brandId = (int) $this->db->insertID();

        $response = $this->postAsAdmin('/deleteBrand', ['brandid' => (string) $brandId]);
        $response->assertStatus(200);
        $response->assertJSONExact(['status' => true]);
        self::assertSame(0, $this->db->table('brand')->where('brand_id', $brandId)->countAllResults());

        $this->postAsAdmin('/deleteBrand', ['brandid' => 'invalid'])
            ->assertJSONExact(['status' => false]);
    }

    public function testForeignKeyFieldRendersSelectWithReferenceOptions(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_details' => 'TYPE ONE',
            'cdate'               => '2026-08-22 09:00:00',
        ]);
        $branchTypeId = (int) $this->db->insertID();
        $this->db->table('branch')->insert([
            'branch_id'    => 1, 'branch_type' => $branchTypeId, 'branch_name' => 'ALPHA BRANCH',
            'branch_details' => 'A', 'default_suffix' => 'A', 'book_order' => 'A',
            'cdate'        => '2026-08-22 09:00:00',
        ]);

        // book.branch_id is a FK to the branch entity (form now on /master/<type>/new).
        $bookForm = $this->getAsAdmin('/master/book/new');
        $bookForm->assertStatus(200);
        $bookForm->assertSee('<select');
        $bookForm->assertSee('name="branch_id"');
        $bookForm->assertSee('ALPHA BRANCH');
        $bookForm->assertSee('value="1"');

        // branch.branch_type is a FK to the branchtype entity.
        $branchForm = $this->getAsAdmin('/master/branch/new');
        $branchForm->assertSee('name="branch_type"');
        $branchForm->assertSee('TYPE ONE');

        // Selected state on the edit form reflects the stored value.
        $this->db->table('book')->insert([
            'book_id' => 5, 'branch_id' => 1, 'book_detail' => 'ED1', 'status' => 1,
            'bunber_limit' => 10, 'cdate' => '2026-08-22 09:00:00',
        ]);
        $edit = $this->getAsAdmin('/master/book/5');
        $edit->assertStatus(200);
        $edit->assertSee('value="1" selected');
    }

    public function testBranchListingColumnContractHoldsForZeroOneAndFiftyRows(): void
    {
        $empty = html_entity_decode((string) $this->getAsAdmin('/master/branch')->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertMatchesRegularExpression('/<th>\s*Branch type\s*<\/th>/s', $empty);
        self::assertStringNotContainsString('<th>Detail</th>', $empty);
        self::assertStringNotContainsString('<th>book order</th>', $empty);

        $this->db->table('branch_type')->insert([
            'branch_type_id' => 1, 'branch_type_details' => 'PAGED TYPE', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $this->db->table('branch')->insert([
            'branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'user-01', 'branch_name' => 'BRANCH-01',
            'branch_details' => 'HIDDEN-DETAIL-01', 'default_suffix' => 'S01', 'book_order' => 'HIDDEN-ORDER-01',
            'customer_ref' => 'R01', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $one = (string) $this->getAsAdmin('/master/branch')->getBody();
        self::assertStringContainsString('PAGED TYPE', $one);
        self::assertStringContainsString('BRANCH-01', $one);
        self::assertStringNotContainsString('HIDDEN-DETAIL-01', $one);
        self::assertStringNotContainsString('HIDDEN-ORDER-01', $one);

        $rows = [];
        for ($i = 2; $i <= 50; $i++) {
            $rows[] = [
                'branch_id' => $i, 'branch_type' => 1, 'branch_user_name' => sprintf('user-%02d', $i),
                'branch_name' => sprintf('BRANCH-%02d', $i), 'branch_details' => sprintf('HIDDEN-DETAIL-%02d', $i),
                'default_suffix' => sprintf('S%02d', $i), 'book_order' => sprintf('HIDDEN-ORDER-%02d', $i),
                'customer_ref' => sprintf('R%02d', $i), 'cdate' => '2026-08-22 09:00:00',
            ];
        }
        $this->db->table('branch')->insertBatch($rows);
        $fifty = (string) $this->getAsAdmin('/master/branch?page=1')->getBody();
        self::assertStringContainsString('BRANCH-50', $fifty);
        // CI3 uses 50 rows per page; exactly 50 rows do not create a second page.
        self::assertStringNotContainsString('/branchListing/50', $fifty);
        self::assertStringNotContainsString('HIDDEN-DETAIL-', $fifty);
        self::assertStringNotContainsString('HIDDEN-ORDER-', $fifty);
    }

    public function testLegacyBranchListingScopesRowsToSessionBranch(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 1, 'branch_type_details' => 'SCOPED TYPE', 'cdate' => '2026-08-22 09:00:00',
        ]);
        $this->db->table('branch')->insertBatch([
            [
                'branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'own-user',
                'branch_name' => 'OWN BRANCH', 'branch_details' => 'OWN', 'default_suffix' => 'OWN',
                'book_order' => 'OWN', 'customer_ref' => 'OWN', 'cdate' => '2026-08-22 09:00:00',
            ],
            [
                'branch_id' => 2, 'branch_type' => 1, 'branch_user_name' => 'other-user',
                'branch_name' => 'OTHER BRANCH', 'branch_details' => 'OTHER', 'default_suffix' => 'OTH',
                'book_order' => 'OTH', 'customer_ref' => 'OTH', 'cdate' => '2026-08-22 09:00:00',
            ],
        ]);
        $branchUserId = (new ShadowUserStore($this->db))->create(
            'branch-scope@example.invalid',
            password_hash('Synthetic branch scope passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $session = [
            'userId' => $branchUserId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];

        $body = (string) $this->withSession($session)->get('/branchListing')->getBody();
        self::assertMatchesRegularExpression('#<td>1</td>\s*<td>SCOPED TYPE</td>\s*<td>own-user</td>#s', $body);
        self::assertDoesNotMatchRegularExpression('#<td>2</td>\s*<td>SCOPED TYPE</td>\s*<td>other-user</td>#s', $body);
        self::assertStringNotContainsString('class="btn btn-sm btn-danger deleteBranch"', $body);
        self::assertStringNotContainsString('href="http://example.invalid/BranchNew"', $body);
    }

    public function testLegacyBookListingScopesRowsSearchAndPaginationToSessionBranch(): void
    {
        $this->db->table('branch')->insertBatch([
            [
                'branch_id' => 1, 'branch_type' => 1, 'branch_user_name' => 'book-own-user',
                'branch_name' => 'OWN BOOK BRANCH', 'branch_details' => 'OWN', 'default_suffix' => 'OWN',
                'book_order' => 'OWN', 'customer_ref' => 'OWN', 'cdate' => '2026-08-22 09:00:00',
            ],
            [
                'branch_id' => 2, 'branch_type' => 1, 'branch_user_name' => 'book-other-user',
                'branch_name' => 'OTHER BOOK BRANCH', 'branch_details' => 'OTHER', 'default_suffix' => 'OTH',
                'book_order' => 'OTH', 'customer_ref' => 'OTH', 'cdate' => '2026-08-22 09:00:00',
            ],
        ]);
        $books = [[
            'book_id' => 1, 'branch_id' => 1, 'book_detail' => 'OWN', 'status' => 1,
            'bunber_limit' => 1, 'cdate' => '2026-08-22 09:00:00',
        ], [
            'book_id' => 2, 'branch_id' => 2, 'book_detail' => 'OTH', 'status' => 1,
            'bunber_limit' => 1, 'cdate' => '2026-08-22 09:00:00',
        ]];
        for ($id = 3; $id <= 52; $id++) {
            $books[] = [
                'book_id' => $id, 'branch_id' => 2, 'book_detail' => sprintf('X%02d', $id), 'status' => 1,
                'bunber_limit' => 1, 'cdate' => '2026-08-22 09:00:00',
            ];
        }
        $this->db->table('book')->insertBatch($books);
        $branchUserId = (new ShadowUserStore($this->db))->create(
            'book-branch-scope@example.invalid',
            password_hash('Synthetic book branch scope passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $session = [
            'userId' => $branchUserId, 'role' => 2, 'GroupID' => 4, 'BranchID' => 1,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];

        $body = (string) $this->withSession($session)->get('/bookListing')->getBody();
        self::assertMatchesRegularExpression(
            '#<td>1</td>\s*<td>OWN BOOK BRANCH</td>\s*<td>OWN</td>#s',
            $body,
        );
        self::assertDoesNotMatchRegularExpression(
            '#<td>2</td>\s*<td>OTHER BOOK BRANCH</td>\s*<td>OTH</td>#s',
            $body,
        );
        self::assertStringNotContainsString('/userListing/50', $body);

        try {
            $otherBranchSearch = (string) $this->withSession($session)
                ->post('/bookListing', ['searchText' => 'OTH'])
                ->getBody();
            self::assertStringNotContainsString('<td>OTH</td>', $otherBranchSearch);
            self::assertStringNotContainsString('OTHER BOOK BRANCH', $otherBranchSearch);

            $ownBranchSearch = (string) $this->withSession($session)
                ->post('/bookListing', ['searchText' => 'OWN'])
                ->getBody();
            self::assertMatchesRegularExpression(
                '#<td>1</td>\s*<td>OWN BOOK BRANCH</td>\s*<td>OWN</td>#s',
                $ownBranchSearch,
            );
        } finally {
            // FeatureTestTrait reuses IncomingRequest and does not clear its POST global on GET.
            // Clear both sources so this search payload cannot bleed into the next test.
            service('incomingrequest')->setGlobal('post', []);
            service('superglobals')->setPostArray([]);
        }
    }

    public function testLegacyBranchListingIgnoresGetSearchButKeepsPostSearch(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 1, 'branch_type_details' => 'SEARCH TYPE', 'cdate' => '2026-08-22 09:00:00',
        ]);
        foreach ([1 => 'ALPHA BRANCH', 2 => 'BETA BRANCH'] as $id => $name) {
            $this->db->table('branch')->insert([
                'branch_id' => $id, 'branch_type' => 1, 'branch_user_name' => 'search-' . $id,
                'branch_name' => $name, 'branch_details' => 'D', 'default_suffix' => 'S' . $id,
                'book_order' => 'B' . $id, 'customer_ref' => 'R' . $id, 'cdate' => '2026-08-22 09:00:00',
            ]);
        }

        $getBody = (string) $this->getAsAdmin('/branchListing?search=BETA%20BRANCH')->getBody();
        self::assertMatchesRegularExpression('#<td>search-1</td>\s*<td>ALPHA BRANCH</td>#s', $getBody);
        self::assertMatchesRegularExpression('#<td>search-2</td>\s*<td>BETA BRANCH</td>#s', $getBody);

        $postBody = (string) $this->postAsAdmin('/branchListing', ['searchText' => 'BETA BRANCH'])->getBody();
        self::assertDoesNotMatchRegularExpression('#<td>search-1</td>\s*<td>ALPHA BRANCH</td>#s', $postBody);
        self::assertMatchesRegularExpression('#<td>search-2</td>\s*<td>BETA BRANCH</td>#s', $postBody);
    }

    public function testLegacyBranchListingRedirectsGuestToLogin(): void
    {
        $response = $this->get('/branchListing');

        $response->assertStatus(307);
        $response->assertRedirectTo('/login');
    }

    public function testLegacyBranchCreateRerendersInvalidFormAndAcceptsCi3OptionalFields(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 1, 'branch_type_details' => 'CREATE TYPE', 'cdate' => '2026-08-22 09:00:00',
        ]);

        $invalid = $this->postAsAdmin('/addNewBranch', [
            'branch_type' => '1', 'branch_name' => '', 'branch_user_name' => 'kept-user',
            'default_suffix' => '', 'book_order' => '', 'customer_ref' => '', 'branch_details' => '',
        ]);
        $invalid->assertStatus(200);
        $invalid->assertSee('id="addBranch"');
        $invalid->assertSee('The Branch Name field is required.');
        $invalid->assertSee('value="kept-user"');
        self::assertSame(0, $this->db->table('branch')->countAllResults());

        $valid = $this->postAsAdmin('/addNewBranch', [
            'branch_type' => '1', 'branch_name' => 'MINIMAL LEGACY BRANCH', 'branch_user_name' => 'legacy-user',
            'default_suffix' => '', 'book_order' => '', 'customer_ref' => '', 'branch_details' => '',
        ]);
        $valid->assertRedirectTo('/BranchNew');
        $row = $this->db->table('branch')->where('branch_name', 'MINIMAL LEGACY BRANCH')->get()->getRowArray();
        self::assertNotNull($row);
        self::assertSame('', $row['branch_details']);
        self::assertSame('', $row['default_suffix']);
        self::assertSame('', $row['book_order']);
    }

    public function testLegacyMasterDeleteUsesRenderedCsrfAndRefreshesIt(): void
    {
        $this->db->table('branch_type')->insert([
            'branch_type_id' => 1, 'branch_type_details' => 'DELETE TYPE', 'cdate' => '2026-08-22 09:00:00',
        ]);
        foreach ([1, 2] as $id) {
            $this->db->table('branch')->insert([
                'branch_id' => $id, 'branch_type' => 1, 'branch_user_name' => 'delete-' . $id,
                'branch_name' => 'DELETE BRANCH ' . $id, 'branch_details' => 'D', 'default_suffix' => 'D' . $id,
                'book_order' => 'D' . $id, 'customer_ref' => 'D' . $id, 'cdate' => '2026-08-22 09:00:00',
            ]);
        }
        $page = $this->getAsAdmin('/branchListing');
        $body = (string) $page->getBody();
        self::assertSame(1, preg_match('/name="csrf_test_name" value="([^"]+)"/', $body, $matches));
        self::assertStringContainsString('data-ci4-security="legacy-csrf"', $body);
        self::assertStringContainsString('/assets/js/legacy-csrf.js', $body);

        try {
            $this->withSession($this->session())->post('/deleteBranch', ['branchid' => '1']);
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }
        self::assertSame(2, $this->db->table('branch')->countAllResults());

        $first = $this->withSession($this->session())->post('/deleteBranch', [
            'branchid' => '1', 'csrf_test_name' => $matches[1],
        ]);
        $first->assertStatus(200);
        $first->assertJSONExact(['status' => true]);
        $nextToken = $first->response()->getHeaderLine('X-CSRF-TOKEN');
        self::assertNotSame('', $nextToken);
        self::assertNotSame($matches[1], $nextToken);

        $second = $this->withSession($this->session())->post('/deleteBranch', [
            'branchid' => '2', 'csrf_test_name' => $nextToken,
        ]);
        $second->assertStatus(200);
        $second->assertJSONExact(['status' => true]);
        self::assertNotSame('', $second->response()->getHeaderLine('X-CSRF-TOKEN'));
        self::assertSame(0, $this->db->table('branch')->countAllResults());

        $script = (string) file_get_contents(PUBLICPATH . 'assets/js/legacy-csrf.js');
        self::assertStringContainsString('jQuery.ajaxPrefilter', $script);
        self::assertStringContainsString("getResponseHeader('X-CSRF-TOKEN')", $script);
        self::assertStringContainsString('csrf_test_name', $script);
    }

    public function testListingPaginatesAtFiftyRowsWithNextLink(): void
    {
        $empty = $this->getAsAdmin('/master/book');
        $empty->assertSee('ฺBookId');
        $empty->assertSee('Branch name');
        $empty->assertDontSee('Number Limit');

        $this->db->table('branch')->insert([
            'branch_id' => 1, 'branch_type' => 1, 'branch_name' => 'PAGE BRANCH',
            'branch_details' => 'D', 'default_suffix' => 'P', 'book_order' => 'P',
            'cdate' => '2026-08-22 09:00:00',
        ]);
        // Prefix 'p' is not a hex character, so markers cannot collide with the
        // 32-hex csrf hash rendered in every form on the page.
        $this->db->table('book')->insert([
            'book_id' => 1, 'branch_id' => 1, 'book_detail' => 'p01', 'status' => 1,
            'bunber_limit' => 1, 'cdate' => '2026-08-22 09:00:00',
        ]);
        $one = $this->getAsAdmin('/master/book');
        $one->assertSee('p01');
        $one->assertSee('PAGE BRANCH');
        $one->assertSee('Publishing');
        $one->assertDontSee('Number Limit');

        for ($i = 2; $i <= 51; $i++) {
            $this->db->table('book')->insert([
                'book_id'      => $i,
                'branch_id'    => 1,
                'book_detail'  => sprintf('p%02d', $i),
                'status'       => 1,
                'bunber_limit' => 1,
                'cdate'        => '2026-08-22 09:00:00',
            ]);
        }

        $page1 = $this->getAsAdmin('/master/book?page=1');
        $page1->assertStatus(200);
        $page1->assertSee('p01');
        $page1->assertSee('p50');
        $page1->assertDontSee('p51');
        $page1->assertSee('PAGE BRANCH');
        $page1->assertSee('Publishing');
        $page1->assertDontSee('Number Limit');
        // CI3 Book::bookListing configures userListing/ as the paginator base; its view
        // intercepts the click and posts the offset to bookListing/.
        self::assertStringContainsString('/userListing/50', (string) $page1->getBody());

        $page2 = $this->getAsAdmin('/master/book?page=2');
        $page2->assertStatus(200);
        $page2->assertSee('p51');
        $page2->assertDontSee('p01');
        $page2->assertSee('PAGE BRANCH');
        $page2->assertSee('Publishing');
        $page2->assertDontSee('Number Limit');
        $page2->assertDontSee('page=3');

        // CI3 pagination keeps the active POST field, then changes form action to the next alias.
        $searched = $this->getAsAdmin('/master/book?search=p&page=1');
        $searched->assertStatus(200);
        $searched->assertSee('value="p"');
        $searched->assertSee('name="searchText" value="p"');
        self::assertStringContainsString('/userListing/50', (string) $searched->getBody());
        $searched->assertDontSee('Number Limit');

        $missing = $this->getAsAdmin('/master/book?search=absent');
        $missing->assertStatus(200);
        $missing->assertSee('value="absent"');
        $missing->assertSee('ฺBookId');
        $missing->assertSee('Branch name');
        $missing->assertDontSee('p01');
        $missing->assertDontSee('Number Limit');
    }

    public function testInvalidPageParameterThrowsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->getAsAdmin('/master/book?page=0');
    }

    public function testBranchTypeImageIsServedPreviewedAndThumbnailed(): void
    {
        $directory = WRITEPATH . 'uploads/branch-types';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $name = str_repeat('a', 32) . '.png';
        $path = $directory . '/' . $name;
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));

        try {
            $this->db->table('branch_type')->insert([
                'branch_type_id'      => 7,
                'branch_type_details' => 'ICON TYPE',
                'branch_type_image'   => $name,
                'cdate'               => '2026-08-22 09:00:00',
            ]);

            $listing = $this->getAsAdmin('/master/branchtype');
            $listing->assertStatus(200);
            $listing->assertSee('<img');
            self::assertStringContainsString(
                'src="' . base_url('branch-type-image/' . $name) . '"',
                (string) $listing->getBody(),
            );

            $edit = $this->getAsAdmin('/master/branchtype/7');
            $edit->assertStatus(200);
            self::assertStringContainsString(
                'src="' . base_url('branch-type-image/' . $name) . '"',
                (string) $edit->getBody(),
            );

            $served = $this->get('/branch-type-image/' . $name);
            $served->assertStatus(200);
            $served->assertHeader('Content-Type', 'image/png');

            $this->get('/branch-type-image/' . str_repeat('b', 32) . '.png')->assertStatus(404);
            $this->get('/branch-type-image/not-a-valid-name.png')->assertStatus(404);
        } finally {
            @unlink($path);
        }
    }

    public function testListingShowsTableAndAddNewLinkButNoEntityFormForAllTypes(): void
    {
        // AC-1 + AC-5: every listing renders a table + an Add New link to /master/<type>/new,
        // and carries no entity <form method="post"> (the reset button only exists in that form).
        $ci3AddRoutes = [
            'branch' => 'BranchNew', 'branchtype' => 'add_new_branchtype',
            'statustype' => 'add_new_statustype', 'producttype' => 'add_new_producttype',
            'book' => 'BookNew', 'brand' => 'add_new_brand', 'condition' => 'add_new_condition',
            'estimateprice' => 'add_new_estimateprice', 'fixed' => 'add_new_fixed',
            'provider' => 'add_new_provider',
        ];
        foreach (array_keys($this->definitions()) as $type) {
            $listing = $this->getAsAdmin('/master/' . $type);
            $listing->assertStatus(200);
            $body = (string) $listing->getBody();
            self::assertStringContainsString('<table', $body, $type);
            self::assertStringContainsString('Add New', $body, $type);
            self::assertStringContainsString('http://example.invalid/' . $ci3AddRoutes[$type], $body, $type);
            // AC-1: no entity create form on the listing. The entity form posts to /master/<type>
            // exactly (create) or /master/<type>/<id> (update); the search form is GET and the
            // per-row delete forms post to /master/<type>/<id>/delete, so neither matches this
            // method+action pair.
            self::assertStringNotContainsString('method="post" action="/master/' . $type . '"', $body, $type);
        }
    }

    public function testCi3MasterGetAliasesReachSameListingAndForm(): void
    {
        foreach ([
            'branch' => ['branchListing', 'BranchNew', 'addNewBranch', 'editBranchOld', 'editBranch'],
            'branchtype' => ['branchtypeListing', 'add_new_branchtype', 'addNewBranchtype', 'editBranchtypeOld', 'editBranchtype'],
            'statustype' => ['statustypeListing', 'add_new_statustype', 'addNewStatustype', 'editStatustypeOld', 'editStatustype'],
            'producttype' => ['producttypeListing', 'add_new_producttype', 'addNewProducttype', 'editProducttypeOld', 'editProducttype'],
            'book' => ['bookListing', 'BookNew', 'addNewBook', 'editBookOld', 'editBook'],
            'brand' => ['brandListing', 'add_new_brand', 'addNewBrand', 'editBrandOld', 'editBrand'],
            'condition' => ['conditionListing', 'add_new_condition', 'addNewCondition', 'editConditionOld', 'editCondition'],
            'estimateprice' => ['estimatepriceListing', 'add_new_estimateprice', 'addNewEstimateprice', 'editEstimatepriceOld', 'editEstimateprice'],
            'fixed' => ['fixedListing', 'add_new_fixed', 'addNewFixed', 'editFixedOld', 'editFixed'],
            'provider' => ['providerListing', 'add_new_provider', 'addNewProvider', 'editProviderOld', 'editProvider'],
        ] as $type => [$listing, $new, $create, $edit, $update]) {
            $id = $this->seedRow($type);
            $this->getAsAdmin('/' . $listing)->assertStatus(200);
            $this->getAsAdmin('/' . $listing . '/1')->assertStatus(200);
            $add = $this->getAsAdmin('/' . $new);
            $add->assertStatus(200);
            self::assertStringContainsString('action="http://example.invalid/' . $create . '"', (string) $add->getBody(), $type);
            $this->getAsAdmin('/' . $edit)->assertRedirectTo('/' . $listing);
            $editPage = $this->getAsAdmin('/' . $edit . '/' . $id);
            $editPage->assertStatus(200);
            self::assertStringContainsString('action="http://example.invalid/' . $update . '"', (string) $editPage->getBody(), $type);
        }
    }

    public function testCi3MasterPostAliasesCreateAndUpdateUsingSourcePayloadNames(): void
    {
        $routes = [
            'branch' => ['addNewBranch', 'editBranch', 'branchListing', 'BranchNew'],
            'branchtype' => ['addNewBranchtype', 'editBranchtype', 'branchtypeListing', 'add_new_branchtype'],
            'statustype' => ['addNewStatustype', 'editStatustype', 'statustypeListing', 'add_new_statustype'],
            'producttype' => ['addNewProducttype', 'editProducttype', 'producttypeListing', 'add_new_producttype'],
            'book' => ['addNewBook', 'editBook', 'bookListing', 'BookNew'],
            'brand' => ['addNewBrand', 'editBrand', 'brandListing', 'add_new_brand'],
            'condition' => ['addNewCondition', 'editCondition', 'conditionListing', 'add_new_condition'],
            'estimateprice' => ['addNewEstimateprice', 'editEstimateprice', 'estimatepriceListing', 'add_new_estimateprice'],
            'fixed' => ['addNewFixed', 'editFixed', 'fixedListing', 'add_new_fixed'],
            'provider' => ['addNewProvider', 'editProvider', 'providerListing', 'add_new_provider'],
        ];
        foreach ($routes as $type => [$create, $update, $listing, $new]) {
            $definition = $this->definitions()[$type];
            $created = $this->legacyPayload($type, 'LEGACY');
            $this->postAsAdmin('/' . $create, $created)->assertRedirectTo('/' . $new);
            $row = $this->db->table($definition['table'])->where($definition['label'], $type === 'provider' ? 'LEGACY' : ($created[$definition['label']] ?? 'LEGACY'))->get()->getRowArray();
            self::assertNotNull($row, $type);

            $updated = $this->legacyPayload($type, 'UPDATED');
            $updated[$definition['pk']] = (string) $row[$definition['pk']];
            $this->postAsAdmin('/' . $update, $updated)->assertRedirectTo('/' . $listing);
        }
    }

    public function testAddPageShowsBlankFormWithResetButNoListingTableForAllTypes(): void
    {
        // AC-2 + AC-4: the add page renders an entity form with a reset button and no listing table.
        foreach (array_keys($this->definitions()) as $type) {
            $add = $this->getAsAdmin('/master/' . $type . '/new');
            $add->assertStatus(200);
            $body = (string) $add->getBody();
            self::assertStringContainsString('<form role="form"', $body, $type);
            self::assertStringContainsString('method="post"', $body, $type);
            self::assertStringContainsString('type="reset"', $body, $type);
            self::assertMatchesRegularExpression('/<input[^>]*type="submit"[^>]*value="Submit"/s', $body, $type);
            self::assertStringNotContainsString('<table', $body, $type);
        }
    }

    public function testEditPageShowsFilledFormWithResetButNoListingTableForAllTypes(): void
    {
        // AC-3 + AC-4: the edit page renders the row's values in an entity form with a reset
        // button and no listing table.
        foreach ($this->definitions() as $type => $def) {
            $id = $this->seedRow($type);
            $edit = $this->getAsAdmin('/master/' . $type . '/' . $id);
            $edit->assertStatus(200);
            $body = (string) $edit->getBody();
            self::assertStringContainsString('<form role="form"', $body, $type);
            self::assertStringContainsString('method="post"', $body, $type);
            self::assertStringContainsString('type="reset"', $body, $type);
            $sourcePrimaryKey = $type === 'book' ? 'bookId' : $def['pk'];
            self::assertMatchesRegularExpression(
                '/<input(?=[^>]*\bname="' . preg_quote($sourcePrimaryKey, '/') . '")(?=[^>]*\bvalue="' . $id . '")[^>]*>/s',
                $body,
                $type,
            );
            // The label column value is prefilled on the edit form.
            self::assertStringContainsString('value="ZQX"', $body, $type);
            self::assertStringNotContainsString('<table', $body, $type);
        }
    }

    public function testAddPageIsDeniedWithoutReadLikeEditPageForAllTypes(): void
    {
        // AC-6: a guest (no read) is refused GET /master/<type>/new with the same result as
        // the existing GET /master/<type>/<id>, proving the route filter is wired (not a bare route).
        foreach ($this->definitions() as $type => $def) {
            $id = $this->seedRow($type);
            $editStatus = $this->get('/master/' . $type . '/' . $id)->response()->getStatusCode();
            $newStatus  = $this->get('/master/' . $type . '/new')->response()->getStatusCode();
            self::assertSame($editStatus, $newStatus, $type);
            self::assertNotSame(200, $newStatus, $type);
        }
    }

    private function seedRow(string $type): int
    {
        $def  = $this->definitions()[$type];
        $seed = $this->payload($type, 'ZQX');
        unset($seed['csrf_test_name']);
        $seed['cdate'] = '2026-08-22 09:00:00';
        $this->db->table($def['table'])->insert($seed);

        return (int) $this->db->insertID();
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

    /** @return array<string, string> */
    private function legacyPayload(string $type, string $label): array
    {
        $payload = $this->payload($type, $label);
        unset($payload['csrf_test_name'], $payload['bunber_limit']);
        if ($type === 'provider') {
            $payload['provider_details'] = $payload['provider_datail'];
            unset($payload['provider_datail']);
        }

        return $payload;
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
            'tracking_status' => 'status_id INTEGER PRIMARY KEY AUTOINCREMENT, description_th VARCHAR(250) NOT NULL, description_en VARCHAR(250) NOT NULL, success INTEGER, cdate DATETIME NOT NULL',
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
