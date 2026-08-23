<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Authorization\AuthorizationPolicy;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthorizationFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminUserId;

    private int $operatorUserId;

    private int $viewerUserId;

    private int $branchTwoUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $users = new ShadowUserStore($this->db);
        $hash  = password_hash('Synthetic authz passphrase', PASSWORD_DEFAULT);

        $this->adminUserId    = $users->create('admin@example.invalid', $hash, 1, null);
        $this->operatorUserId = $users->create('operator@example.invalid', $hash, 2, 1);
        $this->viewerUserId   = $users->create('viewer@example.invalid', $hash, 3, 1);
        $this->branchTwoUserId = $users->create('branch-two@example.invalid', $hash, 2, 2);

        $this->withRoutes([
            [
                'GET',
                'test/protected/read',
                static fn (): ResponseInterface => service('response')->setJSON(['status' => 'allowed']),
                ['filter' => 'authorized:read'],
            ],
            [
                'POST',
                'test/protected/write',
                static fn (): ResponseInterface => service('response')->setJSON(['status' => 'allowed']),
                ['filter' => 'authorized:write'],
            ],
            [
                'GET',
                'test/protected/invalid-action',
                static fn (): ResponseInterface => service('response')->setJSON(['status' => 'allowed']),
                ['filter' => 'authorized:unknown'],
            ],
            [
                'GET',
                'test/protected/orders/(:num)',
                static function (string $orderId): ResponseInterface {
                    $branches = [91001 => 1, 91007 => 2];

                    if (! isset($branches[(int) $orderId])) {
                        throw PageNotFoundException::forPageNotFound();
                    }

                    $session = service('session');
                    (new AuthorizationPolicy())->assertBranchAccess(
                        $session->get('role'),
                        $session->get('BranchID'),
                        $branches[(int) $orderId],
                    );

                    return service('response')->setJSON(['order_id' => (int) $orderId]);
                },
                ['filter' => 'authorized:read'],
            ],
            [
                'POST',
                'test/protected/users/(:num)/deactivate',
                static function (string $userId): ResponseInterface {
                    $db  = db_connect();
                    $row = $db->table('ci4_users')
                        ->select('branch_id')
                        ->where('id', (int) $userId)
                        ->get()
                        ->getRowArray();

                    if ($row === null) {
                        throw PageNotFoundException::forPageNotFound();
                    }

                    $session = service('session');
                    (new AuthorizationPolicy())->assertBranchAccess(
                        $session->get('role'),
                        $session->get('BranchID'),
                        $row['branch_id'],
                    );
                    $db->table('ci4_users')->where('id', (int) $userId)->update(['is_active' => 0]);

                    return service('response')->setJSON(['status' => 'deactivated']);
                },
                ['filter' => 'authorized:write'],
            ],
        ]);
    }

    public function testAnonymousRequestIsDeniedBeforeController(): void
    {
        $result = $this->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testIncompleteSessionIsDenied(): void
    {
        $result = $this
            ->withSession(['isLoggedIn' => true])
            ->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testStaleSessionVersionIsDenied(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn'    => true,
                'userId'        => $this->operatorUserId,
                'role'          => 2,
                'BranchID'      => 1,
                'sessionVersion' => 2,
            ])
            ->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testSessionRoleCannotElevateAboveShadowUser(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn'    => true,
                'userId'        => $this->operatorUserId,
                'role'          => 1,
                'BranchID'      => null,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testBranchScopedSessionWithoutBranchIsDenied(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->operatorUserId,
                'role'       => 2,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testViewerCannotUseWriteRoute(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->viewerUserId,
                'role'       => 3,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->post('/test/protected/write');

        $result->assertStatus(403);
        $result->assertJSONExact(['error' => 'forbidden']);
    }

    public function testViewerCanUseReadRoute(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->viewerUserId,
                'role'       => 3,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/read');

        $result->assertStatus(200);
        $result->assertJSONExact(['status' => 'allowed']);
    }

    public function testUnknownRoleIsDeniedAsUnauthenticated(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => 9099,
                'role'       => 99,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/read');

        $result->assertStatus(401);
        $result->assertJSONExact(['error' => 'unauthenticated']);
    }

    public function testUnknownActionFailsClosed(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->adminUserId,
                'role'       => 1,
                'BranchID'   => null,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/invalid-action');

        $result->assertStatus(403);
        $result->assertJSONExact(['error' => 'forbidden']);
    }

    public function testOperatorCanUseWriteRoute(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->operatorUserId,
                'role'       => 2,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->post('/test/protected/write');

        $result->assertStatus(200);
        $result->assertJSONExact(['status' => 'allowed']);
    }

    public function testCrossBranchObjectIsHiddenFromOperator(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);

        $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->operatorUserId,
                'role'       => 2,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/orders/91007');
    }

    public function testOperatorCanReadOwnBranchObject(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->operatorUserId,
                'role'       => 2,
                'BranchID'   => 1,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/orders/91001');

        $result->assertStatus(200);
        $result->assertJSONExact(['order_id' => 91001]);
    }

    public function testAdminCanReadCrossBranchObjectWithoutBranchSession(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn' => true,
                'userId'     => $this->adminUserId,
                'role'       => 1,
                'BranchID'   => null,
                'sessionVersion' => 1,
            ])
            ->get('/test/protected/orders/91007');

        $result->assertStatus(200);
        $result->assertJSONExact(['order_id' => 91007]);
    }

    public function testCrossBranchWriteReturnsNotFoundAndLeavesDatabaseUnchanged(): void
    {
        try {
            $this
                ->withSession([
                    'isLoggedIn'     => true,
                    'userId'         => $this->operatorUserId,
                    'role'           => 2,
                    'BranchID'       => 1,
                    'sessionVersion' => 1,
                ])
                ->post('/test/protected/users/' . $this->branchTwoUserId . '/deactivate');
            self::fail('Expected cross-branch object to be hidden.');
        } catch (PageNotFoundException $exception) {
            self::assertSame(404, $exception->getCode());
        }

        self::assertSame(1, (int) $this->db->table('ci4_users')
            ->select('is_active')
            ->where('id', $this->branchTwoUserId)
            ->get()
            ->getRow('is_active'));
    }

    public function testReadOnlyRoleCannotMutateOwnBranchObjectAndLeavesDatabaseUnchanged(): void
    {
        $result = $this
            ->withSession([
                'isLoggedIn'     => true,
                'userId'         => $this->viewerUserId,
                'role'           => 3,
                'BranchID'       => 1,
                'sessionVersion' => 1,
            ])
            ->post('/test/protected/users/' . $this->operatorUserId . '/deactivate');

        $result->assertStatus(403);
        self::assertSame(1, (int) $this->db->table('ci4_users')
            ->select('is_active')
            ->where('id', $this->operatorUserId)
            ->get()
            ->getRow('is_active'));
    }
}
