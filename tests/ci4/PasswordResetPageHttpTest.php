<?php

namespace Tests\Ci4;

use App\Authentication\ResetDelivery;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class PasswordResetPageHttpTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testForgotPasswordPageRendersEmailFormWiredToApi(): void
    {
        $result = $this->get('/forgot-password');

        $result->assertStatus(200);
        $result->assertSee('Forgot Password');
        $result->assertSeeElement('input#email');
        $result->assertSee('Submit', 'button');
        // JS must reach the CSRF bootstrap and the request endpoint.
        $result->assertSee('password-reset/csrf');
        $result->assertSee('password-reset/request');
        // Generic, enumeration-resistant confirmation text lives in the page.
        $result->assertSee('If the account exists, reset instructions will be sent.');
    }

    public function testResetPasswordPageRendersPasswordFieldsAndCarriesToken(): void
    {
        $token = str_repeat('a', 64);

        $result = $this->get('/reset-password?token=' . $token);

        $result->assertStatus(200);
        $result->assertSee('Reset Password');
        $result->assertSeeElement('input#password');
        $result->assertSeeElement('input#password_confirmation');
        $result->assertSee('Submit', 'button');
        $result->assertSee('password-reset/complete');
        // Token from the query string is echoed into the hidden field.
        self::assertStringContainsString($token, (string) $result->getBody());
    }

    public function testResetPasswordPageDropsNonHexTokenSoReflectedInputCannotLeak(): void
    {
        $malicious = 'zzxss"><script>alert(1)</script>';

        $result = $this->get('/reset-password?token=' . rawurlencode($malicious));

        $result->assertStatus(200);
        // Guard rejects any token not matching /^[0-9a-f]{64}$/ -> blank field,
        // so no fragment of the attacker payload survives into the markup.
        self::assertStringNotContainsString('zzxss', (string) $result->getBody());
    }

    public function testResetDeliveryComposesBrowserResetLinkWithToken(): void
    {
        $token = str_repeat('b', 64);
        $delivery = new ResetDelivery(
            1,
            str_repeat('c', 64),
            str_repeat('d', 32),
            'user@example.invalid',
            $token,
        );

        $url = $delivery->resetUrl();

        self::assertStringContainsString('reset-password', $url);
        self::assertStringContainsString('token=' . $token, $url);
        // Host comes from site_url() config, never hardcoded in the class.
        self::assertStringStartsWith(site_url('reset-password'), $url);
    }
}
