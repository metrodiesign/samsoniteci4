<?php

namespace Tests\Ci4;

use App\Authentication\ResetDelivery;
use App\Authentication\ResetTokenFactory;
use App\Authentication\ResetTokenStore;
use App\Authentication\ShadowUserStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DateTimeImmutable;

final class PasswordResetPageHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    public function testLoginPagePreservesStandaloneCi3DocumentAndAssetOrder(): void
    {
        $result = $this->get('/login');

        $result->assertStatus(200);
        $body = (string) $result->getBody();
        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('<body class="login-page">', $body);
        self::assertStringContainsString('class="banner-cms"', $body);
        self::assertStringContainsString('placeholder="UserID" name="username"', $body);
        self::assertStringContainsString('placeholder="Password" name="password"', $body);
        self::assertStringContainsString('value="Sign In"', $body);
        self::assertStringContainsString('NEED HELP ? CALL OUR CUSTOMER CENTRE AT', $body);
        self::assertStringNotContainsString('/assets/css/admin.css', $body);
        $this->assertOrdered($body, [
            '/assets/bootstrap/css/bootstrap.min.css',
            '/assets/font-awesome/css/font-awesome.css',
            '/assets/dist/css/AdminLTE.min.css',
            '/assets/dist/css/CustomAdmin.css',
            '/assets/css/main.css',
            '/assets/js/jQuery-2.1.4.min.js',
            '/assets/bootstrap/js/bootstrap.min.js',
            '<section id="footer">',
        ]);
    }

    public function testForgotPasswordCanonicalAndLegacyAliasRenderSameCi3FormContract(): void
    {
        $canonical = $this->get('/forgot-password');
        $legacy = $this->get('/forgotPassword');

        $canonical->assertStatus(200);
        $legacy->assertStatus(200);
        foreach ([$canonical->getBody(), $legacy->getBody()] as $body) {
            self::assertStringStartsWith('<!DOCTYPE html>', (string) $body);
            self::assertStringContainsString('action="http://example.invalid/resetPasswordUser" method="post"', (string) $body);
            self::assertStringContainsString('placeholder="Email" name="login_email" required', (string) $body);
            self::assertStringContainsString('value="Submit"', (string) $body);
            self::assertStringContainsString('NEED HELP ? CALL OUR CUSTOMER CENTRE AT', (string) $body);
            self::assertStringNotContainsString('password-reset/request', (string) $body);
            self::assertStringContainsString('href="http://example.invalid/"', (string) $body);
        }
        self::assertSame($this->withoutCsrfValue((string) $canonical->getBody()), $this->withoutCsrfValue((string) $legacy->getBody()));
    }

    public function testActiveResetTokenRendersReadonlyEmailAndCi3GeneratedPasswordDefaults(): void
    {
        $factory = new ResetTokenFactory(static fn (int $length): string => str_repeat("\x12", $length));
        $users = new ShadowUserStore($this->db);
        $userId = $users->create(
            'page-reset@example.invalid',
            password_hash('Synthetic old passphrase', PASSWORD_DEFAULT),
            2,
            1,
        );
        $issued = $factory->issue(new DateTimeImmutable());
        (new ResetTokenStore($this->db, $factory))->issue($userId, $issued);

        $result = $this->get('/reset-password?token=' . $issued->token());

        $result->assertStatus(200);
        $body = (string) $result->getBody();
        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('name="email" value="page-reset@example.invalid" readonly required', $body);
        self::assertStringContainsString('name="activation_code" value="' . $issued->token() . '" required', $body);
        self::assertSame(1, preg_match('/name="password" required value="([^"]+)"/', $body, $password));
        self::assertSame(1, preg_match('/name="cpassword" required value="([^"]+)"/', $body, $confirmation));
        self::assertSame($password[1], $confirmation[1]);
        self::assertGreaterThanOrEqual(20, strlen($password[1]));
        self::assertSame(1, substr_count($body, $issued->token()));
        self::assertStringNotContainsString('password-reset/complete', $body);
        self::assertStringNotContainsString('maxcdn.bootstrapcdn.com', $body);
        $this->assertOrdered($body, [
            '/assets/bootstrap/css/bootstrap.min.css',
            '/assets/font-awesome/4.3.0/css/font-awesome.min.css',
            '/assets/dist/css/AdminLTE.min.css',
            '<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->',
            '<!-- WARNING: Respond.js doesn\'t work if you view the page via file:// -->',
            '<!--[if lt IE 9]>',
            '/assets/html5shiv/3.7.2/html5shiv.min.js',
            '/assets/respond/1.4.2/respond.min.js',
            '<![endif]-->',
            'name="email"',
            'name="activation_code"',
            'name="password"',
            'name="cpassword"',
            '/assets/js/jQuery-2.1.4.min.js',
            '/assets/bootstrap/js/bootstrap.min.js',
        ]);
    }

    public function testMalformedAndUnknownResetTokensAreNotReflected(): void
    {
        foreach (['zzxss"><script>alert(1)</script>', str_repeat('a', 64), str_repeat('A', 64)] as $candidate) {
            $result = $this->get('/reset-password?token=' . rawurlencode($candidate));

            $result->assertStatus(200);
            $body = (string) $result->getBody();
            self::assertStringNotContainsString($candidate, $body);
            self::assertStringContainsString('name="email" value="" readonly required', $body);
            self::assertStringContainsString('name="activation_code" value="" required', $body);
            self::assertStringContainsString('invalid or has expired', $body);
        }
    }

    public function testLegacyResetLinksFailClosedWithoutReflectingActivationOrEmail(): void
    {
        $activation = 'legacy-activation-marker';
        $email      = 'legacy-reset@example.invalid';

        foreach ([
            '/resetPasswordConfirmUser/' . $activation,
            '/resetPasswordConfirmUser/' . $activation . '/' . rawurlencode($email),
        ] as $path) {
            $response = $this->get($path);

            $response->assertRedirectTo('/forgotPassword');
            self::assertStringNotContainsString($activation, (string) $response->getBody());
            self::assertStringNotContainsString($email, (string) $response->getBody());
        }
    }

    public function testLocalLegacyAssetsMatchCi3PinChecksums(): void
    {
        $assets = [
            'bootstrap/css/bootstrap.min.css'                  => 'f75e846cc83bd11432f4b1e21a45f31bc85283d11d372f7b19accd1bf6a2635c',
            'bootstrap/js/bootstrap.min.js'                    => '53964478a7c634e8dad34ecc303dd8048d00dce4993906de1bacf67f663486ef',
            'bootstrap/fonts/glyphicons-halflings-regular.eot' => '13634da87d9e23f8c3ed9108ce1724d183a39ad072e73e1b3d8cbf646d2d0407',
            'bootstrap/fonts/glyphicons-halflings-regular.svg' => '42f60659d265c1a3c30f9fa42abcbb56bd4a53af4d83d316d6dd7a36903c43e5',
            'bootstrap/fonts/glyphicons-halflings-regular.ttf' => 'e395044093757d82afcb138957d06a1ea9361bdcf0b442d06a18a8051af57456',
            'bootstrap/fonts/glyphicons-halflings-regular.woff' => 'a26394f7ede100ca118eff2eda08596275a9839b959c226e15439557a5a80742',
            'bootstrap/fonts/glyphicons-halflings-regular.woff2' => 'fe185d11a49676890d47bb783312a0cda5a44c4039214094e7957b4c040ef11c',
            'font-awesome/css/font-awesome.css'                 => '295074933a25ae5d6646f86705412ae194ca64508e04984857c61ef495c66ec2',
            'font-awesome/fonts/fontawesome-webfont.eot'        => 'e511891d3e01b0b27aed51a219ced5119e2c3d0460465af8242e9bff4cb61b77',
            'font-awesome/fonts/fontawesome-webfont.svg'        => 'd5b5636ebb2e124810436200086b74a60dff9e8a8be7f4a1088bf5d3458bc3c8',
            'font-awesome/fonts/fontawesome-webfont.ttf'        => '4d6eb9e9d852a2a6f74e7c428456a2f07fc63a1613d10192d8ed3401d9da5ffa',
            'font-awesome/fonts/fontawesome-webfont.woff'       => '199411f659f41aaccb959bacb1b0de30e54f244352a48c6f9894e65ae0f8a9a1',
            'font-awesome/4.3.0/css/font-awesome.min.css'       => '541ac58217a8ade1a5e292a65a0661dc9db7a49ae13654943817a4fbc6761afd',
            'font-awesome/4.3.0/fonts/fontawesome-webfont.eot'  => 'cbb644d0ee730ea57dd5fbae35ef5ba4a41d57a254a6b1215de5c9ff8a321c2d',
            'font-awesome/4.3.0/fonts/fontawesome-webfont.svg'  => 'bfdef833219a6edffd9c3cbc28db72739d22bb4d20cc2e2f8d56a7a4d408a206',
            'font-awesome/4.3.0/fonts/fontawesome-webfont.ttf'  => '9e540a087924a6e64790149d735cac022640e4fa6bff6bd65f5e9f41529bf0b3',
            'font-awesome/4.3.0/fonts/fontawesome-webfont.woff' => 'e3870de89716b72cb61a4bba0e17c75783b361cdaba35ea96961c3070bd8ca18',
            'font-awesome/4.3.0/fonts/fontawesome-webfont.woff2' => 'aadc3580d2b64ff5a7e6f1425587db4e8b033efcbf8f5c332ca52a5ed580c87c',
            'html5shiv/3.7.2/html5shiv.min.js'                  => 'e0eac80838c161f29e7c46d54fbc044d12cd164baae13255e562c6be3aa91809',
            'respond/1.4.2/respond.min.js'                      => '83a8807ef669fa70d0d9375347f5552897f76c6ae8e2e6f97ef592595462d8d1',
            'js/jQuery-2.1.4.min.js'                            => 'f16ab224bb962910558715c82f58c10c3ed20f153ddfaa199029f141b5b0255c',
            'images/bg-login.jpg'                              => '476e5512aecb8bc658772d57716154e9af1ab5a5fad628237421766437f06819',
            'images/img-footer.png'                            => '688aeee5af3396a6a47aeb61448ac6606ca0f00eb46d698781b88844bf5d1a7b',
        ];

        foreach ($assets as $path => $checksum) {
            self::assertSame($checksum, hash_file('sha256', PUBLICPATH . 'assets/' . $path), $path);
        }
    }

    public function testAuthCssGraphIsLocalResolvedAndVersionPinned(): void
    {
        $pages = [
            '/login' => [
                'assets/bootstrap/css/bootstrap.min.css',
                'assets/font-awesome/css/font-awesome.css',
                'assets/dist/css/AdminLTE.min.css',
                'assets/dist/css/CustomAdmin.css',
                'assets/css/main.css',
            ],
            '/forgot-password' => [
                'assets/bootstrap/css/bootstrap.min.css',
                'assets/font-awesome/css/font-awesome.css',
                'assets/dist/css/AdminLTE.min.css',
                'assets/dist/css/CustomAdmin.css',
                'assets/css/main.css',
            ],
            '/reset-password' => [
                'assets/bootstrap/css/bootstrap.min.css',
                'assets/font-awesome/4.3.0/css/font-awesome.min.css',
                'assets/dist/css/AdminLTE.min.css',
            ],
        ];
        $seen = [];

        foreach ($pages as $route => $entrypoints) {
            $body = (string) $this->get($route)->getBody();
            foreach ($entrypoints as $entrypoint) {
                self::assertStringContainsString('/' . $entrypoint, $body, $route . ': ' . $entrypoint);
            }
            $seen = array_merge($seen, $this->localCssTree($entrypoints));
        }

        self::assertContains('assets/dist/css/AdminLTE.min.css', $seen);
        self::assertContains('assets/dist/css/CustomAdmin.css', $seen);
        self::assertContains('assets/font-awesome/4.7.0/css/font-awesome.min.css', $seen);
        self::assertContains('assets/fonts/source-sans-pro/stylesheet.css', $seen);

        self::assertSame('@import url("../../fonts/source-sans-pro/stylesheet.css");', strtok((string) file_get_contents(PUBLICPATH . 'assets/dist/css/AdminLTE.min.css'), "\n"));
        self::assertSame('@import url("../../font-awesome/4.7.0/css/font-awesome.min.css");', strtok((string) file_get_contents(PUBLICPATH . 'assets/dist/css/CustomAdmin.css'), "\n"));
        self::assertSame('799aeb25cc0373fdee0e1b1db7ad6c2f6a0e058dfadaa3379689f583213190bd', hash_file('sha256', PUBLICPATH . 'assets/font-awesome/4.7.0/css/font-awesome.min.css'));
        self::assertSame('2adefcbc041e7d18fcf2d417879dc5a09997aa64d675b7a3c4b6ce33da13f3fe', hash_file('sha256', PUBLICPATH . 'assets/font-awesome/4.7.0/fonts/fontawesome-webfont.woff2'));
        self::assertSame('fce9f9e2fb268507a89fceea0b3eccc044f39fc3492968a04fd9e04df5ae95fa', hash_file('sha256', PUBLICPATH . 'assets/fonts/source-sans-pro/OFL.txt'));
        foreach ([
            'SourceSansPro-Light.ttf' => '719319e7fe1ed06a6bc5e66a1cfea8c52250eefee502d175780cf4571ddc5bf0',
            'SourceSansPro-Regular.ttf' => '3d2e962599d4bd83b797ab813f2017f2c7f7e7e0e2e8e3a497f4e713a0b3c9c9',
            'SourceSansPro-SemiBold.ttf' => '37bb472f47d33a04f5616c6e9120723ed944c31306838ecd692feb7c69084da2',
            'SourceSansPro-Bold.ttf' => 'e1ac971e7b62b2ad0b0bb9f55bc15f6215df8af5bf69e894905341cfdfa51aea',
            'SourceSansPro-LightItalic.ttf' => '605425dc687ecc7bdce9329c0fc976ea38eb38a910c24144ced60d73f35e855e',
            'SourceSansPro-Italic.ttf' => '740798947aa0151c6bec4508ba73eb7cb22f9ef2e4354314fc638f66eaa2f072',
            'SourceSansPro-SemiBoldItalic.ttf' => '8e1136c1c135261a389118f9758d477d3582c9dda10fec6b7511e27e866adfbc',
        ] as $file => $sha256) {
            self::assertSame($sha256, hash_file('sha256', PUBLICPATH . 'assets/fonts/source-sans-pro/' . $file), $file);
        }
    }

    public function testResetDeliveryComposesCanonicalBrowserResetLinkWithTokenOnly(): void
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

        self::assertSame(site_url('reset-password') . '?token=' . $token, $url);
        self::assertStringNotContainsString('user@example.invalid', $url);
    }

    /** @param list<string> $needles */
    private function assertOrdered(string $body, array $needles): void
    {
        $position = -1;

        foreach ($needles as $needle) {
            $next = strpos($body, $needle);
            self::assertNotFalse($next, $needle);
            self::assertGreaterThan($position, $next, $needle);
            $position = $next;
        }
    }

    /** @param list<string> $entrypoints @return list<string> */
    private function localCssTree(array $entrypoints): array
    {
        $pending = $entrypoints;
        $seen = [];

        while ($pending !== []) {
            $asset = array_pop($pending);
            if (isset($seen[$asset])) {
                continue;
            }
            self::assertFileExists(PUBLICPATH . $asset, $asset);
            $seen[$asset] = true;
            $css = (string) file_get_contents(PUBLICPATH . $asset);
            preg_match_all('/url\\(\\s*(?:(["\\\'])(.*?)\\1|([^\\s)]+))\\s*\\)/i', $css, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $reference = $match[2] !== '' ? $match[2] : $match[3];
                self::assertDoesNotMatchRegularExpression('#^(?:https?:)?//#i', $reference, $asset . ': ' . $reference);
                if (str_starts_with($reference, 'data:') || str_starts_with($reference, '#')) {
                    continue;
                }
                $path = preg_split('/[?#]/', $reference, 2)[0];
                $candidate = str_starts_with($path, '/') ? ltrim($path, '/') : dirname($asset) . '/' . $path;
                $segments = [];
                foreach (explode('/', $candidate) as $segment) {
                    if ($segment === '' || $segment === '.') {
                        continue;
                    }
                    if ($segment === '..') {
                        array_pop($segments);
                        continue;
                    }
                    $segments[] = $segment;
                }
                $resolved = implode('/', $segments);
                self::assertFileExists(PUBLICPATH . $resolved, $asset . ': ' . $reference);
                if (str_ends_with(strtolower($resolved), '.css')) {
                    $pending[] = $resolved;
                }
            }
        }

        return array_keys($seen);
    }

    private function withoutCsrfValue(string $body): string
    {
        $body = (string) preg_replace('/<!-- DEBUG-VIEW (?:START|ENDED) \d+ [^-]+-->/', '', $body);

        return (string) preg_replace(
            '/(<input type="hidden" name="csrf_test_name" value=")[^"]+(" \/>)/',
            '$1[csrf]$2',
            $body,
        );
    }
}
