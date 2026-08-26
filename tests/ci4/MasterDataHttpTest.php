<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Master\MasterCatalog;
use App\Master\MasterDataStore;
use CodeIgniter\Exceptions\PageNotFoundException;
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
            '<th>Id</th> <th>Branch type</th> <th>Branch User</th> <th>Branch name</th> <th>Branch suffix</th> <th>Ref</th> <th>Actions</th>',
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
            '<th>ฺBookId</th> <th>Branch name</th> <th>Book Details</th> <th>Status</th> <th>Actions</th>',
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

    public function testBranchAndBookFormOnlyFieldsRemainValidatedAndPersisted(): void
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
        $branch['book_order'] = '';
        $this->postAsAdmin('/master/branch/' . $branchId, $branch)->assertStatus(422);
        self::assertSame('UPDATED', $this->db->table('branch')->where('branch_id', $branchId)->get()->getRow('book_order'));

        $book = $this->payload('book', 'FRM');
        $book['bunber_limit'] = '321';
        $this->postAsAdmin('/master/book', $book)->assertRedirectTo('/master/book');
        $bookId = (int) $this->db->insertID();
        self::assertSame(321, (int) $this->db->table('book')->where('book_id', $bookId)->get()->getRow('bunber_limit'));
        foreach (['/master/book/new', '/master/book/' . $bookId] as $path) {
            self::assertStringContainsString('name="bunber_limit"', (string) $this->getAsAdmin($path)->getBody(), $path);
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
        $listing->assertSee('/master/brand/' . $brandId . '/delete');
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
        $empty = html_entity_decode((string) $this->getAsAdmin('/master/branch')->getBody());
        self::assertStringContainsString('<th>Branch type</th>', $empty);
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
        self::assertStringContainsString('page=2', $fifty);
        self::assertStringNotContainsString('HIDDEN-DETAIL-', $fifty);
        self::assertStringNotContainsString('HIDDEN-ORDER-', $fifty);
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
        $page1->assertSee('page=2');

        $page2 = $this->getAsAdmin('/master/book?page=2');
        $page2->assertStatus(200);
        $page2->assertSee('p51');
        $page2->assertDontSee('p01');
        $page2->assertSee('PAGE BRANCH');
        $page2->assertSee('Publishing');
        $page2->assertDontSee('Number Limit');
        $page2->assertDontSee('page=3');

        // Next link carries the active search term (every book_detail starts with 'p').
        $searched = $this->getAsAdmin('/master/book?search=p&page=1');
        $searched->assertStatus(200);
        $searched->assertSee('value="p"');
        $searched->assertSee('search=p');
        $searched->assertSee('page=2');
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
            $listing->assertSee('/branch-type-image/' . $name);

            $edit = $this->getAsAdmin('/master/branchtype/7');
            $edit->assertStatus(200);
            $edit->assertSee('/branch-type-image/' . $name);

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
        foreach (array_keys($this->definitions()) as $type) {
            $listing = $this->getAsAdmin('/master/' . $type);
            $listing->assertStatus(200);
            $body = (string) $listing->getBody();
            self::assertStringContainsString('<table', $body, $type);
            self::assertStringContainsString('Add New', $body, $type);
            self::assertStringContainsString('/master/' . $type . '/new', $body, $type);
            // AC-1: no entity create form on the listing. The entity form posts to /master/<type>
            // exactly (create) or /master/<type>/<id> (update); the search form is GET and the
            // per-row delete forms post to /master/<type>/<id>/delete, so neither matches this
            // method+action pair.
            self::assertStringNotContainsString('method="post" action="/master/' . $type . '"', $body, $type);
        }
    }

    public function testAddPageShowsBlankFormWithResetButNoListingTableForAllTypes(): void
    {
        // AC-2 + AC-4: the add page renders an entity form with a reset button and no listing table.
        foreach (array_keys($this->definitions()) as $type) {
            $add = $this->getAsAdmin('/master/' . $type . '/new');
            $add->assertStatus(200);
            $body = (string) $add->getBody();
            self::assertStringContainsString('<form method="post"', $body, $type);
            self::assertStringContainsString('type="reset"', $body, $type);
            self::assertStringContainsString('>Submit</button>', $body, $type);
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
            self::assertStringContainsString('<form method="post"', $body, $type);
            self::assertStringContainsString('type="reset"', $body, $type);
            self::assertStringContainsString('action="/master/' . $type . '/' . $id . '"', $body, $type);
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
