<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Encryption;
use Config\Services;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\AssertionFailedError;

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
        $englishForm->assertSee('REPAIR CENTER');
        $englishForm->assertSee('CUSTOMER');
        $englishForm->assertSee('MORE INFOMATION');
        $englishForm->assertSee('Google Map');
        $englishForm->assertSee('https://goo.gl/maps/uH7TMBuW1w22');
        $thaiForm = $this->get('/contact-th');
        $thaiForm->assertStatus(200);
        $thaiForm->assertSee('ศูนย์บริการซ่อม');
        $thaiForm->assertSee('ลูกค้าสัมพันธ์');
        $thaiForm->assertSee('ข้อมูลเพิ่มเติม');
        $thaiForm->assertSee('แผนที่');

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

    public function testContactPagesKeepCi3ChromeDomDependencyOrderAndLocalAssetGraph(): void
    {
        foreach (['/contact' => 'en', '/contact-th' => 'th', '/contact_th' => 'th'] as $route => $language) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $html = (string) $response->getBody();

            $this->assertCi3ContactTitle($html);
            self::assertStringContainsString('<section id="header">', $html);
            self::assertStringContainsString('<input class="menu-btn" type="checkbox" id="menu-btn">', $html);
            self::assertStringContainsString('<i class="fa fa-cogs edit-ico"></i>', $html);
            self::assertStringContainsString('<i class="fa fa-envelope-o edit-ico"></i>', $html);
            self::assertStringContainsString('<i class="fa fa-shopping-bag edit-ico"></i>', $html);
            self::assertStringContainsString('<section id="footer">', $html);
            self::assertStringContainsString('id="addContact"', $html);
            self::assertStringContainsString('name="fullname" id="fullname" class="main-input form-control required"', $html);
            self::assertStringContainsString('name="email" class="main-input form-control required email" id="email"', $html);
            self::assertStringContainsString('name="phone" id="phone" class="main-input form-control required"', $html);
            self::assertStringContainsString('name="detail" id="detail" class="main-input form-control required"', $html);
            self::assertStringContainsString('name="submission_id"', $html);
            self::assertStringContainsString('name="csrf_test_name"', $html);
            self::assertStringNotContainsString('assets/css/public.css', $html);
            self::assertStringNotContainsString('<svg', $html);
            self::assertStringContainsString(
                'action="' . ($language === 'th' ? base_url('contact_th/addContact') : base_url('contact/addContact')) . '"',
                $html,
            );
            self::assertStringContainsString(
                'value="' . ($language === 'th' ? 'แผนที่' : 'Google Map') . '"',
                html_entity_decode($html, ENT_QUOTES, 'UTF-8'),
            );
            if ($language === 'th') {
                self::assertStringContainsString("3388/25-37,\u{00A0}51-53 อาคารสิรินรัตน์ ชั้น 8", html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
                self::assertStringContainsString('<div class="text-right">', $html);
                self::assertStringNotContainsString('class="" style="text-align: right"', $html);
            } else {
                self::assertStringContainsString('<div class="" style="text-align: right">', $html);
                self::assertStringNotContainsString('<div class="text-right">', $html);
            }
            self::assertStringContainsString("onclick=\"window.location.href='https://goo.gl/maps/uH7TMBuW1w22'\"", $html);
            $this->assertInOrder($html, [
                'assets/js/jquery-3.2.1.min.js',
                'assets/bootstrap/js/bootstrap.min.js',
                'assets/bootstrap/css/bootstrap.css',
                'assets/css/main.css',
                'assets/fontawesome/css/font-awesome.css',
                'assets/fonts/stylesheet.css',
                'assets/dist/js/app.min.js',
                'assets/js/jquery.validate.js',
                'assets/js/validation.js',
                'assets/js/addContact.js',
            ]);
            $this->assertLocalAssetGraph($html);
        }

        foreach ($this->ci3AssetHashes() as $path => $hash) {
            self::assertSame($hash, hash_file('sha256', ROOTPATH . 'public/' . $path), $path);
        }
        $this->assertAdaptedAdminLteAsset();
    }

    public function testContactSourceComparatorKeepsOrderedFormAndValidationShellForBothLanguages(): void
    {
        foreach ([
            '/contact' => ['contact/addContact', 'NAME & SURNAME *', 'SEND NOW', 'Message received'],
            '/contact-th' => ['contact_th/addContact', 'ชื่อ-สกุล *', 'ส่ง', 'รับข้อความแล้ว'],
        ] as $route => [$action, $namePlaceholder, $submitLabel, $received]) {
            $normal = (string) $this->get($route)->getBody();
            self::assertStringContainsString($namePlaceholder, html_entity_decode($normal, ENT_QUOTES, 'UTF-8'));
            self::assertStringContainsString('value="' . $submitLabel . '"', html_entity_decode($normal, ENT_QUOTES, 'UTF-8'));
            $this->assertInOrder($normal, [
                'name="csrf_test_name"',
                'name="submission_id"',
                'name="fullname" id="fullname" class="main-input form-control required"',
                'placeholder="',
                'name="email" class="main-input form-control required email" id="email"',
                'name="phone" id="phone" class="main-input form-control required"',
                'name="detail" id="detail" class="main-input form-control required"',
                'class="main-btn-sm" value="',
            ]);
            $normalXpath = $this->domXPath($normal);
            $this->assertXPathCount(
                1,
                $normalXpath,
                '//section[@id="contact"]/div[@class="container"]/div[@class="row"]/div[@class="col-lg-5 con-box-info"]/form[@id="addContact" and @action="' . base_url($action) . '"]',
                $route . ' form hierarchy',
            );
            $this->assertXPathCount(1, $normalXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[@class="row"]/div[@class="col-md-12"]', $route . ' validation shell');

            $success = (string) $this->get($route . '?submitted=1')->getBody();
            self::assertStringContainsString($received, html_entity_decode($success, ENT_QUOTES, 'UTF-8'));
            $successXpath = $this->domXPath($success);
            $this->assertXPathCount(1, $successXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-success ")]', $route . ' direct success flash');
            $this->assertXPathCount(0, $successXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[@class="row"]/div[@class="col-md-12"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-success ")]', $route . ' success flash not nested');
        }
    }

    public function testDomAssetExtractorRejectsExternalUrlsForEveryNodeAndQuotingForm(): void
    {
        $nodes = [
            ['script', 'src'],
            ['img', 'src'],
            ['link', 'href'],
        ];
        $urls = ['http://cdn.example.invalid/asset.js', 'https://cdn.example.invalid/asset.js', '//cdn.example.invalid/asset.js'];
        $quoting = ['double', 'single', 'unquoted'];

        foreach ($nodes as [$node, $attribute]) {
            foreach ($quoting as $quote) {
                foreach ($urls as $url) {
                    $value = match ($quote) {
                        'double' => '"' . $url . '"',
                        'single' => "'" . $url . "'",
                        'unquoted' => $url,
                    };
                    $case = $node . ' ' . $attribute . ' ' . $quote . ' ' . $url;
                    $html = '<' . $node . ' ' . $attribute . '=' . $value . '>';

                    self::assertSame([$url], $this->domAssetUrls($html), $case);
                    $rejected = false;
                    try {
                        $this->localAssetPath($url);
                    } catch (AssertionFailedError) {
                        $rejected = true;
                    }
                    self::assertTrue($rejected, 'External DOM asset was accepted: ' . $case);
                }
            }
        }
    }

    public function testLegacyPostAliasesUseTheSameProtectedWorkflow(): void
    {
        $this->post('/addContact', $this->payload('a3', 'SYNTHETIC LEGACY EN'))
            ->assertRedirectTo('/contact?submitted=1');
        $this->post('/addContact_th', $this->payload('b4', 'SYNTHETIC LEGACY TH'))
            ->assertRedirectTo('/contact-th?submitted=1');
        $this->post('/contact/addContact', $this->payload('a4', 'SYNTHETIC SOURCE EN'))
            ->assertRedirectTo('/contact?submitted=1');
        $this->post('/contact_th/addContact', $this->payload('b5', 'SYNTHETIC SOURCE TH'))
            ->assertRedirectTo('/contact-th?submitted=1');

        self::assertSame(4, $this->db->table('contact')->countAllResults());
        self::assertSame(4, $this->db->table('ci4_delivery_intents')->where('kind', 'contact')->countAllResults());
    }

    public function testInvalidReplayAndUnavailableDeliveryDoNotCreatePartialOrDuplicateRows(): void
    {
        $invalid = $this->payload('c3', '<script>CONTACT-MARKER</script>');
        $invalid['email'] = 'not-an-email';
        $invalidResponse = $this->post('/contact', $invalid);
        $invalidResponse->assertStatus(422);
        $invalidHtml = (string) $invalidResponse->getBody();
        $this->assertCi3ContactTitle($invalidHtml);
        $invalidResponse->assertSee('Please enter a valid email address');
        $invalidResponse->assertSeeInField('fullname', '<script>CONTACT-MARKER</script>');
        $invalidResponse->assertSeeInField('email', 'not-an-email');
        $invalidResponse->assertSeeInField('submission_id', str_repeat('c3', 16));
        self::assertStringNotContainsString('<script>CONTACT-MARKER</script>', $invalidHtml);
        self::assertStringContainsString('&lt;script&gt;CONTACT-MARKER&lt;/script&gt;', $invalidHtml);
        $invalidXpath = $this->domXPath($invalidHtml);
        $this->assertXPathCount(1, $invalidXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[@class="row"]/div[@class="col-md-12"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]', 'EN validation alert inside shell');
        $this->assertXPathCount(0, $invalidXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]', 'EN validation alert not direct child');
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

    public function testInvalidThaiSubmissionRerendersFormWithErrorsAndKeptValues(): void
    {
        $invalid          = $this->payload('f6', 'SYNTHETIC INVALID TH');
        $invalid['email'] = 'not-an-email';
        $response         = $this->post('/contact-th', $invalid);
        $response->assertStatus(422);
        $this->assertCi3ContactTitle((string) $response->getBody());
        $response->assertSee('กรุณากรอกอีเมล');
        $response->assertSeeInField('fullname', 'SYNTHETIC INVALID TH');
        $response->assertSeeInField('email', 'not-an-email');
        $response->assertSeeInField('submission_id', str_repeat('f6', 16));
        $invalidXpath = $this->domXPath((string) $response->getBody());
        $this->assertXPathCount(1, $invalidXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[@class="row"]/div[@class="col-md-12"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]', 'TH validation alert inside shell');
        $this->assertXPathCount(0, $invalidXpath, '//section[@id="contact"]/div[@class="container"]/div[@class="col-md-4"]/div[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")]', 'TH validation alert not direct child');
        self::assertSame(0, $this->db->table('contact')->countAllResults());
        self::assertSame(0, $this->db->table('ci4_delivery_intents')->countAllResults());
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

        // CI3 column set and search field name; `search` above still works for old links.
        $listing = (string) $this->withSession($this->sessionFor($adminId, 1, null))
            ->get('/contact-list?searchText=CONTACT+B')
            ->getBody();
        foreach (['Id', 'Name', 'Email', 'Samsoniteid', 'Phone', 'Detail', 'Date'] as $header) {
            self::assertStringContainsString('<th>' . $header . '</th>', $listing, $header);
        }
        self::assertStringContainsString('<h3 class="box-title">Contact List</h3>', $listing);
        self::assertStringContainsString('name="searchText"', $listing);
        self::assertStringContainsString('SYNTHETIC CONTACT B', $listing);
        self::assertStringNotContainsString('SYNTHETIC CONTACT A', $listing);

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

    /** @param list<string> $needles */
    private function assertInOrder(string $html, array $needles): void
    {
        $last = -1;
        foreach ($needles as $needle) {
            $position = strpos($html, $needle, $last + 1);
            self::assertNotFalse($position, $needle);
            self::assertGreaterThan($last, $position, $needle);
            $last = $position;
        }
    }

    private function domXPath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));

        return new DOMXPath($document);
    }

    private function assertXPathCount(int $expected, DOMXPath $xpath, string $expression, string $case): void
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes, $case);
        self::assertSame($expected, $nodes->length, $case);
    }

    private function assertLocalAssetGraph(string $html): void
    {
        $stylesheets = [];
        foreach ($this->domAssetUrls($html) as $url) {
            $path = $this->localAssetPath($url);
            self::assertFileExists($path, $url);
            if (str_ends_with($path, '.css')) {
                $stylesheets[] = $path;
            }
        }

        $seen = [];
        foreach ($stylesheets as $stylesheet) {
            $this->assertCssGraph($stylesheet, $seen);
        }
    }

    /** @return list<string> */
    private function domAssetUrls(string $html): array
    {
        preg_match_all('/<(script|img|link)\\b[^>]*>/i', $html, $tags, PREG_SET_ORDER);
        $urls = [];
        foreach ($tags as $tag) {
            $attribute = strtolower($tag[1]) === 'link' ? 'href' : 'src';
            $pattern = '/(?<![\\w:-])' . $attribute . '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s"\'=<>`]+))/i';
            if (preg_match($pattern, $tag[0], $match) !== 1) {
                continue;
            }
            $url = $match[1];
            if ($url === '') {
                $url = $match[2] ?? '';
            }
            if ($url === '') {
                $url = $match[3] ?? '';
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /** @param array<string, bool> $seen */
    private function assertCssGraph(string $path, array &$seen): void
    {
        $real = realpath($path);
        self::assertNotFalse($real, $path);
        if (isset($seen[$real])) {
            return;
        }
        $seen[$real] = true;
        $css = (string) file_get_contents($real);
        preg_match_all('/(?:@import\\s+(?:url\\()?|url\\()\\s*[\\\'\"]?([^\\\'\")\\s;]+)[^)]*\\)?/i', $css, $matches);
        foreach ($matches[1] as $reference) {
            $reference = trim($reference);
            if ($reference === '' || str_starts_with($reference, 'data:') || str_starts_with($reference, '#')) {
                continue;
            }
            self::assertDoesNotMatchRegularExpression('/^(?:https?:)?\\/\\//i', $reference, $real . ': ' . $reference);
            $reference = explode('#', explode('?', $reference, 2)[0], 2)[0];
            $asset = str_starts_with($reference, '/')
                ? ROOTPATH . 'public' . $reference
                : dirname($real) . '/' . $reference;
            self::assertFileExists($asset, $real . ': ' . $reference);
            if (str_ends_with($asset, '.css')) {
                $this->assertCssGraph($asset, $seen);
            }
        }
    }

    private function localAssetPath(string $url): string
    {
        self::assertDoesNotMatchRegularExpression('/^\\/\\//', $url, $url);
        $host = parse_url($url, PHP_URL_HOST);
        self::assertTrue($host === null || $host === parse_url(base_url(), PHP_URL_HOST), $url);
        $path = parse_url($url, PHP_URL_PATH);
        self::assertIsString($path, $url);
        self::assertStringStartsWith('/assets/', $path, $url);

        return ROOTPATH . 'public' . $path;
    }

    private function assertCi3ContactTitle(string $html): void
    {
        self::assertStringContainsString('<title>Samsonite</title>', $html);
        self::assertStringNotContainsString('<title>Contact us</title>', $html);
        self::assertStringNotContainsString('<title>ติดต่อเรา</title>', $html);
    }

    private function assertAdaptedAdminLteAsset(): void
    {
        $path = ROOTPATH . 'public/assets/dist/js/app.min.js';
        $contents = (string) file_get_contents($path);
        self::assertSame('54101b5ffbeed57ac37b68edb22598cce27c6b859e57108d8b499dc850d48df9', hash('sha256', $contents));

        $runtimeOffset = strpos($contents, '"use strict"');
        self::assertNotFalse($runtimeOffset);
        $header = substr($contents, 0, $runtimeOffset);
        self::assertStringContainsString('@Author  Almsaeed Studio', $header);
        self::assertStringContainsString('@version 2.1.0', $header);
        self::assertStringContainsString('@license MIT <http://opensource.org/licenses/MIT>', $header);
        self::assertStringContainsString('@Email   <support@almsaeedstudio.com>', $header);
        self::assertSame(
            '4de4779418ce0c6a42f2f146f05b110f96fb3a9f8e8de264fdec8c3daff0407d',
            hash('sha256', substr($contents, $runtimeOffset)),
        );
    }

    /** @return array<string, string> */
    private function ci3AssetHashes(): array
    {
        return [
            'assets/js/jquery-3.2.1.min.js' => '87083882cc6015984eb0411a99d3981817f5dc5c90ba24f0940420c5548d82de',
            'assets/bootstrap/css/bootstrap.css' => '7e630d90c7234b0df1729f62b8f9e4bbfaf293d91a5a0ac46df25f2a6759e39a',
            'assets/bootstrap/js/bootstrap.min.js' => '53964478a7c634e8dad34ecc303dd8048d00dce4993906de1bacf67f663486ef',
            'assets/fontawesome/css/font-awesome.css' => '36e0a7e08bee65774168528938072c536437669c1b7458ac77976ec788e4439c',
            'assets/fonts/stylesheet.css' => '3d1df1d7dcdc73d899b6cde7b3333af5cac73ba2fda90aec0652ac1926a808f5',
            'assets/js/jquery.validate.js' => 'ce8302a3e292c9be113dc37579315cd74e40ceb12c4741c25afceef6b5e72f83',
            'assets/js/validation.js' => '8364fab53552b0781783ad062983de8e1905bcb0988116300f298e0618c7ac9e',
            'assets/js/addContact.js' => '9aa8c0e9cd3e5e8e67e93d18041481e0270c4efde167f3f833446dbfdbca319e',
            'assets/fontawesome/fonts/fontawesome-webfont.eot' => '7bfcab6db99d5cfbf1705ca0536ddc78585432cc5fa41bbd7ad0f009033b2979',
            'assets/fontawesome/fonts/fontawesome-webfont.svg' => 'ad6157926c1622ba4e1d03d478f1541368524bfc46f51e42fe0d945f7ef323e4',
            'assets/fontawesome/fonts/fontawesome-webfont.ttf' => 'aa58f33f239a0fb02f5c7a6c45c043d7a9ac9a093335806694ecd6d4edc0d6a8',
            'assets/fontawesome/fonts/fontawesome-webfont.woff' => 'ba0c59deb5450f5cb41b3f93609ee2d0d995415877ddfa223e8a8a7533474f07',
            'assets/fontawesome/fonts/fontawesome-webfont.woff2' => '2adefcbc041e7d18fcf2d417879dc5a09997aa64d675b7a3c4b6ce33da13f3fe',
            'assets/fonts/db_helvethaica_x_ext_v3.2-webfont.woff' => 'b4c796293bbef1e030fd8f93545ce9d5ab28309cfa857678d68aa37559caa81e',
            'assets/fonts/db_helvethaica_x_ext_v3.2-webfont.woff2' => 'af92beca4968b6425c4266f4297cc174dbcb32ea58d156146bb3ff0b5fcb245b',
            'assets/fonts/db_helvethaica_x_med_ext_v3.2-webfont.woff' => '9e051bb01116491cc307ba3afeb230f59f00dbdc62633ef451dc7acec0b31b69',
            'assets/fonts/db_helvethaica_x_med_ext_v3.2-webfont.woff2' => 'c20f71bb32770ef3c42718c8ea4b53ad243f09463622c1ad61477acb19f809c3',
            'assets/images/main-logo.png' => '1e410f31f7735b241b6dbb62a8723e4165f12fa0599ce6bf5fb9744d08f978b4',
            'assets/images/eng.png' => '8830eeeceba20a920a7e75932c68fedbde3b0d04cb0a41832c7cfa0e10e6bdba',
            'assets/images/thai.png' => '70645e14c283411e4215badb20860cacb9e845066c5277a518c3d79170ed9d38',
            'assets/images/img-footer.png' => '688aeee5af3396a6a47aeb61448ac6606ca0f00eb46d698781b88844bf5d1a7b',
            'assets/images/img-contact-1.png' => 'd1667ee6536768b744bf6770453608ca3c3422d15ebb03056ebdaa8b5e509240',
            'assets/images/img-contact-2.png' => 'd6070f3d5237523d5ea7ff7de4eb49cce0d3346e6ca8bb0b0a208db59250ffe6',
        ];
    }
}
