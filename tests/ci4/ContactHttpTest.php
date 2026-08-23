<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Config\Services;

final class ContactHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $name = $this->db->escapeIdentifiers($this->db->prefixTable('contact'));
        $this->db->query("CREATE TABLE IF NOT EXISTS {$name} (id INTEGER PRIMARY KEY AUTOINCREMENT, fullname VARCHAR(128) NOT NULL, email VARCHAR(128) NOT NULL, samsoniteid VARCHAR(100), phone VARCHAR(32) NOT NULL, detail TEXT NOT NULL, cdate DATETIME NOT NULL)");
        $this->db->table('contact')->truncate();
        $this->db->table('ci4_delivery_intents')->truncate();

        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x30", 32);
        Services::injectMock('encrypter', Services::encrypter($config, false));
    }

    public function testEnglishAndThaiSubmissionsPersistContactAndEncryptedDeliveryIntent(): void
    {
        $englishForm = $this->get('/contact');
        $englishForm->assertStatus(200);
        $englishForm->assertSee('Contact us');
        $thaiForm = $this->get('/contact-th');
        $thaiForm->assertStatus(200);
        $thaiForm->assertSee('ติดต่อเรา');

        $english = $this->post('/contact', $this->payload('a1', 'SYNTHETIC CONTACT EN'));
        $english->assertRedirectTo('/contact?submitted=1');
        $thai = $this->post('/contact-th', $this->payload('b2', 'SYNTHETIC CONTACT TH'));
        $thai->assertRedirectTo('/contact-th?submitted=1');
        self::assertSame(2, $this->db->table('contact')->countAllResults());
        self::assertSame(2, $this->db->table('ci4_delivery_intents')->where('kind', 'contact')->countAllResults());
        $ciphertexts = array_column(
            $this->db->table('ci4_delivery_intents')->select('payload_ciphertext')->get()->getResultArray(),
            'payload_ciphertext',
        );
        foreach ($ciphertexts as $ciphertext) {
            self::assertStringNotContainsString('wp00c-contact@example.invalid', $ciphertext);
            self::assertStringNotContainsString('SYNTHETIC CONTACT', $ciphertext);
        }
    }

    public function testInvalidReplayAndUnavailableDeliveryDoNotCreatePartialOrDuplicateRows(): void
    {
        $invalid = $this->payload('c3', 'SYNTHETIC INVALID');
        $invalid['email'] = 'not-an-email';
        $this->post('/contact', $invalid)->assertStatus(422);
        self::assertSame(0, $this->db->table('contact')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->countAllResults());

        $valid = $this->payload('d4', 'SYNTHETIC REPLAY');
        $this->post('/contact', $valid)->assertRedirectTo('/contact?submitted=1');
        $valid['csrf_test_name'] = service('security')->getHash();
        $this->post('/contact', $valid)->assertRedirectTo('/contact?submitted=1');
        self::assertSame(1, $this->db->table('contact')->countAllResults());
        self::assertSame(1, $this->db->table('ci4_delivery_intents')->countAllResults());

        Services::resetSingle('encrypter');
        $this->post('/contact', $this->payload('e5', 'SYNTHETIC UNAVAILABLE'))->assertStatus(503);
        self::assertSame(1, $this->db->table('contact')->countAllResults());
        self::assertSame(1, $this->db->table('ci4_delivery_intents')->countAllResults());
    }

    public function testContactListingIsSearchableByAdminAndHiddenFromBranchRole(): void
    {
        $this->db->table('contact')->insertBatch([
            [
                'fullname'    => 'SYNTHETIC CONTACT A',
                'email'       => 'contact-a@example.invalid',
                'samsoniteid' => 'WP00C-TRACK-001',
                'phone'       => '0000000000',
                'detail'      => 'SYNTHETIC A',
                'cdate'       => '2026-08-22 09:00:00',
            ],
            [
                'fullname'    => 'SYNTHETIC CONTACT B',
                'email'       => 'contact-b@example.invalid',
                'samsoniteid' => 'WP00C-TRACK-002',
                'phone'       => '0000000000',
                'detail'      => 'SYNTHETIC B',
                'cdate'       => '2026-08-22 10:00:00',
            ],
        ]);
        $users = new ShadowUserStore($this->db);
        $adminId = $users->create('contact-admin@example.invalid', password_hash('Synthetic passphrase', PASSWORD_DEFAULT), 1, null);
        $operatorId = $users->create('contact-operator@example.invalid', password_hash('Synthetic passphrase', PASSWORD_DEFAULT), 2, 1);

        $admin = $this->withSession($this->sessionFor($adminId, 1, null))
            ->get('/contact-list?search=CONTACT+B');
        $admin->assertStatus(200);
        $admin->assertSee('SYNTHETIC CONTACT B');
        $admin->assertDontSee('SYNTHETIC CONTACT A');

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $this->withSession($this->sessionFor($operatorId, 2, 1))->get('/contact-list');
    }

    /** @return array<string, string> */
    private function payload(string $suffix, string $name): array
    {
        return [
            'csrf_test_name' => service('security')->getHash(),
            'submission_id'  => str_repeat($suffix, 16),
            'fullname'       => $name,
            'email'          => 'wp00c-contact@example.invalid',
            'phone'          => '0000000000',
            'detail'         => 'SYNTHETIC CONTACT MESSAGE',
        ];
    }

    /** @return array<string, int|bool|null> */
    private function sessionFor(int $userId, int $role, ?int $branchId): array
    {
        return [
            'userId'         => $userId,
            'role'           => $role,
            'BranchID'       => $branchId,
            'sessionVersion' => 1,
            'isLoggedIn'     => true,
        ];
    }
}
