<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class MasterDataRoleMatrixTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    /** @var array<int, int> role => shadow user id */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        $users = new ShadowUserStore($this->db);
        $hash  = password_hash('Synthetic role matrix passphrase', PASSWORD_DEFAULT);

        $this->userIds = [
            1 => $users->create('rm-admin@example.invalid', $hash, 1, null),
            2 => $users->create('rm-operator@example.invalid', $hash, 2, 1),
            3 => $users->create('rm-viewer@example.invalid', $hash, 3, 1),
        ];
    }

    public function testEveryKnownRoleCanReadWriteDeleteRepresentativeEntities(): void
    {
        foreach (array_keys($this->userIds) as $role) {
            foreach ($this->entities() as $type => $def) {
                $context = "role {$role} / {$type}";

                $this->getAsRole($role, '/master/' . $type)->assertStatus(200);

                $this->postAsRole($role, '/master/' . $type, [$def['field'] => 'CREATED'])
                    ->assertRedirectTo('/master/' . $type);
                $id = (int) $this->db->insertID();
                self::assertSame(
                    1,
                    $this->db->table($def['table'])->where($def['pk'], $id)->countAllResults(),
                    'create ' . $context,
                );

                $listing = $this->getAsRole($role, '/master/' . $type);
                $listing->assertStatus(200);
                $listing->assertSee('CREATED');

                $this->postAsRole($role, '/master/' . $type . '/' . $id, [$def['field'] => 'UPDATED'])
                    ->assertRedirectTo('/master/' . $type);
                self::assertSame(
                    'UPDATED',
                    $this->db->table($def['table'])->where($def['pk'], $id)->get()->getRow($def['field']),
                    'update ' . $context,
                );

                $this->postAsRole($role, '/master/' . $type . '/' . $id . '/delete', [])
                    ->assertStatus(204);
                self::assertSame(
                    0,
                    $this->db->table($def['table'])->where($def['pk'], $id)->countAllResults(),
                    'delete ' . $context,
                );
            }
        }
    }

    public function testUnknownRoleIsDeniedEveryActionAndLeavesDatabaseUnchanged(): void
    {
        foreach ([4, 0, 'garbage'] as $role) {
            foreach ($this->entities() as $type => $def) {
                $context = 'role ' . var_export($role, true) . " / {$type}";

                $this->db->table($def['table'])->insert([
                    $def['field'] => 'SEEDED',
                    'cdate'       => '2026-08-22 09:00:00',
                ]);
                $id     = (int) $this->db->insertID();
                $before = $this->db->table($def['table'])->countAllResults();
                $session = $this->sessionFor($this->userIds[1], $role, null);

                $this->withSession($session)->get('/master/' . $type)->assertStatus(401);

                $this->withSession($session)
                    ->post('/master/' . $type, $this->withCsrf([$def['field'] => 'HACKED']))
                    ->assertStatus(401);
                self::assertSame(
                    $before,
                    $this->db->table($def['table'])->countAllResults(),
                    'no create ' . $context,
                );

                $this->withSession($session)
                    ->post('/master/' . $type . '/' . $id . '/delete', $this->withCsrf([]))
                    ->assertStatus(401);
                self::assertSame(
                    1,
                    $this->db->table($def['table'])->where($def['pk'], $id)->countAllResults(),
                    'no delete ' . $context,
                );

                $this->db->table($def['table'])->where($def['pk'], $id)->delete();
            }
        }
    }

    public function testGuestIsDeniedEveryActionAndLeavesDatabaseUnchanged(): void
    {
        foreach ($this->entities() as $type => $def) {
            $this->db->table($def['table'])->insert([
                $def['field'] => 'SEEDED',
                'cdate'       => '2026-08-22 09:00:00',
            ]);
            $id     = (int) $this->db->insertID();
            $before = $this->db->table($def['table'])->countAllResults();

            $this->get('/master/' . $type)->assertStatus(401);

            $this->post('/master/' . $type, $this->withCsrf([$def['field'] => 'GHOST']))
                ->assertStatus(401);
            self::assertSame($before, $this->db->table($def['table'])->countAllResults(), 'no create guest / ' . $type);

            $this->post('/master/' . $type . '/' . $id . '/delete', $this->withCsrf([]))
                ->assertStatus(401);
            self::assertSame(
                1,
                $this->db->table($def['table'])->where($def['pk'], $id)->countAllResults(),
                'no delete guest / ' . $type,
            );
        }
    }

    private function getAsRole(int $role, string $path)
    {
        return $this->withSession($this->sessionForRole($role))->get($path);
    }

    /** @param array<string, string> $payload */
    private function postAsRole(int $role, string $path, array $payload)
    {
        return $this->withSession($this->sessionForRole($role))->post($path, $this->withCsrf($payload));
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, string>
     */
    private function withCsrf(array $payload): array
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $payload;
    }

    /** @return array<string, int|bool|null> */
    private function sessionForRole(int $role): array
    {
        return $this->sessionFor($this->userIds[$role], $role, $role === 1 ? null : 1);
    }

    /** @return array<string, mixed> */
    private function sessionFor(int $userId, int|string $role, ?int $branchId): array
    {
        return [
            'userId'         => $userId,
            'role'           => $role,
            'BranchID'       => $branchId,
            'sessionVersion' => 1,
            'isLoggedIn'     => true,
        ];
    }

    /** @return array<string, array{table: string, pk: string, field: string}> */
    private function entities(): array
    {
        return [
            'brand'     => ['table' => 'brand', 'pk' => 'brand_id', 'field' => 'brand_details'],
            'condition' => ['table' => 'condition', 'pk' => 'condition_id', 'field' => 'condition_details'],
        ];
    }

    private function createTables(): void
    {
        foreach (['request_order', 'request_order_delete', 'tbl_users', 'status_log', 'uploadstaus'] as $table) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
        }
        $tables = [
            'brand'     => 'brand_id INTEGER PRIMARY KEY AUTOINCREMENT, brand_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
            'condition' => 'condition_id INTEGER PRIMARY KEY AUTOINCREMENT, condition_details VARCHAR(250) NOT NULL, cdate DATETIME NOT NULL',
        ];
        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $this->db->resetDataCache();
    }
}
