<?php

namespace Tests\Ci4;

use App\Authentication\ResetDeliveryIntentStore;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Authentication\ShadowUserStore;
use CodeIgniter\Encryption\EncrypterInterface;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Config\Services;
use DateTimeImmutable;

final class PasswordResetHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private EncrypterInterface $encrypter;

    protected function setUp(): void
    {
        parent::setUp();

        $config         = new Encryption();
        $config->driver = 'Sodium';
        $config->key    = str_repeat("\x10", 32);
        $this->encrypter = Services::encrypter($config, false);
        Services::injectMock('encrypter', $this->encrypter);
    }

    public function testKnownAndUnknownResetRequestsReturnSameGenericResponse(): void
    {
        (new ShadowUserStore($this->db))->create(
            'known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $known = $this->postJson('/password-reset/request', ['email' => 'known@example.invalid']);
        $unknown = $this->postJson('/password-reset/request', ['email' => 'unknown@example.invalid']);

        $known->assertStatus(202);
        $unknown->assertStatus(202);
        $known->assertJSONExact([
            'status'  => 'accepted',
            'message' => 'If the account exists, reset instructions will be sent.',
        ]);
        self::assertSame($known->getJSON(), $unknown->getJSON());

        $delivery = (new ResetDeliveryIntentStore($this->db, $this->encrypter))->reserveNext(
            new DateTimeImmutable('+1 minute'),
        );
        self::assertNotNull($delivery);
        self::assertSame('known@example.invalid', $delivery->recipient());
        self::assertNull((new ResetDeliveryIntentStore($this->db, $this->encrypter))->reserveNext(
            new DateTimeImmutable('+1 minute'),
        ));
    }

    public function testLegacyResetRequestFormUsesSameGenericWorkflowForKnownAndUnknownEmail(): void
    {
        (new ShadowUserStore($this->db))->create(
            'form-known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $known = $this->postForm('/resetPasswordUser', ['login_email' => ' form-known@example.invalid ']);
        $unknown = $this->postForm('/resetPasswordUser', ['login_email' => 'form-unknown@example.invalid']);

        $known->assertRedirectTo('/forgotPassword');
        $unknown->assertRedirectTo('/forgotPassword');
        self::assertSame($known->response()->getStatusCode(), $unknown->response()->getStatusCode());
        self::assertSame(
            $known->response()->getHeaderLine('Location'),
            $unknown->response()->getHeaderLine('Location'),
        );

        $delivery = (new ResetDeliveryIntentStore($this->db, $this->encrypter))->reserveNext(
            new DateTimeImmutable('+1 minute'),
        );
        self::assertNotNull($delivery);
        self::assertSame('form-known@example.invalid', $delivery->recipient());
        self::assertNull((new ResetDeliveryIntentStore($this->db, $this->encrypter))->reserveNext(
            new DateTimeImmutable('+1 minute'),
        ));
    }

    public function testLegacyResetRequestFormRequiresCsrfBeforeWorkflow(): void
    {
        $before = $this->db->table('ci4_password_reset_audit')->countAllResults();

        try {
            $this->post('/resetPasswordUser', ['login_email' => 'csrf@example.invalid']);
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException) {
            // The route filter rejects before the controller operation.
        }

        self::assertSame($before, $this->db->table('ci4_password_reset_audit')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_password_reset_tokens')->countAllResults());
    }

    public function testLegacyResetRequestFormUsesSameIdentityRateLimit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postForm('/resetPasswordUser', ['login_email' => 'form-throttle@example.invalid'])
                ->assertRedirectTo('/forgotPassword');
        }

        $limited = $this->postForm('/resetPasswordUser', ['login_email' => 'form-throttle@example.invalid']);

        $limited->assertStatus(429);
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/D', $limited->response()->getHeaderLine('Retry-After'));
        self::assertStringContainsString('Too many requests', (string) $limited->getBody());
        self::assertSame(0, $this->db->table('ci4_password_reset_tokens')->countAllResults());
    }

    public function testLegacyResetCompletionConsumesTokenOnceAndRevokesSessions(): void
    {
        $users = new ShadowUserStore($this->db);
        $userId = $users->create(
            'form-complete@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x14", $length));
        $issued = $factory->issue(new DateTimeImmutable());
        (new ResetTokenStore($this->db, $factory))->issue($userId, $issued);
        $payload = [
            'email'           => 'form-complete@example.invalid',
            'activation_code' => $issued->token(),
            'password'        => 'Synthetic modern passphrase',
            'cpassword'       => 'Synthetic modern passphrase',
        ];

        $this->postForm('/createPasswordUser', $payload)->assertRedirectTo('/login');
        $replay = $this->postForm('/createPasswordUser', $payload);

        $replay->assertStatus(400);
        self::assertStringContainsString('invalid or has expired', (string) $replay->getBody());
        self::assertStringNotContainsString($issued->token(), (string) $replay->getBody());
        self::assertTrue($users->verifyPassword($userId, 'Synthetic modern passphrase'));
        self::assertSame(2, $users->currentSessionVersion($userId));
    }

    public function testLegacyResetCompletionRequiresCsrfBeforePasswordChange(): void
    {
        $users = new ShadowUserStore($this->db);
        $userId = $users->create(
            'complete-csrf@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x17", $length));
        $issued = $factory->issue(new DateTimeImmutable());
        $tokens = new ResetTokenStore($this->db, $factory);
        $tokens->issue($userId, $issued);

        try {
            $this->post('/createPasswordUser', [
                'email'           => 'complete-csrf@example.invalid',
                'activation_code' => $issued->token(),
                'password'        => 'Synthetic modern passphrase',
                'cpassword'       => 'Synthetic modern passphrase',
            ]);
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException) {
            // The route filter rejects before the controller operation.
        }

        self::assertTrue($users->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertTrue($tokens->isValid($userId, $issued->token(), new DateTimeImmutable()));
    }

    public function testLegacyResetCompletionRejectsMismatchedEmailWithoutReflectingPayload(): void
    {
        $users = new ShadowUserStore($this->db);
        $userId = $users->create(
            'form-owner@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x15", $length));
        $issued = $factory->issue(new DateTimeImmutable());
        $tokens = new ResetTokenStore($this->db, $factory);
        $tokens->issue($userId, $issued);

        $result = $this->postForm('/createPasswordUser', [
            'email'           => 'attacker@example.invalid',
            'activation_code' => $issued->token(),
            'password'        => 'Synthetic modern passphrase',
            'cpassword'       => 'Synthetic modern passphrase',
        ]);

        $result->assertStatus(400);
        $body = (string) $result->getBody();
        self::assertStringNotContainsString('attacker@example.invalid', $body);
        self::assertStringNotContainsString('form-owner@example.invalid', $body);
        self::assertStringNotContainsString($issued->token(), $body);
        self::assertTrue($users->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertTrue($tokens->isValid($userId, $issued->token(), new DateTimeImmutable()));
    }

    public function testLegacyResetCompletionRejectsWeakPasswordWithoutConsumingToken(): void
    {
        $users = new ShadowUserStore($this->db);
        $userId = $users->create(
            'form-weak@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x16", $length));
        $issued = $factory->issue(new DateTimeImmutable());
        $tokens = new ResetTokenStore($this->db, $factory);
        $tokens->issue($userId, $issued);

        $result = $this->postForm('/createPasswordUser', [
            'email'           => 'form-weak@example.invalid',
            'activation_code' => $issued->token(),
            'password'        => 'short',
            'cpassword'       => 'short',
        ]);

        $result->assertStatus(422);
        $body = (string) $result->getBody();
        self::assertStringContainsString('value="form-weak@example.invalid" readonly', $body);
        self::assertSame(1, preg_match('/name="password" required value="([^"]+)"/', $body, $password));
        self::assertSame(1, preg_match('/name="cpassword" required value="([^"]+)"/', $body, $confirmation));
        self::assertSame($password[1], $confirmation[1]);
        self::assertStringNotContainsString('value="short"', $body);
        self::assertTrue($tokens->isValid($userId, $issued->token(), new DateTimeImmutable()));
        self::assertTrue($users->verifyPassword($userId, 'Synthetic old passphrase'));
    }

    public function testResetDocumentKeepsServiceUnavailableMessageWithoutReflectingResetData(): void
    {
        $controller = new \App\Controllers\PasswordReset();
        $method = new \ReflectionMethod($controller, 'resetDocument');
        $token = str_repeat('a', 64);
        $body = (string) $method->invoke(
            $controller,
            $token,
            'The reset service is temporarily unavailable. Please try again later.',
            'lookup-failure@example.invalid',
        );

        self::assertStringContainsString('reset service is temporarily unavailable', $body);
        self::assertStringNotContainsString('invalid or has expired', $body);
        self::assertStringNotContainsString($token, $body);
        self::assertStringNotContainsString('lookup-failure@example.invalid', $body);
    }

    public function testKnownAndUnknownResetRequestsBothObserveMinimumResponseTime(): void
    {
        (new ShadowUserStore($this->db))->create(
            'timed-known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $knownStarted = hrtime(true);
        $this->postJson('/password-reset/request', ['email' => 'timed-known@example.invalid'])
            ->assertStatus(202);
        $knownElapsed = hrtime(true) - $knownStarted;

        $unknownStarted = hrtime(true);
        $this->postJson('/password-reset/request', ['email' => 'timed-unknown@example.invalid'])
            ->assertStatus(202);
        $unknownElapsed = hrtime(true) - $unknownStarted;

        self::assertGreaterThanOrEqual(80_000_000, $knownElapsed);
        self::assertGreaterThanOrEqual(80_000_000, $unknownElapsed);
    }

    public function testCsrfBootstrapReturnsHeaderNameAndToken(): void
    {
        $result = $this->get('/password-reset/csrf');

        $result->assertStatus(200);
        $payload = json_decode($result->getJSON(), true, 8, JSON_THROW_ON_ERROR);
        self::assertSame('X-CSRF-TOKEN', $payload['header']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $payload['token']);
        self::assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
    }

    public function testResetRequestRateLimitAlsoAppliesToUnknownAccount(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/password-reset/request', ['email' => 'throttle@example.invalid'])
                ->assertStatus(202);
        }

        $limited = $this->postJson('/password-reset/request', ['email' => 'throttle@example.invalid']);

        $limited->assertStatus(429);
        $limited->assertJSONExact(['error' => 'too_many_requests']);
    }

    public function testOversizedResetRequestIsRejectedBeforeJsonDecoding(): void
    {
        $result = $this->postJson('/password-reset/request', [
            'email'   => 'unknown@example.invalid',
            'padding' => str_repeat('x', 4096),
        ]);

        $result->assertStatus(413);
        $result->assertJSONExact(['error' => 'payload_too_large']);
    }

    public function testOversizedResetCompletionIsRejectedBeforePasswordValidation(): void
    {
        $result = $this->postJson('/password-reset/complete', [
            'email'                 => 'unknown@example.invalid',
            'token'                 => str_repeat('a', 64),
            'password'              => str_repeat('x', 4096),
            'password_confirmation' => str_repeat('x', 4096),
        ]);

        $result->assertStatus(413);
        $result->assertJSONExact(['error' => 'payload_too_large']);
    }

    public function testMalformedResetRequestJsonReturnsGenericValidationError(): void
    {
        $result = $this->postMalformedJson('/password-reset/request');

        $result->assertStatus(422);
        $result->assertJSONExact(['error' => 'invalid_request']);
        self::assertStringNotContainsString('trace', $result->getJSON());
    }

    public function testMalformedResetCompletionJsonReturnsGenericValidationError(): void
    {
        $result = $this->postMalformedJson('/password-reset/complete');

        $result->assertStatus(400);
        $result->assertJSONExact(['error' => 'invalid_or_expired_reset']);
        self::assertStringNotContainsString('trace', $result->getJSON());
    }

    public function testValidResetCompletesOnceAndReplayIsDenied(): void
    {
        $users  = new ShadowUserStore($this->db);
        $userId = $users->create(
            'complete@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            3,
            7,
        );
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x11", $length));
        $issued  = $factory->issue(new DateTimeImmutable());
        (new ResetTokenStore($this->db, $factory))->issue($userId, $issued);
        $payload = [
            'email'                 => 'complete@example.invalid',
            'token'                 => $issued->token(),
            'password'              => 'Synthetic modern passphrase',
            'password_confirmation' => 'Synthetic modern passphrase',
        ];

        $success = $this->postJson('/password-reset/complete', $payload);
        $success->assertStatus(200);
        $success->assertJSONExact(['status' => 'password_reset']);

        $replay = $this->postJson('/password-reset/complete', $payload);
        $replay->assertStatus(400);
        $replay->assertJSONExact(['error' => 'invalid_or_expired_reset']);
        self::assertFalse($users->verifyPassword($userId, 'Synthetic old passphrase'));
        self::assertTrue($users->verifyPassword($userId, 'Synthetic modern passphrase'));
        self::assertSame(2, $users->currentSessionVersion($userId));
    }

    public function testResetRequestWithoutCsrfTokenIsRejectedWithoutDebugDetails(): void
    {
        $result = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withBodyFormat('json')
            ->post('/password-reset/request', ['email' => 'unknown@example.invalid']);

        $result->assertStatus(403);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $result->assertJSONExact(['error' => 'csrf_rejected']);
        $result->assertDontSee('Access Denied');
        self::assertStringNotContainsString('trace', $result->getJSON());
        self::assertStringNotContainsString('unknown@example.invalid', $result->getJSON());
    }

    public function testResetRequestFailsClosedWhenEncryptionKeyIsMissing(): void
    {
        Services::resetSingle('encrypter');

        $result = $this->postJson('/password-reset/request', [
            'email' => 'missing-key@example.invalid',
        ]);

        $result->assertStatus(503);
        $result->assertJSONExact(['error' => 'reset_service_unavailable']);
    }

    public function testResetRequestAuditsKnownAndUnknownWithDistinctEventsButSameResponse(): void
    {
        (new ShadowUserStore($this->db))->create(
            'audit-known@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );

        $known = $this->postJson('/password-reset/request', ['email' => 'audit-known@example.invalid']);
        $unknown = $this->postJson('/password-reset/request', ['email' => 'audit-unknown@example.invalid']);

        $known->assertStatus(202);
        $unknown->assertStatus(202);
        self::assertSame($known->getJSON(), $unknown->getJSON());

        $byEvent = $this->auditRowsByEvent();
        self::assertArrayHasKey('request_accepted', $byEvent);
        self::assertArrayHasKey('request_unknown_identity', $byEvent);

        // No-PII: identity_hash must be the sha256 of the normalized email, never the plaintext.
        self::assertSame(
            hash('sha256', 'audit-known@example.invalid'),
            $byEvent['request_accepted']['identity_hash'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/D',
            (string) $byEvent['request_unknown_identity']['identity_hash'],
        );
        $this->assertNoPlaintextIdentity();
    }

    public function testResetCompletionWithWrongTokenAuditsInvalidToken(): void
    {
        $result = $this->postJson('/password-reset/complete', [
            'email'                 => 'audit-complete@example.invalid',
            'token'                 => str_repeat('a', 64),
            'password'              => 'Synthetic modern passphrase',
            'password_confirmation' => 'Synthetic modern passphrase',
        ]);

        $result->assertStatus(400);
        self::assertArrayHasKey('complete_invalid_token', $this->auditRowsByEvent());
        $this->assertNoPlaintextIdentity();
    }

    public function testResetRequestThrottleIsAudited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/password-reset/request', ['email' => 'audit-throttle@example.invalid'])
                ->assertStatus(202);
        }

        $this->postJson('/password-reset/request', ['email' => 'audit-throttle@example.invalid'])
            ->assertStatus(429);

        self::assertArrayHasKey('request_throttled', $this->auditRowsByEvent());
        $this->assertNoPlaintextIdentity();
    }

    /** @return array<string, array<string, mixed>> */
    private function auditRowsByEvent(): array
    {
        $byEvent = [];

        foreach ($this->db->table('ci4_password_reset_audit')->get()->getResultArray() as $row) {
            $byEvent[(string) $row['event']] = $row;
        }

        return $byEvent;
    }

    private function assertNoPlaintextIdentity(): void
    {
        $rows = $this->db->table('ci4_password_reset_audit')->get()->getResultArray();
        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            if ($row['identity_hash'] !== null) {
                self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', (string) $row['identity_hash']);
            }
        }
    }

    /** @param array<string, string> $payload */
    private function postJson(string $path, array $payload)
    {
        return $this
            ->withHeaders(['X-CSRF-TOKEN' => service('security')->getHash()])
            ->withBodyFormat('json')
            ->post($path, $payload);
    }

    private function postMalformedJson(string $path)
    {
        return $this
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-CSRF-TOKEN' => service('security')->getHash(),
            ])
            ->withBody('{"email":')
            ->post($path);
    }

    /** @param array<string, string> $payload */
    private function postForm(string $path, array $payload)
    {
        $payload[csrf_token()] = csrf_hash();

        return $this->post($path, $payload);
    }
}
