<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
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
        $english->assertSee('SYNTHETIC RETURN');
        $english->assertSeeInOrder([
            'SYNTHETIC RETURN',
            'SYNTHETIC REPAIR COMPLETE',
            'SYNTHETIC REQUEST',
            'SYNTHETIC NEW',
        ]);
        $english->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        $english->assertDontSee('wp00c-customer-5@example.invalid');
        $english->assertSee('05/08/2569');
        $english->assertSee('08/08/2569');

        $thai = $this->get('/tracking-th/WP00C-TRACK-005');
        $thai->assertStatus(200);
        $thai->assertSeeInOrder([
            'สถานะทดสอบ 5',
            'สถานะทดสอบ 4',
            'สถานะทดสอบ 2',
            'สถานะทดสอบ 1',
        ]);
        $thai->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        $thai->assertDontSee('wp00c-customer-5@example.invalid');
        $thai->assertSee('05/08/2569');
    }

    public function testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch(): void
    {
        $known = $this->get('/tracking?tracking_id=WP00C-TRACK-005');
        $known->assertStatus(200);
        $known->assertSee('SYNTHETIC RETURN');

        foreach (['WP00C-TRACK-999', 'WP00C%', str_repeat('A', 101)] as $trackingId) {
            $result = $this->get('/tracking?tracking_id=' . rawurlencode($trackingId));
            $result->assertStatus(200);
            $result->assertSee('Tracking ID not found');
            $result->assertDontSee('SYNTHETIC RETURN');
            $result->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }
    }

    public function testCanonicalQueryTakesPrecedenceAndLegacyQueryUsesTheSameExactLookup(): void
    {
        $canonical = $this->get('/tracking?tracking_id=WP00C-TRACK-005&searchText=WP00C-TRACK-999');
        $canonical->assertStatus(200);
        $canonical->assertSee('SYNTHETIC RETURN');

        foreach ([
            '/tracking?searchText=WP00C-TRACK-005' => 'SYNTHETIC RETURN',
            '/tracking-th?searchText=WP00C-TRACK-005' => 'สถานะทดสอบ 5',
        ] as $url => $expectedStatus) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $response->assertSee($expectedStatus);
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
            $response->assertDontSee('wp00c-customer-5@example.invalid');
        }
    }

    public function testCi3TrackingPostEndpointsUseTheSameExactLookup(): void
    {
        foreach ([
            '/track/trackstatus' => 'SYNTHETIC RETURN',
            '/track_th/trackstatus' => 'สถานะทดสอบ 5',
        ] as $route => $expectedStatus) {
            $response = $this->post($route, ['searchText' => 'WP00C-TRACK-005']);
            $response->assertStatus(200);
            $response->assertSee($expectedStatus);
            $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
        }

        $invalid = $this->post('/track/trackstatus', ['searchText' => ['WP00C-TRACK-005']]);
        $invalid->assertStatus(200);
        $invalid->assertSee('Tracking ID not found');
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
        foreach (['/tracking/', '/tracking-th/'] as $prefix) {
            $page = $this->get($prefix . 'WP00C-TRACK-006');
            $page->assertStatus(200);
            $page->assertSee('circle-awe bg-success circle-awe-animate');
        }
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
            'assets/images/bg-tracking-mb.png',
        ] as $contract) {
            self::assertStringContainsString($contract, $englishHtml, $contract);
        }
        self::assertStringNotContainsString('assets/css/public.css', $englishHtml);
        self::assertStringNotContainsString('<dialog', $englishHtml);
        $english->assertSee('popup_en.png');
        $english->assertDontSee('popup_th.png');

        $thai = $this->get('/tracking-th');
        $thai->assertStatus(200);
        $thaiHtml = (string) $thai->getBody();
        self::assertStringContainsString('action="' . base_url('track_th/trackstatus') . '" method="post"', $thaiHtml);
        $thai->assertSee('วิธีตรวจสอบสถานะ');
        $thai->assertSee('ระบุรหัสติดตามของคุณ');
        $thai->assertSee('popup_th.png');
        $thai->assertDontSee('popup_en.png');
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

        $thai = $this->get('/tracking-th?tracking_id=WP00C-TRACK-005');
        $thai->assertSee('สถานะทดสอบ 5 08/08/2569');
        $thai->assertDontSee('SYNTHETIC RETURN 08/08/2569');
    }

    public function testResultBannerUsesOnlyPublishedBackgroundStoreOutput(): void
    {
        $withoutBanner = $this->get('/tracking/WP00C-TRACK-006');
        $withoutBanner->assertStatus(200);
        $withoutBanner->assertDontSee('banner-control');

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
        $withBanner->assertSee('banner-control');
        $withBanner->assertSee('/background-image/' . $name);
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
        preg_match_all('/<(script|img|link)\b[^>]*>/i', $html, $tags, PREG_SET_ORDER);
        $urls = [];
        foreach ($tags as $tag) {
            $attribute = strtolower($tag[1]) === 'link' ? 'href' : 'src';
            if (preg_match('/(?<![\\w:-])' . $attribute . '\\s*=/i', $tag[0]) !== 1) {
                continue;
            }
            $pattern = '/(?<![\\w:-])' . $attribute . '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s"\'=<>`]+))/i';
            if (preg_match($pattern, $tag[0], $match) !== 1) {
                throw new \UnexpectedValueException('Unparseable ' . $attribute . ' attribute: ' . $tag[0]);
            }
            $url = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
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
        $staticLaptop = base_url('assets/images/bg-tracking.png');
        $staticMobile = base_url('assets/images/bg-tracking-mb.png');
        self::assertStringContainsString('@media (max-width: 850px)', $html, $language);
        $this->assertAppearsBefore($html, $staticLaptop, $staticMobile, $language . ' static fallback');
        self::assertSame(
            (int) ($laptop !== null) + (int) ($mobile !== null),
            substr_count($html, '/background-image/'),
            $language . ' published background count',
        );

        if ($laptop !== null) {
            $publishedLaptop = base_url('background-image/' . $laptop);
            $this->assertAppearsBefore($html, $staticLaptop, $publishedLaptop, $language . ' laptop override');
            $this->assertAppearsBefore($html, $publishedLaptop, $staticMobile, $language . ' mobile fallback wins');
        }

        if ($mobile !== null) {
            $publishedMobile = base_url('background-image/' . $mobile);
            $this->assertAppearsBefore($html, $staticMobile, $publishedMobile, $language . ' mobile override');
        }
    }

    private function assertAppearsBefore(string $text, string $first, string $second, string $case): void
    {
        $firstPosition = strpos($text, $first);
        $secondPosition = strpos($text, $second);
        if ($firstPosition === false || $secondPosition === false) {
            self::fail($case . ': missing CSS reference');
        }

        self::assertLessThan($secondPosition, $firstPosition, $case);
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
