<?php

namespace Tests\Ci4;

use App\Authentication\ShadowUserStore;
use App\Controllers\BaseController;
use CodeIgniter\Database\Query;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\AssertionFailedError;

final class AccessDeniedHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;

    private int $branchUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $users = new ShadowUserStore($this->db);
        $hash = password_hash('Synthetic access denied passphrase', PASSWORD_DEFAULT);
        $this->adminId = $users->create('access-admin@example.invalid', $hash, 1, null);
        $this->branchUserId = $users->create('access-branch@example.invalid', $hash, 2, 1);

        $allowed = static fn (): ResponseInterface => service('response')->setJSON(['status' => 'allowed']);
        $this->withRoutes([
            ['GET', 'test/access/authorization', $allowed, ['filter' => 'authorized:unknown']],
            ['POST', 'test/access/authorization', $allowed, ['filter' => 'authorized:unknown']],
            ['PUT', 'test/access/authorization', $allowed, ['filter' => 'authorized:unknown']],
            ['GET', 'test/access/branchless', $allowed, ['filter' => ['web-auth', 'branchless']]],
            ['GET', 'test/access/authorized-allowed', $allowed, ['filter' => 'authorized:read']],
            ['GET', 'test/access/branchless-allowed', $allowed, ['filter' => ['web-auth', 'branchless']]],
        ]);
    }

    public function testAuthorizationDenialRendersCi3BodyInAuthenticatedAdminChrome(): void
    {
        $response = $this->htmlAuthorizationRequest();
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertNotRedirect();
        $body = (string) $response->response()->getBody();

        foreach ([
            '<title>Access Denied | Samsonite Tracking</title>',
            '<body class="admin">',
            '<nav aria-label="Main navigation">',
            '<main class="content">',
            '<div class="content-wrapper">',
            '<section class="content-header">',
            '<small>You are not authorize user to use this</small>',
            '<section class="content">',
            '<img src="/assets/images/access.png" alt="Access Denied Image">',
        ] as $contract) {
            self::assertStringContainsString($contract, $body, $contract);
        }
        self::assertSame(1, preg_match(
            '#<main class="content">(?:\s|<!--.*?-->)*<div class="content-wrapper">\s*<section class="content-header">\s*<h1>\s*Access Denied\s*<small>You are not authorize user to use this</small>#s',
            $body,
        ));
        self::assertStringNotContainsString('class="page-header"', $body);
        self::assertStringNotContainsString('id="page-title"', $body);
    }

    public function testBranchlessDenialUsesTheSameHtmlRepresentationWithoutController(): void
    {
        $response = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession($this->branchSession())
            ->get('/test/access/branchless');
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertNotRedirect();
        self::assertStringContainsString('<div class="content-wrapper">', (string) $response->response()->getBody());
        self::assertStringContainsString('Access Denied', (string) $response->response()->getBody());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('negotiationCases')]
    public function testAcceptNegotiationIsExplicitAndFailClosed(array $headers, bool $expectsHtml): void
    {
        $response = $this
            ->withHeaders($headers)
            ->withSession($this->branchSession())
            ->get('/test/access/authorization');
        $response->assertStatus(403);

        if ($expectsHtml) {
            $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            self::assertStringContainsString('<div class="content-wrapper">', (string) $response->response()->getBody());

            return;
        }

        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        self::assertSame('{"error":"forbidden"}', (string) $response->response()->getBody());
    }

    /** @return iterable<string, array{array<string, string>, bool}> */
    public static function negotiationCases(): iterable
    {
        return [
            'explicit HTML' => [['Accept' => 'text/html'], true],
            'browser header' => [['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'], true],
            'absent Accept' => [[], false],
            'wildcard only' => [['Accept' => '*/*'], false],
            'text wildcard only' => [['Accept' => 'text/*'], false],
            'JSON' => [['Accept' => 'application/json'], false],
            'JSON suffix range' => [['Accept' => 'application/*+json'], false],
            'XHTML only' => [['Accept' => 'application/xhtml+xml'], false],
            'HTML q zero' => [['Accept' => 'text/html;q=0'], false],
            'invalid HTML q' => [['Accept' => 'text/html;q=wat'], false],
            'out of range HTML q' => [['Accept' => 'text/html;q=1.1'], false],
            'JSON wins' => [['Accept' => 'application/json;q=1,text/html;q=0.5'], false],
            'HTML wins' => [['Accept' => 'text/html;q=1,application/json;q=0.5'], true],
            'equal q fails closed' => [['Accept' => 'text/html;q=1,application/json;q=1'], false],
            'duplicate HTML fails closed' => [['Accept' => 'text/html;q=1,text/html;q=0.5'], false],
            'case and parameter' => [['Accept' => 'TEXT/HTML; charset=UTF-8; q=1'], true],
            'malformed media type' => [['Accept' => 'text/htmlfoo'], false],
            'quoted media type' => [['Accept' => '"text/html"'], false],
            'empty range' => [['Accept' => 'text/html,'], false],
            'whitespace in media type' => [['Accept' => 'text / html'], false],
        ];
    }

    public function testJsonAjaxAndAnonymousDenialsDoNotQueryMenuData(): void
    {
        $menuQueries = [];
        $listener = static function (Query $query) use (&$menuQueries): void {
            $sql = $query->getQuery();
            if (str_contains($sql, 'group_menu') || str_contains($sql, 'tbl_menu')) {
                $menuQueries[] = $sql;
            }
        };
        Events::on('DBQuery', $listener);

        try {
            $this->withSession($this->branchSession())
                ->get('/test/access/authorization')
                ->assertStatus(403);
            $this->withHeaders(['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest'])
                ->withSession($this->branchSession())
                ->get('/test/access/authorization')
                ->assertStatus(403);
            $this->withHeaders(['Accept' => 'text/html'])
                ->withSession([])
                ->get('/test/access/authorization')
                ->assertStatus(401);
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        self::assertSame([], $menuQueries);
    }

    public function testAjaxAndUnauthenticatedRequestsCannotSelectHtml(): void
    {
        $ajax = $this
            ->withHeaders(['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest'])
            ->withSession($this->branchSession())
            ->get('/test/access/authorization');
        $ajax->assertStatus(403);
        self::assertSame('{"error":"forbidden"}', (string) $ajax->response()->getBody());

        $anonymous = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession([])
            ->get('/test/access/authorization');
        $anonymous->assertStatus(401);
        $anonymous->assertJSONExact(['error' => 'unauthenticated']);
        $anonymous->assertDontSee('Access Denied');
    }

    public function testMethodDoesNotChangeDenialRepresentationOrStatus(): void
    {
        foreach (['post', 'put'] as $method) {
            $html = $this
                ->withHeaders(['Accept' => 'text/html'])
                ->withSession($this->branchSession())
                ->{$method}('/test/access/authorization', ['request_marker' => 'BODY-MARKER']);
            $html->assertStatus(403);
            $html->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            self::assertStringContainsString('<div class="content-wrapper">', (string) $html->response()->getBody());

            $json = $this
                ->withHeaders([])
                ->withSession($this->branchSession())
                ->{$method}('/test/access/authorization', ['request_marker' => 'BODY-MARKER']);
            $json->assertStatus(403);
            self::assertSame('{"error":"forbidden"}', (string) $json->response()->getBody());
        }
    }

    public function testAllowedPathsDoNotUseTheResponder(): void
    {
        $authorized = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession($this->adminSession())
            ->get('/test/access/authorized-allowed');
        $authorized->assertStatus(200);
        $authorized->assertJSONExact(['status' => 'allowed']);

        $branchless = $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession($this->adminSession())
            ->get('/test/access/branchless-allowed');
        $branchless->assertStatus(200);
        $branchless->assertJSONExact(['status' => 'allowed']);
    }

    public function testHtmlDenialDoesNotReflectRequestControlledValues(): void
    {
        $markers = ['QUERY-MARKER-827', 'BODY-MARKER-827', 'HEADER-MARKER-827', 'OBJECT-MARKER-827'];
        $response = $this
            ->withHeaders(['Accept' => 'text/html', 'X-Trace-Marker' => $markers[2]])
            ->withSession($this->branchSession())
            ->post('/test/access/authorization?route_marker=' . $markers[0] . '&object=' . $markers[3], ['payload' => $markers[1]]);
        $response->assertStatus(403);
        $body = (string) $response->response()->getBody();

        foreach ($markers as $marker) {
            self::assertStringNotContainsString($marker, $body, $marker);
        }
        foreach (['BranchID', 'exception', 'database detail'] as $detail) {
            self::assertStringNotContainsString($detail, $body, $detail);
        }
    }

    public function testAccessAssetIsExactAndTheRecursiveGraphIsLocal(): void
    {
        $body = (string) $this->htmlAuthorizationRequest()->response()->getBody();
        self::assertSame(
            'cd1c2f92a6f5c4dd695037905e764db9a1e6b3810870f4bf329dad53b6b96c8c',
            hash_file('sha256', ROOTPATH . 'public/assets/images/access.png'),
        );
        $this->assertLocalAssetGraph($body);
    }

    public function testAssetGraphFailsClosedForMissingExternalAndMalformedReferences(): void
    {
        foreach ([
            '<img src="/assets/images/missing-access.png">',
            '<img src="https://cdn.example.invalid/access.png">',
            '<img src="/assets/images/access.png>',
        ] as $html) {
            try {
                $this->assertLocalAssetGraph($html);
                self::fail('Expected asset graph rejection: ' . $html);
            } catch (AssertionFailedError | \UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNormalLayoutDoesNotInheritTheAccessDeniedProfileAcrossRenders(): void
    {
        $this->withSession($this->adminSession())->get('/test/access/authorized-allowed')->assertStatus(200);

        $before = $this->renderNormalBaseLayout('Before denial');
        self::assertStringContainsString('<div class="page-header">', $before);
        self::assertStringContainsString('id="page-title">Before denial', $before);

        $denied = $this->htmlAuthorizationRequest();
        $denied->assertStatus(403);
        self::assertStringNotContainsString('class="page-header"', (string) $denied->response()->getBody());

        $after = $this->renderNormalBaseLayout('After denial');
        self::assertStringContainsString('<div class="page-header">', $after);
        self::assertStringContainsString('id="page-title">After denial', $after);
        self::assertStringNotContainsString('Access Denied</h1>', $after);
    }

    private function renderNormalBaseLayout(string $title): string
    {
        $controller = new class extends BaseController {
            public function render(string $title): string
            {
                return $this->layout($title, '<p>Normal layout content</p>');
            }
        };
        $controller->initController(service('request'), service('response'), service('logger'));

        return $controller->render($title);
    }

    public function testUnknownRouteIsStillNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $this->withSession($this->adminSession())->get('/test/access/unknown');
    }

    private function htmlAuthorizationRequest(): \CodeIgniter\Test\TestResponse
    {
        return $this
            ->withHeaders(['Accept' => 'text/html'])
            ->withSession($this->branchSession())
            ->get('/test/access/authorization');
    }

    /** @return array<string, int|bool|null|string> */
    private function adminSession(): array
    {
        return [
            'userId' => $this->adminId,
            'role' => 1,
            'GroupID' => 1,
            'BranchID' => null,
            'sessionVersion' => 1,
            'isLoggedIn' => true,
            'name' => 'Synthetic admin',
        ];
    }

    /** @return array<string, int|bool|null|string> */
    private function branchSession(): array
    {
        return [
            'userId' => $this->branchUserId,
            'role' => 2,
            'GroupID' => 4,
            'BranchID' => 1,
            'sessionVersion' => 1,
            'isLoggedIn' => true,
            'name' => 'Synthetic branch',
        ];
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
        preg_match_all('/<(script|img|link)\b[^>]*>/i', $html, $tags, PREG_SET_ORDER);
        $urls = [];
        foreach ($tags as $tag) {
            $attribute = strtolower($tag[1]) === 'link' ? 'href' : 'src';
            if (preg_match('/(?<![\w:-])' . $attribute . '\s*=/i', $tag[0]) !== 1) {
                continue;
            }
            $pattern = '/(?<![\w:-])' . $attribute . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i';
            if (preg_match($pattern, $tag[0], $match) !== 1) {
                throw new \UnexpectedValueException('Unparseable ' . $attribute . ' attribute.');
            }
            $url = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] ?? ''));
            if ($url === '') {
                throw new \UnexpectedValueException('Empty ' . $attribute . ' attribute.');
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
        preg_match_all('/(?:@import\s+(?:url\()?|url\()\s*[\'\"]?([^\'\")\s;]+)[^)]*\)?/i', (string) file_get_contents($real), $matches);
        foreach ($matches[1] as $reference) {
            $reference = trim($reference);
            if ($reference === '' || str_starts_with($reference, 'data:') || str_starts_with($reference, '#')) {
                continue;
            }
            self::assertDoesNotMatchRegularExpression('/^(?:https?:)?\/\//i', $reference, $real . ': ' . $reference);
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
        self::assertDoesNotMatchRegularExpression('/^\/\//', $url, $url);
        $host = parse_url($url, PHP_URL_HOST);
        self::assertTrue($host === null || $host === parse_url(base_url(), PHP_URL_HOST), $url);
        $path = parse_url($url, PHP_URL_PATH);
        self::assertIsString($path, $url);
        self::assertStringStartsWith('/assets/', $path, $url);

        return ROOTPATH . 'public' . $path;
    }
}
