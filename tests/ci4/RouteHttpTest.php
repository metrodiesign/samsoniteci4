<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class RouteHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $name = $this->db->escapeIdentifiers($this->db->prefixTable('book'));
        $this->db->query("DROP TABLE IF EXISTS {$name}");
        $this->db->query("CREATE TABLE {$name} (book_id INTEGER PRIMARY KEY AUTOINCREMENT, branch_id INTEGER, book_detail VARCHAR(3), status INTEGER, bunber_limit INTEGER, cdate DATETIME)");
        $this->db->resetDataCache();
        $this->db->table('book')->insert([
            'branch_id' => 1, 'book_detail' => 'WPA', 'status' => 1,
            'bunber_limit' => 999, 'cdate' => '2026-08-22 00:00:00',
        ]);
        $this->adminId = (new ShadowUserStore($this->db))->create(
            'route-admin@example.invalid', password_hash('pass', PASSWORD_DEFAULT), 1, null,
        );
    }

    public function testAll178Ci3ExplicitRoutesHaveDeterministicCi4Disposition(): void
    {
        $path = ROOTPATH . 'tests/wp00c/ci4-route-disposition.json';
        self::assertFileExists($path);
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6', $payload['ci3_commit']);
        self::assertCount(178, $payload['routes']);
        $identities = [];
        foreach ($payload['routes'] as $row) {
            self::assertContains($row['status'], ['mapped', 'retired']);
            self::assertNotSame('', $row['decision']);
            self::assertTrue($row['status'] === 'retired' || $row['replacement'] !== '');
            $identities[] = $row['line'] . ':' . $row['route'];
        }
        self::assertCount(178, array_unique($identities));
        self::assertFalse(config('Routing')->autoRoute);
    }

    public function testUnknownAndUnapprovedImplicitEntriesReturnReal404ForAnonymousAndAuthenticatedUsers(): void
    {
        foreach (['/wp00c-missing-route', '/menu/deleteUser', '/menu/changePassword', '/order/do_upload_multi', '/Order/ReportTrackingListingTest'] as $path) {
            $this->assert404($path, false);
            $this->assert404($path, true);
        }
    }

    public function testKnownBrokenBookAliasIsCorrectedAndRackstatusIsRetired(): void
    {
        $book = $this->withSession($this->session())->get('/bookListing/2');
        $book->assertStatus(200);
        $book->assertSee('WPA');
        $this->assert404('/rackstatus', false);
        $this->assert404('/rackstatus/1', true);
        $this->assert404('/rackstatus_th', false);
    }

    private function assert404(string $path, bool $authenticated): void
    {
        try {
            $request = $authenticated ? $this->withSession($this->session()) : $this;
            $request->get($path);
            self::fail('Expected 404 for ' . $path);
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
            self::assertStringNotContainsString('invalid callback', strtolower($exception->getMessage()));
        }
    }

    /** @return array<string, int|bool|null> */
    private function session(): array
    {
        return [
            'userId' => $this->adminId, 'role' => 1, 'GroupID' => 1, 'BranchID' => null,
            'sessionVersion' => 1, 'isLoggedIn' => true,
        ];
    }
}
