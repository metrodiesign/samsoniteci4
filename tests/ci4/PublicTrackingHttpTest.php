<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\AssertionFailedError;

final class PublicTrackingHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedTimeline();
    }

    public function testKnownTrackingIdRendersEnglishAndThaiTimelineInOrder(): void
    {
        $english = $this->get('/tracking/WP00C-TRACK-005');
        $english->assertStatus(200);
        $englishHtml = html_entity_decode((string) $english->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Both pinned CI3 result templates render status_name_th.
        $this->assertInOrder($englishHtml, [
            'สถานะทดสอบ 5',
            'สถานะทดสอบ 4',
            'สถานะทดสอบ 2',
            'สถานะทดสอบ 1',
        ]);
        $english->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        $english->assertDontSee('wp00c-customer-5@example.invalid');
        self::assertStringContainsString('05/08/2569', $englishHtml);
        self::assertStringContainsString('08/08/2569', $englishHtml);

        $thai = $this->get('/tracking-th/WP00C-TRACK-005');
        $thai->assertStatus(200);
        $thaiHtml = html_entity_decode((string) $thai->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertInOrder($thaiHtml, [
            'สถานะทดสอบ 5',
            'สถานะทดสอบ 4',
            'สถานะทดสอบ 2',
            'สถานะทดสอบ 1',
        ]);
        $thai->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        $thai->assertDontSee('wp00c-customer-5@example.invalid');
        self::assertStringContainsString('05/08/2569', $thaiHtml);
    }

    public function testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch(): void
    {
        $known = $this->get('/tracking?tracking_id=WP00C-TRACK-005');
        $known->assertStatus(200);
        self::assertStringContainsString(
            'สถานะทดสอบ 5',
            html_entity_decode((string) $known->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );

        foreach (['WP00C-TRACK-999', 'WP00C%', str_repeat('A', 101)] as $trackingId) {
            $result = $this->get('/tracking?tracking_id=' . rawurlencode($trackingId));
            $result->assertStatus(200);
            self::assertStringContainsString(
                'ไม่มีสินค้า',
                html_entity_decode((string) $result->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
            $result->assertDontSee('SYNTHETIC RETURN');
            $result->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }
    }

    public function testCanonicalQueryTakesPrecedenceAndLegacyQueryUsesTheSameExactLookup(): void
    {
        $canonical = $this->get('/tracking?tracking_id=WP00C-TRACK-005&searchText=WP00C-TRACK-999');
        $canonical->assertStatus(200);
        self::assertStringContainsString(
            'สถานะทดสอบ 5',
            html_entity_decode((string) $canonical->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );

        foreach ([
            '/tracking?searchText=WP00C-TRACK-005' => 'สถานะทดสอบ 5',
            '/tracking-th?searchText=WP00C-TRACK-005' => 'สถานะทดสอบ 5',
        ] as $url => $expectedStatus) {
            $response = $this->get($url);
            $response->assertStatus(200);
            self::assertStringContainsString(
                $expectedStatus,
                html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
            $response->assertDontSee('wp00c-customer-5@example.invalid');
        }
    }

    public function testCi3TrackingPostEndpointsUseTheSameExactLookup(): void
    {
        foreach ([
            '/track/trackstatus' => 'สถานะทดสอบ 5',
            '/track_th/trackstatus' => 'สถานะทดสอบ 5',
        ] as $route => $expectedStatus) {
            $response = $this->post($route, ['searchText' => 'WP00C-TRACK-005']);
            $response->assertStatus(200);
            self::assertStringContainsString(
                $expectedStatus,
                html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }

        $invalid = $this->post('/track/trackstatus', ['searchText' => ['WP00C-TRACK-005']]);
        $invalid->assertStatus(200);
        self::assertStringContainsString(
            'ไม่มีสินค้า',
            html_entity_decode((string) $invalid->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $invalid->assertDontSee('SYNTHETIC RETURN');
    }

    public function testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection(): void
    {
        $queries = [
            '/tracking?tracking_id=',
            '/tracking?tracking_id=WP00C_',
            '/tracking?tracking_id%5B%5D=WP00C-TRACK-005',
            '/tracking?searchText%5B%5D=WP00C-TRACK-005',
            '/tracking?tracking_id=%3Cscript%3Ealert(1)%3C%2Fscript%3E',
            '/tracking?tracking_id=%22TRACKING-QUOTE-MARKER%22',
            '/tracking?searchText=%0D%0AWP00C-TRACK-005',
            '/tracking?searchText=%00TRACKING-NUL-MARKER',
            '/tracking?tracking_id=%20WP00C-TRACK-005%20',
            '/tracking?searchText=%20WP00C-TRACK-005%20',
            '/tracking?tracking_id=' . str_repeat('A', 101),
        ];

        foreach ($queries as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $response->assertDontSee('SYNTHETIC RETURN');
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
            $response->assertDontSee('wp00c-customer-5@example.invalid');
            self::assertStringNotContainsString('alert(1)', (string) $response->getBody(), $url);
            self::assertStringNotContainsString('TRACKING-QUOTE-MARKER', (string) $response->getBody(), $url);
            self::assertStringNotContainsString('TRACKING-NUL-MARKER', (string) $response->getBody(), $url);
        }

        $segment = $this->get('/tracking/WP00C-TRACK-005%2Fmore');
        $segment->assertStatus(200);
        $segment->assertDontSee('SYNTHETIC RETURN');
        $segment->assertDontSee('SYNTHETIC CUSTOMER FIVE');
    }

    public function testCompleteStatusRendersGreenCircleInBothLanguages(): void
    {
        $english = $this->get('/tracking/WP00C-TRACK-006');
        $english->assertStatus(200);
        self::assertStringContainsString('circle-awe bg-success circle-awe-animate', (string) $english->getBody());

        $thai = $this->get('/tracking-th/WP00C-TRACK-006');
        $thai->assertStatus(200);
        // CI3 th/trackstatus.php compares status_name_th with the literal "complete".
        self::assertStringNotContainsString('circle-awe bg-success circle-awe-animate', (string) $thai->getBody());
        self::assertStringContainsString('circle-awe circle-awe-animate', (string) $thai->getBody());
    }

    public function testTrackingFormShowsLanguageSpecificPopupAndLegacyControls(): void
    {
        $english = $this->get('/tracking');
        $english->assertStatus(200);
        $englishHtml = (string) $english->getBody();
        self::assertStringContainsString('<title>Samsonite</title>', $englishHtml);
        foreach ([
            '<form role="form" id="addtrack"',
            'action="' . base_url('track/trackstatus') . '" method="post"',
            '<section id="track">',
            'class="con-center-track"',
            'TRACK &amp; TRACE',
            'Track Your Tracking Number',
            'name="searchText" id="searchText" class="search-txt form-control required"',
            'id="btnModal" class="main-btn-sm" data-toggle="modal" data-target="#exampleModal"',
            'class="modal fade" id="myModal"',
            "$('#myModal').modal('show')",
            'assets/js/addtrack.js',
            'assets/images/bg-tracking.png',
        ] as $contract) {
            self::assertStringContainsString($contract, $englishHtml, $contract);
        }
        self::assertStringNotContainsString('assets/css/public.css', $englishHtml);
        self::assertStringNotContainsString('<dialog', $englishHtml);
        self::assertStringContainsString('popup_en.png', $englishHtml);
        self::assertStringNotContainsString('popup_th.png', $englishHtml);
        $this->assertTrackingFormDom($englishHtml, base_url('track/trackstatus'), 'popup_en.png', 'EN');
        $this->assertInOrder($englishHtml, [
            'TRACK &amp; TRACE',
            'Track Your Tracking Number',
            'placeholder="Your Tracking ID"',
            'HOW TO CHECK',
            'value="CHECK NOW"',
            'CONTACT US',
            'SHOPPING',
            'assets/images/popup_en.png',
            "$('#myModal').modal('show')",
            'assets/js/addtrack.js',
        ]);

        $thai = $this->get('/tracking-th');
        $thai->assertStatus(200);
        $thaiHtml = (string) $thai->getBody();
        self::assertStringContainsString('action="' . base_url('track_th/trackstatus') . '" method="post"', $thaiHtml);
        $thaiDecoded = html_entity_decode($thaiHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertStringContainsString('วิธีตรวจสอบสถานะ', $thaiDecoded);
        self::assertStringContainsString('ระบุรหัสติดตามของคุณ', $thaiDecoded);
        self::assertStringContainsString('popup_th.png', $thaiHtml);
        self::assertStringNotContainsString('popup_en.png', $thaiHtml);
        $this->assertTrackingFormDom($thaiHtml, base_url('track_th/trackstatus'), 'popup_th.png', 'TH');
        $this->assertInOrder(html_entity_decode($thaiHtml, ENT_QUOTES, 'UTF-8'), [
            'TRACK & TRACE',
            'Track Your Tracking Number',
            'ระบุรหัสติดตามของคุณ',
            'วิธีตรวจสอบสถานะ',
            'value="ติดตาม"',
            'CONTACT US',
            'SHOPPING',
            'assets/images/popup_th.png',
            "$('#myModal').modal('show')",
            'assets/js/addtrack.js',
        ]);

        foreach ([$englishHtml, $thaiHtml] as $html) {
            self::assertStringContainsString('<section id="header">', $html);
            self::assertStringContainsString('<section id="footer">', $html);
            $this->assertInOrder($html, [
                'assets/js/jquery-3.2.1.min.js',
                'assets/bootstrap/js/bootstrap.min.js',
                'assets/bootstrap/css/bootstrap.css',
                'assets/css/main.css',
                'assets/fontawesome/css/font-awesome.css',
                'assets/fonts/stylesheet.css',
                // CI3 loadViews() renders the page-specific script before web/footer.php.
                'assets/js/addtrack.js',
                'assets/dist/js/app.min.js',
                'assets/js/jquery.validate.js',
                'assets/js/validation.js',
            ]);
        }
    }

    public function testTrackingFormBackgroundCascadeKeepsStaticMobileFallbackForEveryPublishedCombination(): void
    {
        $this->createBackgroundTable();
        $cases = [
            ['en', null, null],
            ['en', str_repeat('b', 32) . '.png', null],
            ['en', null, str_repeat('c', 32) . '.png'],
            ['en', str_repeat('b', 32) . '.png', str_repeat('c', 32) . '.png'],
            ['th', null, null],
            ['th', str_repeat('d', 32) . '.png', null],
            ['th', null, str_repeat('e', 32) . '.png'],
            ['th', str_repeat('d', 32) . '.png', str_repeat('e', 32) . '.png'],
        ];

        foreach ($cases as [$language, $laptop, $mobile]) {
            $suffix = $language === 'th' ? '_th' : '';
            $this->replacePublishedBackground([
                'image_track_laptop' . $suffix => $laptop,
                'image_track_mobile' . $suffix => $mobile,
            ]);

            $html = (string) $this->get($language === 'th' ? '/tracking-th' : '/tracking')->getBody();
            $this->assertTrackingFormBackgroundCascade($html, $laptop, $mobile, $language);
        }
    }

    public function testResultUsesCi3HierarchyLanguageLabelsAndNoTrackingIdParagraph(): void
    {
        $english = (string) $this->get('/tracking?tracking_id=WP00C-TRACK-005')->getBody();
        foreach (['<section id="rs-track">', 'class="con-pro-bar"', 'class="con-step-pass"', 'class="contain-process"', 'class="line-normal line-progress"'] as $contract) {
            self::assertStringContainsString($contract, $english, $contract);
        }
        self::assertStringNotContainsString('<picture', $english);
        self::assertStringNotContainsString('<source', $english);
        self::assertStringNotContainsString('data-tracking-id', $english);
        self::assertStringNotContainsString('WP00C-TRACK-005</p>', $english);
        $this->assertTrackingKnownResultDom($english, 4, 'EN known result');

        $thai = $this->get('/tracking-th?tracking_id=WP00C-TRACK-005');
        $thaiBody = html_entity_decode((string) $thai->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        self::assertStringContainsString('สถานะทดสอบ 5 08/08/2569', $thaiBody);
        self::assertStringNotContainsString('SYNTHETIC RETURN 08/08/2569', $thaiBody);
        $this->assertTrackingKnownResultDom((string) $thai->getBody(), 4, 'TH known result');
    }

    public function testResultBannerUsesOnlyPublishedBackgroundStoreOutput(): void
    {
        $withoutBanner = $this->get('/tracking/WP00C-TRACK-006');
        $withoutBanner->assertStatus(200);
        self::assertStringContainsString('banner-control', (string) $withoutBanner->getBody());
        self::assertStringContainsString('uploads/web/trackstatus_laptop.png', (string) $withoutBanner->getBody());

        $columns = implode(', ', array_map(
            static fn (string $field): string => $field . ' VARCHAR(64)',
            \App\Master\BackgroundStore::FIELDS,
        ));
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('tbl_background_web'));
        $this->db->query("DROP TABLE IF EXISTS {$table}");
        $this->db->query("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, status INTEGER, {$columns})");
        $name = str_repeat('a', 32) . '.png';
        $this->db->table('tbl_background_web')->insert([
            'id'                       => 1,
            'status'                   => 1,
            'image_trackstatus_laptop' => $name,
        ]);

        $withBanner = $this->get('/tracking/WP00C-TRACK-006');
        $withBanner->assertStatus(200);
        self::assertStringContainsString('banner-control', (string) $withBanner->getBody());
        self::assertStringContainsString('uploads/web/trackstatus_laptop.png', (string) $withBanner->getBody());
        self::assertStringNotContainsString('/background-image/' . $name, (string) $withBanner->getBody());
    }

    public function testTrackingPublicAssetsAreLocalRecursiveAndMatchCi3Bytes(): void
    {
        foreach (['/tracking', '/tracking-th'] as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $this->assertLocalAssetGraph((string) $response->getBody());
        }

        foreach ($this->ci3TrackingAssetHashes() as $path => $hash) {
            self::assertSame($hash, hash_file('sha256', ROOTPATH . 'public/' . $path), $path);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedDomAssetTags')]
    public function testDomAssetUrlsFailsClosedForMalformedAssetTags(string $html): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->domAssetUrls($html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('externalDomAssetTags')]
    public function testAssetGraphRejectsEveryExternalHtmlQuotingVariant(string $html): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->assertLocalAssetGraph($html);
    }

    public function testAssetGraphRejectsMissingFile(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->assertLocalAssetGraph('<img src="' . base_url('assets/images/missing-tracking-asset.png') . '">');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('externalCssReferences')]
    public function testCssAssetParserRejectsEveryExternalQuotingVariant(string $css): void
    {
        $this->expectException(\UnexpectedValueException::class);
        foreach ($this->cssReferences($css) as $reference) {
            $this->assertCssReferenceIsLocal($reference, $css);
        }
    }

    public function testCssAssetParserKeepsLocalReference(): void
    {
        self::assertSame(
            [base_url('assets/images/bg-tracking.png')],
            $this->cssReferences("#track { background-image: url('" . base_url('assets/images/bg-tracking.png') . "'); }"),
        );
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

        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $styles);
        foreach ($styles[1] as $css) {
            foreach ($this->cssReferences($css) as $reference) {
                $this->assertCssReferenceIsLocal($reference, 'inline style');
                $path = $this->localAssetPath($reference);
                self::assertFileExists($path, $reference);
            }
        }
    }

    /** @return list<string> */
    private function domAssetUrls(string $html): array
    {
        // HTML comments are not runtime nodes; CI3 header.php contains an empty favicon href in one.
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        preg_match_all('/<(script|img|link)\b[^>]*>/i', $html, $tags, PREG_SET_ORDER);
        $urls = [];
        foreach ($tags as $tag) {
            $attribute = strtolower($tag[1]) === 'link' ? 'href' : 'src';
            if (preg_match('/(?<![\\w:-])' . $attribute . '\\s*=/i', $tag[0]) !== 1) {
                continue;
            }
            $pattern = '/(?<![\\w:-])' . $attribute . '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s"\'=<>`]+))/i';
            if (preg_match($pattern, $tag[0], $match, PREG_UNMATCHED_AS_NULL) !== 1) {
                throw new \UnexpectedValueException('Unparseable ' . $attribute . ' attribute: ' . $tag[0]);
            }
            $url = ($match[1] ?? '') !== ''
                ? $match[1]
                : ((($match[2] ?? '') !== '') ? $match[2] : ($match[3] ?? ''));
            if ($url === '') {
                throw new \UnexpectedValueException('Empty ' . $attribute . ' attribute: ' . $tag[0]);
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /** @return iterable<string, array{string}> */
    public static function malformedDomAssetTags(): iterable
    {
        return [
            'script unclosed double quote' => ['<script src="https://cdn.example.invalid/a.js>'],
            'img unclosed single quote'    => ["<img src='//cdn.example.invalid/a.png>"],
            'link missing unquoted value'  => ['<link href=>'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function externalDomAssetTags(): iterable
    {
        $cases = [];
        foreach ([['script', 'src'], ['img', 'src'], ['link', 'href']] as [$node, $attribute]) {
            foreach ([
                'double' => '"https://cdn.example.invalid/asset.js"',
                'single' => "'//cdn.example.invalid/asset.js'",
                'unquoted' => 'http://cdn.example.invalid/asset.js',
            ] as $quoting => $value) {
                $cases[$node . ' ' . $quoting] = ['<' . $node . ' ' . $attribute . '=' . $value . '>'];
            }
        }

        return $cases;
    }

    /** @return iterable<string, array{string}> */
    public static function externalCssReferences(): iterable
    {
        return [
            'double quote' => ['url("https://cdn.example.invalid/a.css")'],
            'single quote' => ["url('//cdn.example.invalid/a.css')"],
            'unquoted'     => ['@import url(http://cdn.example.invalid/a.css)'],
        ];
    }

    /** @return list<string> */
    private function cssReferences(string $css): array
    {
        preg_match_all('/(?:@import\\s+(?:url\\()?|url\\()\\s*[\'\"]?([^\'\")\\s;]+)[^)]*\\)?/i', $css, $matches);

        return array_values(array_filter(array_map(
            static fn (string $reference): string => trim($reference),
            $matches[1],
        ), static fn (string $reference): bool => $reference !== '' && ! str_starts_with($reference, 'data:') && ! str_starts_with($reference, '#')));
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
        foreach ($this->cssReferences((string) file_get_contents($real)) as $reference) {
            $this->assertCssReferenceIsLocal($reference, $real);
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

    private function assertCssReferenceIsLocal(string $reference, string $context): void
    {
        $host = parse_url($reference, PHP_URL_HOST);
        if (str_starts_with($reference, '//')
            || ($host !== null && $host !== parse_url(base_url(), PHP_URL_HOST))) {
            throw new \UnexpectedValueException('External CSS asset: ' . $context . ': ' . $reference);
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

    private function createBackgroundTable(): void
    {
        $columns = implode(', ', array_map(
            static fn (string $field): string => $field . ' VARCHAR(64)',
            \App\Master\BackgroundStore::FIELDS,
        ));
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('tbl_background_web'));
        $this->db->query("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, status INTEGER, {$columns})");
    }

    /** @param array<string, string|null> $images */
    private function replacePublishedBackground(array $images): void
    {
        $this->db->table('tbl_background_web')->truncate();
        $this->db->table('tbl_background_web')->insert([
            'id' => 1,
            'status' => 1,
            ...$images,
        ]);
    }

    private function assertTrackingFormBackgroundCascade(string $html, ?string $laptop, ?string $mobile, string $language): void
    {
        // Pinned CI3 en/th track.php keeps only the static laptop URL in a source comment;
        // responsive fallback lives in assets/css/main.css and CMS rows do not alter this view.
        self::assertStringContainsString(base_url('assets/images/bg-tracking.png'), $html, $language);
        self::assertSame(0, substr_count($html, '/background-image/'), $language);
        if ($laptop !== null) {
            self::assertStringNotContainsString($laptop, $html, $language . ' laptop DB value');
        }
        if ($mobile !== null) {
            self::assertStringNotContainsString($mobile, $html, $language . ' mobile DB value');
        }
        $css = (string) file_get_contents(PUBLICPATH . 'assets/css/main.css');
        self::assertStringContainsString('../../uploads/web/track_laptop.png', $css);
        self::assertStringContainsString('../../uploads/web/track_mobile.png', $css);
    }

    /** @return array<string, string> */
    private function ci3TrackingAssetHashes(): array
    {
        return [
            'assets/js/addtrack.js' => '8570b028d3f67cbbe6aa2cc72f3ca70f2d3302ab94546440da72b98de4a20130',
            'assets/images/bg-tracking.png' => '16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a',
            'assets/images/bg-tracking-mb.png' => '58e2c7e48ff6ee4791ff8cbd13215b0f301cef956bafc47f55de690e810eb7c3',
            'assets/images/popup_en.png' => '7ec545b28528c595c1d2e0aeb01d8f8a72ec80105ba81eac2ad4312755aab025',
            'assets/images/popup_th.png' => 'e5078cb83be73c233f3d9421d77edba35a130a365f99bfb6fc896a68bb2ac85e',
        ];
    }

    public function testResultViewEmptyTimelineShowsUnpassPlaceholder(): void
    {
        $html = view('tracking_result', [
            'language'              => 'en',
            'trackId'               => 'WP00C-TRACK-005',
            'timeline'              => [],
            'backgroundImage'       => null,
            'backgroundImageMobile' => null,
        ]);

        $this->assertStringContainsString('circle-awe bg-unpass', $html);
        $this->assertStringContainsString('ไม่มีสินค้า', $html);
        $xpath = $this->domXPath($html);
        $step = '//section[@id="rs-track"]/div[@class="container"]/div[@class="row"]/div[@class="con-pro-bar"]/div[@class="con-step-pass"]';
        $this->assertXPathCount(1, $xpath, $step, 'empty direct step');
        $this->assertXPathCount(1, $xpath, $step . '/div[contains(concat(" ", normalize-space(@class), " "), " bg-unpass ")]/div[@class="line-normal"]', 'empty circle line hierarchy');
        $this->assertXPathCount(1, $xpath, $step . '/div[@class="txt-normal"]', 'empty direct placeholder');
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

    private function assertTrackingFormDom(string $html, string $action, string $popup, string $case): void
    {
        $xpath = $this->domXPath($html);
        $center = '//form[@id="addtrack" and @action="' . $action . '"]/section[@id="track"]/div[@class="container"]/div[@class="row"]/div[@class="con-center-track"]';
        $modal = $center . '/div[@id="myModal" and contains(concat(" ", normalize-space(@class), " "), " modal ")]';
        $content = $modal . '/div[@class="modal-dialog"]/div[@class="modal-content"]';

        $this->assertXPathCount(1, $xpath, $center . '/input[@name="searchText" and @id="searchText"]', $case . ' search input hierarchy');
        $this->assertXPathCount(1, $xpath, $content . '/div[@class="modal-header"]', $case . ' modal header hierarchy');
        $this->assertXPathCount(1, $xpath, $content . '/div[@class="modal-body"]//img[contains(@src, "' . $popup . '")]', $case . ' modal popup hierarchy');
        $this->assertXPathCount(1, $xpath, $content . '/div[@class="modal-footer"]', $case . ' modal footer hierarchy');
    }

    private function assertTrackingKnownResultDom(string $html, int $steps, string $case): void
    {
        $xpath = $this->domXPath($html);
        $bar = '//section[@id="rs-track"]/div[@class="container"]/div[@class="row"]/div[@class="con-pro-bar"]';
        $step = $bar . '/div[@class="con-step-pass"]';

        $this->assertXPathCount($steps, $xpath, $step, $case . ' direct steps');
        $this->assertXPathCount($steps, $xpath, $step . '/div[@class="contain-process"]', $case . ' process hierarchy');
        $this->assertXPathCount($steps, $xpath, $step . '/div[@class="txt-normal"]', $case . ' label hierarchy');
        $this->assertXPathCount($steps, $xpath, $step . '/div[@class="contain-process"]/div[contains(concat(" ", normalize-space(@class), " "), " circle-awe ")]', $case . ' circle hierarchy');
        $this->assertXPathCount($steps - 1, $xpath, $step . '/div[@class="contain-process"]/div[@class="line-normal line-progress"]', $case . ' line hierarchy');
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

    private function createTables(): void
    {
        $tables = [
            'request_order' => 'request_id INTEGER PRIMARY KEY, trackID VARCHAR(100) NOT NULL, customerFullname VARCHAR(250), customerEmail VARCHAR(100)',
            'status_log'    => 'id INTEGER PRIMARY KEY, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
            'statusaction'  => 'status_id INTEGER PRIMARY KEY, status_name VARCHAR(128) NOT NULL, status_name_th VARCHAR(128) NOT NULL',
        ];

        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
        $backgrounds = $this->db->escapeIdentifiers($this->db->prefixTable('tbl_background_web'));
        $this->db->query("DROP TABLE IF EXISTS {$backgrounds}");
    }

    private function seedTimeline(): void
    {
        $this->db->table('request_order')->insert([
            'request_id'       => 91005,
            'trackID'          => 'WP00C-TRACK-005',
            'customerFullname' => 'SYNTHETIC CUSTOMER FIVE',
            'customerEmail'    => 'wp00c-customer-5@example.invalid',
        ]);
        $this->db->table('statusaction')->insertBatch([
            ['status_id' => 1, 'status_name' => 'SYNTHETIC NEW', 'status_name_th' => 'สถานะทดสอบ 1'],
            ['status_id' => 2, 'status_name' => 'SYNTHETIC REQUEST', 'status_name_th' => 'สถานะทดสอบ 2'],
            ['status_id' => 4, 'status_name' => 'SYNTHETIC REPAIR COMPLETE', 'status_name_th' => 'สถานะทดสอบ 4'],
            ['status_id' => 5, 'status_name' => 'SYNTHETIC RETURN', 'status_name_th' => 'สถานะทดสอบ 5'],
            ['status_id' => 6, 'status_name' => 'complete', 'status_name_th' => 'เสร็จสมบูรณ์'],
        ]);
        $this->db->table('status_log')->insertBatch([
            ['id' => 92007, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 1, 'cdate' => '2026-08-05 00:00:00'],
            ['id' => 92008, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 2, 'cdate' => '2026-08-06 00:00:00'],
            ['id' => 92009, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 4, 'cdate' => '2026-08-07 00:00:00'],
            ['id' => 92010, 'order_id' => 'WP00C-TRACK-005', 'action_id' => 5, 'cdate' => '2026-08-08 00:00:00'],
            ['id' => 92020, 'order_id' => 'WP00C-TRACK-006', 'action_id' => 6, 'cdate' => '2026-08-12 00:00:00'],
        ]);
        $this->db->table('request_order')->insert([
            'request_id'       => 91006,
            'trackID'          => 'WP00C-TRACK-006',
            'customerFullname' => 'SYNTHETIC CUSTOMER SIX',
            'customerEmail'    => 'wp00c-customer-6@example.invalid',
        ]);
    }
}
