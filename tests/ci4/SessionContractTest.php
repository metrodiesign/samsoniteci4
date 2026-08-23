<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockSession;
use Config\Cookie as CookieConfig;
use Config\Session as SessionConfig;

/**
 * WP-03A session contract regression tests.
 *
 * Locks three behaviours that already exist in app code:
 * - session ID is regenerated on successful login;
 * - cookie/session hardening flags match the security contract;
 * - a session whose version no longer matches the active server version is rejected.
 */
final class SessionContractTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private const PASSWORD = 'Synthetic contract passphrase';

    protected function setUp(): void
    {
        parent::setUp();

        // Legacy tables consumed by the login flow / dashboard render.
        // ci4_users itself is created by App migrations (DatabaseTestTrait).
        foreach ([
            'tbl_last_login' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, userId INTEGER NOT NULL, sessionData VARCHAR(2048) NOT NULL, machineIp VARCHAR(1024) NOT NULL, userAgent VARCHAR(128) NOT NULL, agentString VARCHAR(1024) NOT NULL, platform VARCHAR(128) NOT NULL, createdDtm DATETIME NOT NULL',
            'request_order'  => 'request_id INTEGER PRIMARY KEY, branchID INTEGER, action_status INTEGER',
        ] as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("CREATE TABLE IF NOT EXISTS {$name} ({$definition})");
            $this->db->table($table)->truncate();
        }

        $this->db->table('ci4_users')->truncate();
        (new ShadowUserStore($this->db))->synchronizeLegacyUser(
            9001,
            'contract-admin@example.invalid',
            password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            1,
            null,
            'contract-admin',
            'CONTRACT ADMIN',
            1,
            'Admin',
            true,
        );
    }

    public function testSuccessfulLoginRegeneratesSessionId(): void
    {
        // MockSession records regeneration via its public didRegenerate flag.
        $before = service('session');
        self::assertInstanceOf(MockSession::class, $before);
        self::assertFalse($before->didRegenerate);

        $result = $this->postWithCsrf('/loginMe', [
            'username' => 'contract-admin',
            'password' => self::PASSWORD,
        ]);

        // Redirect to /dashboard proves the successful-login branch actually ran.
        $result->assertRedirectTo('/dashboard');

        $after = service('session');
        self::assertInstanceOf(MockSession::class, $after);
        self::assertTrue(
            $after->didRegenerate,
            'Login controller must call session regenerate(true) on success.',
        );
        self::assertSame(9001, $after->get('userId'));
    }

    public function testCookieAndSessionHardeningFlagsMatchContract(): void
    {
        $cookie = config(CookieConfig::class);
        self::assertInstanceOf(CookieConfig::class, $cookie);
        self::assertTrue($cookie->httponly, 'Cookie httponly must be true.');
        self::assertSame('Lax', $cookie->samesite, 'Cookie samesite must be Lax.');
        self::assertSame(
            ENVIRONMENT === 'production',
            $cookie->secure,
            'Cookie secure must be bound to the production environment.',
        );

        $session = config(SessionConfig::class);
        self::assertInstanceOf(SessionConfig::class, $session);
        self::assertSame(7200, $session->expiration, 'Session expiration must be 7200s.');
        self::assertSame(300, $session->timeToUpdate, 'Session timeToUpdate must be 300s.');
        self::assertTrue($session->regenerateDestroy, 'Session regenerateDestroy must be true.');
    }

    public function testStaleSessionVersionIsRejectedWhileCurrentVersionIsAccepted(): void
    {
        // Server-side session version moves ahead of the client's copy.
        $this->db->table('ci4_users')->where('id', 9001)->update(['session_version' => 2]);

        // Positive control: matching version passes the auth filter.
        $current = $this->withSession($this->webSession(2))->get('/dashboard');
        $current->assertStatus(200);

        // Negative control: the stale (behind) version is rejected back to login.
        $stale = $this->withSession($this->webSession(1))->get('/dashboard');
        $stale->assertRedirectTo('/login');
    }

    /** @return array<string, int|string|bool|null> */
    private function webSession(int $sessionVersion): array
    {
        return [
            'userId'         => 9001,
            'role'           => 1,
            'GroupID'        => 1,
            'BranchID'       => null,
            'name'           => 'CONTRACT ADMIN',
            'isLoggedIn'     => true,
            'sessionVersion' => $sessionVersion,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return \CodeIgniter\Test\TestResponse
     */
    private function postWithCsrf(string $path, array $payload)
    {
        $payload['csrf_test_name'] = service('security')->getHash();

        return $this->post($path, $payload);
    }
}
