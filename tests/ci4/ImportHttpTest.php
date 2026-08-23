<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Config\Services;
use CodeIgniter\Security\Exceptions\SecurityException;
use ZipArchive;

final class ImportHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $encryption = new Encryption();
        $encryption->driver = 'Sodium';
        $encryption->key = str_repeat("\x50", 32);
        Services::injectMock('encrypter', Services::encrypter($encryption, false));
        foreach ([
            'request_order' => 'request_id INTEGER PRIMARY KEY AUTOINCREMENT, requestDate DATETIME NOT NULL, trackID VARCHAR(100) NOT NULL UNIQUE, numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100), customerFullname VARCHAR(250), customerTel VARCHAR(100), detailTypeId INTEGER, detailBrandId INTEGER, branchID INTEGER, branch_type_id INTEGER, UserID INTEGER, date_create DATETIME, date_repair DATETIME, date_repair_complete DATETIME, date_repair_waranty DATETIME, date_update_status DATETIME, action_status INTEGER, RepairPrice DECIMAL(8,2), waranty_cmg VARCHAR(100), number_cmg VARCHAR(100), create_by_user VARCHAR(250)',
            'status_log' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
            'tracking_status' => 'status_id INTEGER PRIMARY KEY, description_th VARCHAR(250), description_en VARCHAR(250) NOT NULL, success INTEGER NOT NULL',
            'branch' => 'branch_id INTEGER PRIMARY KEY, branch_type INTEGER NOT NULL, default_suffix VARCHAR(10) NOT NULL',
            'uploadstaus' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, tracking_id VARCHAR(250), Listname VARCHAR(250), Telephone VARCHAR(100), updatetime VARCHAR(100), startdate VARCHAR(250), Userstatus VARCHAR(100), tracking_status INTEGER, cdate DATE, user_id INTEGER, RepairPrice DECIMAL(8,2), waranty_cmg VARCHAR(100), number_cmg VARCHAR(100)',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->table('ci4_users')->truncate();
        $this->db->table('ci4_delivery_intents')->truncate();
        if ($this->db->tableExists($this->db->prefixTable('ci4_import_rows'), false)) {
            $this->db->table('ci4_import_rows')->truncate();
            $this->db->table('ci4_import_batches')->truncate();
        }
        if ($this->db->tableExists($this->db->prefixTable('ci4_order_sequences'), false)) {
            $this->db->table('ci4_order_sequences')->truncate();
        }
        $this->db->resetDataCache();
        $this->db->table('branch')->insertBatch([
            ['branch_id' => 1, 'branch_type' => 1, 'default_suffix' => 'WPA'],
            ['branch_id' => 2, 'branch_type' => 2, 'default_suffix' => 'WPB'],
        ]);
        $this->db->table('tracking_status')->insertBatch([
            ['status_id' => 1, 'description_th' => 'สำเร็จ', 'description_en' => 'SUCCESS', 'success' => 1],
            ['status_id' => 2, 'description_th' => 'กำลังซ่อม', 'description_en' => 'REPAIR', 'success' => 0],
        ]);
        $this->db->table('request_order')->insertBatch([
            ['request_id' => 1, 'requestDate' => '2026-08-01 00:00:00', 'trackID' => 'TRACK-STATUS', 'orderIDShow' => 'WPA/100', 'customerFullname' => 'STATUS CUSTOMER', 'customerTel' => '0000000000', 'branchID' => 1, 'branch_type_id' => 1, 'action_status' => 2, 'RepairPrice' => '100.00', 'number_cmg' => 'CMG-STATUS'],
            ['request_id' => 2, 'requestDate' => '2026-08-01 00:00:00', 'trackID' => 'TRACK-PRICE', 'orderIDShow' => 'WPA/200', 'customerFullname' => 'PRICE CUSTOMER', 'customerTel' => '0000000000', 'branchID' => 1, 'branch_type_id' => 1, 'action_status' => 4, 'RepairPrice' => '100.00', 'number_cmg' => 'CMG-PRICE'],
        ]);
        $users = new ShadowUserStore($this->db);
        $users->create('import-a@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 1);
        $users->create('import-b@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 2, 2);
    }

    public function testThreePreviewsCreateOwnedEncryptedBatchesWithoutBusinessWrites(): void
    {
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $files = [
            'status' => $this->xlsx([$headers, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS'], ['', 'INVALID', '', '', '', '', '', '', '']]),
            'price' => $this->xlsx([$headers, ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE']]),
            'new-order' => $this->xlsx([$headers, ['WPA/300', 'NEW CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '300.00', 'IN', 'CMG-NEW']]),
        ];
        foreach ($files as $kind => $path) {
            $response = $this->preview($kind, $path);
            $response->assertStatus(200);
            $response->assertSee('Accepted: 1');
            $response->assertSee($kind === 'status' ? 'Rejected: 1' : 'Rejected: 0');
        }
        self::assertSame(3, $this->db->table('ci4_import_batches')->where('owner_user_id', 1)->countAllResults());
        self::assertSame(4, $this->db->table('ci4_import_rows')->countAllResults());
        foreach ($this->db->table('ci4_import_rows')->get()->getResultArray() as $row) {
            self::assertStringNotContainsString('CUSTOMER', (string) $row['payload_ciphertext']);
            self::assertStringNotContainsString('0000000000', (string) $row['payload_ciphertext']);
        }
        self::assertSame(2, $this->db->table('request_order')->countAllResults());
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->countAllResults());
    }

    public function testThreeConfirmsApplyAcceptedRowsOnceAndReplayWithoutExtraWrites(): void
    {
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $files = [
            'status' => $this->xlsx([$headers, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS'], ['', 'INVALID', '', '', '', '', '', '', '']]),
            'price' => $this->xlsx([$headers, ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE']]),
            'new-order' => $this->xlsx([$headers, ['WPA/300', 'NEW CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '300.00', 'IN', 'CMG-NEW']]),
        ];
        $batches = [];
        foreach ($files as $kind => $path) {
            $this->preview($kind, $path)->assertStatus(200);
            $batches[$kind] = (string) $this->db->table('ci4_import_batches')
                ->select('batch_id')->where('kind', $kind)->get()->getRow('batch_id');
        }
        foreach ($batches as $kind => $batchId) {
            $this->confirm($kind, $batchId)->assertRedirectTo('/imports/' . $kind . '?confirmed=1');
        }

        $status = $this->db->table('request_order')->where('request_id', 1)->get()->getRowArray();
        self::assertSame(4, (int) $status['action_status']);
        self::assertSame('250.00', number_format((float) $status['RepairPrice'], 2, '.', ''));
        self::assertSame('2026-08-20 00:00:00', $status['date_repair']);
        self::assertSame('2026-08-22 00:00:00', $status['date_update_status']);
        self::assertSame('2026-08-22 00:00:00', $status['date_repair_complete']);
        self::assertSame('CMG-STATUS', $status['number_cmg']);
        self::assertSame('275.50', number_format((float) $this->db->table('request_order')->where('request_id', 2)->get()->getRow('RepairPrice'), 2, '.', ''));

        $created = $this->db->table('request_order')->where('orderIDShow', 'WPA/300')->get()->getRowArray();
        self::assertNotNull($created);
        self::assertMatchesRegularExpression('/\AWPA[0-9]{8}\z/', (string) $created['trackID']);
        self::assertSame(4, (int) $created['action_status']);
        self::assertSame(1, (int) $created['branchID']);
        self::assertSame('NEW CUSTOMER', $created['customerFullname']);
        self::assertSame('300.00', number_format((float) $created['RepairPrice'], 2, '.', ''));
        self::assertSame(2, $this->db->table('status_log')->countAllResults());
        self::assertSame(1, $this->db->table('status_log')->where('order_id', 'TRACK-STATUS')->where('update_id', 1)->countAllResults());
        self::assertSame(1, $this->db->table('status_log')->where('order_id', $created['trackID'])->where('action_id', 4)->countAllResults());
        self::assertSame(1, $this->db->table('uploadstaus')->countAllResults());
        self::assertSame(3, $this->db->table('ci4_import_batches')->where('state', 'confirmed')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->countAllResults());

        foreach ($batches as $kind => $batchId) {
            $this->confirm($kind, $batchId)->assertRedirectTo('/imports/' . $kind . '?confirmed=1');
        }
        self::assertSame(3, $this->db->table('request_order')->countAllResults());
        self::assertSame(2, $this->db->table('status_log')->countAllResults());
        self::assertSame(1, $this->db->table('uploadstaus')->countAllResults());
    }

    public function testMalformedWrongHeaderEmptyAndInvalidBatchInputsWriteNothing(): void
    {
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $valid = $this->xlsx([$headers, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS']]);
        $this->previewAs('status', $valid, 1, 1, 'status.csv')->assertStatus(422);

        $malformed = tempnam(sys_get_temp_dir(), 'wp00c-bad-xlsx-');
        self::assertIsString($malformed);
        file_put_contents($malformed, 'not an xlsx archive');
        $this->previewAs('status', $malformed, 1, 1, 'status.xlsx')->assertStatus(422);

        $wrongHeaders = $headers;
        $wrongHeaders[0] = 'tracking_id';
        $this->preview('status', $this->xlsx([$wrongHeaders, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS']]))->assertStatus(422);
        $this->preview('status', $this->xlsx([$headers]))->assertStatus(422);
        $this->confirm('status', 'not-a-batch')->assertStatus(422);

        self::assertSame(0, $this->db->table('ci4_import_batches')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_import_rows')->countAllResults());
        self::assertSame(2, $this->db->table('request_order')->countAllResults());
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
        self::assertSame(0, $this->db->table('uploadstaus')->countAllResults());
    }

    public function testBatchOwnershipAndBranchScopePreventCrossUserContamination(): void
    {
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $this->previewAs('new-order', $this->xlsx([
            $headers, ['WPA/A-1', 'OWNER A', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '111.00', 'IN', 'A-1'],
        ]), 1, 1)->assertStatus(200);
        $batchA = (string) $this->db->table('ci4_import_batches')->where('owner_user_id', 1)->get()->getRow('batch_id');
        $this->previewAs('new-order', $this->xlsx([
            $headers, ['WPB/B-1', 'OWNER B', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '222.00', 'IN', 'B-1'],
        ]), 2, 2)->assertStatus(200);
        $batchB = (string) $this->db->table('ci4_import_batches')->where('owner_user_id', 2)->get()->getRow('batch_id');

        $this->confirmAs('new-order', $batchA, 2, 2)->assertStatus(404);
        self::assertSame('previewed', $this->db->table('ci4_import_batches')->where('batch_id', $batchA)->get()->getRow('state'));
        $this->confirmAs('new-order', $batchA, 1, 1)->assertRedirect();
        $this->confirmAs('new-order', $batchB, 2, 2)->assertRedirect();

        $orderA = $this->db->table('request_order')->where('orderIDShow', 'WPA/A-1')->get()->getRowArray();
        $orderB = $this->db->table('request_order')->where('orderIDShow', 'WPB/B-1')->get()->getRowArray();
        self::assertNotNull($orderA);
        self::assertNotNull($orderB);
        self::assertSame(1, (int) $orderA['branchID']);
        self::assertSame(2, (int) $orderB['branchID']);
        self::assertNotSame($orderA['trackID'], $orderB['trackID']);
        self::assertSame(2, $this->db->table('status_log')->countAllResults());
        self::assertSame(2, $this->db->table('ci4_import_batches')->where('state', 'confirmed')->countAllResults());
    }

    public function testTokenlessConfirmForEveryImportKindIsRejectedBeforeBusinessWrite(): void
    {
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        foreach ([
            'status' => ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS'],
            'price' => ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE'],
            'new-order' => ['WPA/300', 'NEW CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '300.00', 'IN', 'CMG-NEW'],
        ] as $kind => $row) {
            $this->preview($kind, $this->xlsx([$headers, $row]))->assertSee('csrf_test_name');
            $batch = (string) $this->db->table('ci4_import_batches')->where('kind', $kind)->get()->getRow('batch_id');
            try {
                $this->withSession($this->session(1, 1))->post('/imports/' . $kind . '/' . $batch . '/confirm');
                self::fail('Expected CSRF rejection.');
            } catch (SecurityException $exception) {
                self::assertSame(403, $exception->getCode());
            }
        }

        self::assertSame(3, $this->db->table('ci4_import_batches')->where('state', 'previewed')->countAllResults());
        self::assertSame(2, $this->db->table('request_order')->countAllResults());
        self::assertSame(2, (int) $this->db->table('request_order')->where('request_id', 1)->get()->getRow('action_status'));
        self::assertSame('100.00', number_format((float) $this->db->table('request_order')->where('request_id', 2)->get()->getRow('RepairPrice'), 2, '.', ''));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
        self::assertSame(0, $this->db->table('uploadstaus')->countAllResults());
    }

    public function testLegacyListingPreviewAndConfirmRoutesUseOwnedBatchWorkflow(): void
    {
        foreach (['/UploadexcelListing', '/UploadexcelpriceListing', '/UploadneworderexcelListing'] as $path) {
            $this->withSession($this->session(1, 1))->get($path)->assertStatus(200);
        }
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $flows = [
            'status' => ['/ExcelDataAdd', '/ExcelConfirm', ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS']],
            'price' => ['/ExcelPriceDataAdd', '/ExcelPriceConfirm', ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE']],
            'new-order' => ['/ExcelNewOrderDataAdd', '/ExcelNewOrderConfirm', ['WPA/300', 'NEW CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '300.00', 'IN', 'CMG-NEW']],
        ];
        foreach ($flows as $kind => [$previewPath, $confirmPath, $row]) {
            $this->previewAs($kind, $this->xlsx([$headers, $row]), 1, 1, null, $previewPath)->assertStatus(200);
            $batch = (string) $this->db->table('ci4_import_batches')->where('kind', $kind)->get()->getRow('batch_id');
            $this->withSession($this->session(1, 1))->post($confirmPath, [
                'batch_id' => $batch, 'csrf_test_name' => service('security')->getHash(),
            ])->assertRedirectTo('/imports/' . $kind . '?confirmed=1');
        }
        self::assertSame(3, $this->db->table('ci4_import_batches')->where('state', 'confirmed')->countAllResults());
    }

    public function testSuccessfulImportRetainsSourceFileUnderWritableAndAuditDownloadServesIt(): void
    {
        $directory = WRITEPATH . 'uploads/imports/';
        $before = glob($directory . '*.xlsx') ?: [];
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $path = $this->xlsx([$headers, ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE']]);
        $this->preview('price', $path)->assertStatus(200);

        $sha = (string) $this->db->table('ci4_import_batches')->where('kind', 'price')->get()->getRow('file_sha256');
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $sha);
        self::assertSame($sha, hash_file('sha256', $path));
        self::assertFileExists($directory . $sha . '.xlsx');

        $download = $this->withSession($this->session(1, 1))->get('/imports/file/' . $sha . '.xlsx');
        $download->assertStatus(200);
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $download->response()->getHeaderLine('Content-Type'),
        );
        self::assertSame((string) file_get_contents($path), (string) $download->response()->getBody());

        $this->cleanupImports($before);
    }

    public function testAuditDownloadRequiresAuthAndValidatesFilenameFormat(): void
    {
        $directory = WRITEPATH . 'uploads/imports/';
        $before = glob($directory . '*.xlsx') ?: [];
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];
        $path = $this->xlsx([$headers, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS']]);
        $this->preview('status', $path)->assertStatus(200);
        $sha = (string) $this->db->table('ci4_import_batches')->where('kind', 'status')->get()->getRow('file_sha256');
        self::assertFileExists($directory . $sha . '.xlsx');

        $this->withSession([])->get('/imports/file/' . $sha . '.xlsx')->assertStatus(401);
        $this->withSession($this->session(1, 1))->get('/imports/file/not_a_hash.xlsx')->assertStatus(404);
        $this->withSession($this->session(1, 1))->get('/imports/file/' . str_repeat('a', 64) . '.xlsx')->assertStatus(404);

        $this->cleanupImports($before);
    }

    public function testFailedValidationStoresNoSourceFileAndDuplicateImportIsDeduped(): void
    {
        $directory = WRITEPATH . 'uploads/imports/';
        $before = glob($directory . '*.xlsx') ?: [];
        $headers = ['order_id', 'customer_name', 'telephone', 'updated_at', 'status', 'repair_started_at', 'repair_price', 'warranty', 'number_cmg'];

        $wrongHeaders = $headers;
        $wrongHeaders[0] = 'tracking_id';
        $this->preview('status', $this->xlsx([$wrongHeaders, ['WPA/100', 'STATUS CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '250.00', 'IN', 'CMG-STATUS']]))->assertStatus(422);
        self::assertSame($before, glob($directory . '*.xlsx') ?: []);

        $path = $this->xlsx([$headers, ['WPA/200', 'PRICE CUSTOMER', '0000000000', '22/08/2026', 'SUCCESS', '20/08/2026', '275.50', 'IN', 'CMG-PRICE']]);
        $this->preview('price', $path)->assertStatus(200);
        $sha = (string) hash_file('sha256', $path);
        $duplicate = tempnam(sys_get_temp_dir(), 'wp05c-dup-');
        self::assertIsString($duplicate);
        self::assertNotFalse(copy($path, $duplicate));
        $this->preview('price', $duplicate)->assertStatus(200);
        self::assertSame([$directory . $sha . '.xlsx'], array_values(array_diff(glob($directory . '*.xlsx') ?: [], $before)));

        $this->cleanupImports($before);
    }

    /** @param list<string> $keep */
    private function cleanupImports(array $keep): void
    {
        foreach (glob(WRITEPATH . 'uploads/imports/*.xlsx') ?: [] as $file) {
            if (! in_array($file, $keep, true)) {
                unlink($file);
            }
        }
    }

    private function preview(string $kind, string $path)
    {
        return $this->previewAs($kind, $path, 1, 1);
    }

    private function previewAs(
        string $kind,
        string $path,
        int $userId,
        int $branchId,
        ?string $name = null,
        ?string $uri = null,
    )
    {
        service('superglobals')->setFilesArray(['file' => [
            'name' => $name ?? $kind . '.xlsx',
            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ]]);

        return $this->withSession($this->session($userId, $branchId))->post($uri ?? '/imports/' . $kind . '/preview', [
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function confirm(string $kind, string $batchId)
    {
        return $this->confirmAs($kind, $batchId, 1, 1);
    }

    private function confirmAs(string $kind, string $batchId, int $userId, int $branchId)
    {
        return $this->withSession($this->session($userId, $branchId))->post('/imports/' . $kind . '/' . $batchId . '/confirm', [
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    /** @return array<string, int|bool> */
    private function session(int $id, int $branch): array
    {
        return ['userId' => $id, 'role' => 2, 'GroupID' => 4, 'BranchID' => $branch, 'sessionVersion' => 1, 'isLoggedIn' => true];
    }

    /** @param list<list<string>> $rows */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp00c-xlsx-');
        self::assertIsString($path);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $sheet .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $column => $value) {
                $letter = chr(65 + $column);
                $sheet .= '<c r="' . $letter . ($rowIndex + 1) . '" t="inlineStr"><is><t>'
                    . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet . '</sheetData></worksheet>');
        self::assertTrue($zip->close());

        return $path;
    }
}
