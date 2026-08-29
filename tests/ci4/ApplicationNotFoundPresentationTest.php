<?php

namespace Tests\Ci4;

use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;
use Config\Exceptions;
use Config\Routing;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\AssertionFailedError;

final class ApplicationNotFoundPresentationTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = 'App';

    public function testHtmlPageNotFoundRendererKeepsTheCi3ContentBlockAndImage(): void
    {
        [$body, $status] = $this->renderPageNotFound('wp03l-secret');
        self::assertSame(404, $status);

        $document = new DOMDocument();
        self::assertTrue(@$document->loadHTML($body));
        $xpath = new DOMXPath($document);
        self::assertSame('404', trim((string) $xpath->evaluate('string((//h1/text())[1])')));
        self::assertSame(1, $xpath->query('//div[@class="content-wrapper"]/section[@class="content-header"]//small[text()="This is not the page you are looking for"]')->length);

        $image = $xpath->query('//section[@class="content"]//img')->item(0);
        self::assertInstanceOf(DOMElement::class, $image);
        self::assertSame('/assets/images/404.png', $image->getAttribute('src'));
        self::assertSame('Page Not Found Image', $image->getAttribute('alt'));
    }

    public function testRendererDoesNotReflectExceptionOrRequestDetails(): void
    {
        $message = "wp03l-secret\n<script>wp03l</script>\n/tmp/wp03l-secret.php\nline1\n#0 wp03l-secret";
        [$body] = $this->renderPageNotFound($message, '/missing/wp03l-secret?token=wp03l-secret');

        foreach ([
            'wp03l-secret',
            '<script>wp03l</script>',
            '&lt;script&gt;wp03l&lt;/script&gt;',
            '/tmp/wp03l-secret.php',
            'line1',
            '#0',
            'token=',
        ] as $detail) {
            self::assertStringNotContainsString($detail, $body, $detail);
        }
    }

    public function test404AssetIsExactAndRenderedAssetGraphIsLocal(): void
    {
        [$body] = $this->renderPageNotFound('wp03l-secret');
        self::assertSame(
            '209cebe97c229d48ba4ad2906fb17ff1ccf533756122383781c7abc2ee5d0e8a',
            hash_file('sha256', PUBLICPATH . 'assets/images/404.png'),
        );
        $this->assertLocalAssetGraph($body);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('externalAssetVariants')]
    public function testAssetGraphRejectsExternalHtmlAndCssReferences(string $html): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->assertLocalAssetGraph($html);
    }

    /** @return iterable<string, array{string}> */
    public static function externalAssetVariants(): iterable
    {
        $variants = [];
        foreach (['"https://cdn.example.invalid/404.png"', "'//cdn.example.invalid/404.png'", 'http://cdn.example.invalid/404.png'] as $quoting) {
            $variants['img ' . $quoting] = ['<img src=' . $quoting . '>'];
            $variants['link ' . $quoting] = ['<link href=' . $quoting . '>'];
            $variants['css url ' . $quoting] = ['<style>.x { background: url(' . $quoting . '); }</style>'];
            $variants['css import ' . $quoting] = ['<style>@import ' . $quoting . ';</style>'];
        }

        return $variants;
    }

    public function testUnknownRouteIsStillAReal404ForAnonymousAndAuthenticatedRequests(): void
    {
        foreach ([false, true] as $authenticated) {
            try {
                $request = $authenticated ? $this->withSession(['isLoggedIn' => true]) : $this;
                $request->get('/wp03l-missing-route');
                self::fail('Expected a PageNotFoundException.');
            } catch (PageNotFoundException $exception) {
                self::assertSame(404, $exception->getCode());
            }
        }
    }

    public function testRoutingRemainsExplicitWithoutA404Override(): void
    {
        $routing = config(Routing::class);
        self::assertFalse($routing->autoRoute);
        self::assertNull($routing->override404);
    }

    /** @return array{string, int} */
    private function renderPageNotFound(string $message, string $path = '/missing'): array
    {
        $request = new IncomingRequest(
            config(App::class),
            new URI('http://localhost:8080' . $path),
            '',
            new UserAgent(),
        );
        $request->setHeader('Accept', 'text/html');
        $response = new Response(config(App::class));
        $handler = new ExceptionHandler(config(Exceptions::class));

        ob_start();
        $handler->handle(PageNotFoundException::forPageNotFound($message), $request, $response, 404, 1);

        return [(string) ob_get_clean(), $response->getStatusCode()];
    }

    private function assertLocalAssetGraph(string $html): void
    {
        foreach (array_merge($this->htmlAssetUrls($html), $this->cssAssetUrls($html)) as $url) {
            self::assertDoesNotMatchRegularExpression('#^(?:https?:)?//#i', $url, $url);
            $path = parse_url($url, PHP_URL_PATH);
            self::assertIsString($path, $url);
            self::assertStringStartsWith('/assets/', $path, $url);
            self::assertFileExists(PUBLICPATH . ltrim($path, '/'), $url);
        }
    }

    /** @return list<string> */
    private function htmlAssetUrls(string $html): array
    {
        preg_match_all('/<(?:script|img|link)\\b[^>]*>/i', $html, $tags);
        $urls = [];
        foreach ($tags[0] as $tag) {
            if (preg_match("/\\b(?:src|href)\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s\"'=<>`]+))/i", $tag, $match) !== 1) {
                continue;
            }
            $urls[] = $match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]);
        }

        return $urls;
    }

    /** @return list<string> */
    private function cssAssetUrls(string $html): array
    {
        preg_match_all("/(?:url\\(\\s*|@import\\s+)(?:\"([^\"]*)\"|'([^']*)'|([^\\s)\"';]+))/i", $html, $matches);

        $urls = [];
        foreach ($matches[0] as $index => $_) {
            $urls[] = $matches[1][$index] !== ''
                ? $matches[1][$index]
                : ($matches[2][$index] !== '' ? $matches[2][$index] : ($matches[3][$index] ?? ''));
        }

        return array_values(array_filter($urls, static fn (string $url): bool => $url !== ''));
    }
}
