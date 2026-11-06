<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\HttpCache;

use FOS\HttpCache\SymfonyCache\CacheInvalidation;
use FOS\HttpCache\SymfonyCache\EventDispatchingHttpCache;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Pdir\MatomoAiTrackingBundle\HttpCache\AiBotCacheSubscriber;
use Pdir\MatomoAiTrackingBundle\Tracking\AiBotTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Stellt den Contao-HTTP-Cache nach: Symfony HttpCache + FOS EventDispatchingHttpCache,
 * genau wie ContaoCache. Die "App" simuliert Contao samt MatomoAiTrackingListener
 * in einer Multidomain-Installation.
 */
class AiBotCacheSubscriberTest extends TestCase
{
    private const CHATGPT_UA = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot';

    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private string $cacheDir;

    /**
     * @var list<\Closure>
     */
    private array $deferred = [];

    /**
     * @var list<array{url: string, body: array<string, string>}>
     */
    private array $sentToMatomo = [];

    private TestApp $app;

    private HttpCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/pdir-matomo-ai-'.bin2hex(random_bytes(4));

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            parse_str($options['body'], $body);
            $this->sentToMatomo[] = ['url' => $url, 'body' => $body];

            return new MockResponse('{"status":"success"}');
        });

        $tracker = new AiBotTracker(new TrackingConfig(enabled: true, source: 'contao-test'), $httpClient);

        $this->app = new TestApp();
        $this->cache = new TestContaoCache($this->app, new Store($this->cacheDir), options: ['debug' => true, 'trace_level' => 'full', 'terminate_on_cache_hit' => false]);
        $this->cache->addSubscriber(new AiBotCacheSubscriber($tracker, function (\Closure $task): void {
            $this->deferred[] = $task;
        }));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testTracksCacheMissAndCacheHit(): void
    {
        $first = $this->request('https://pdir.de/seite.html', self::CHATGPT_UA);
        $second = $this->request('https://pdir.de/seite.html', self::CHATGPT_UA);

        // Die App wurde nur einmal ausgeführt, der zweite Aufruf ist ein Cache-Treffer
        $this->assertSame(1, $this->app->calls);
        $this->assertStringContainsString('miss', (string) $first->headers->get('X-Symfony-Cache'));
        $this->assertStringContainsString('fresh', (string) $second->headers->get('X-Symfony-Cache'));

        // Nichts wird gesendet, bevor die Antwort raus ist
        $this->assertSame([], $this->sentToMatomo);
        $this->runDeferred();

        $this->assertCount(2, $this->sentToMatomo);

        foreach ($this->sentToMatomo as ['url' => $url, 'body' => $payload]) {
            $this->assertSame('https://matomo.pdir.de/matomo.php', $url);
            $this->assertSame('1', $payload['idsite']);
            $this->assertSame('1', $payload['rec']);
            $this->assertSame('1', $payload['recMode']);
            $this->assertSame('https://pdir.de/seite.html', $payload['url']);
            $this->assertSame('200', $payload['http_status']);
            $this->assertSame((string) \strlen(TestApp::CONTENT), $payload['bw_bytes']);
            $this->assertSame(self::CHATGPT_UA, $payload['ua']);
            $this->assertSame('contao-test', $payload['source']);
            $this->assertArrayHasKey('pf_srv', $payload);
            $this->assertArrayNotHasKey('cip', $payload);
        }
    }

    public function testMultidomainUsesTargetOfEachDomain(): void
    {
        $this->request('https://pdir.de/', self::CHATGPT_UA);
        $this->request('https://maklermodul.de/', self::CHATGPT_UA);
        $this->request('https://contao-themes.net/', self::CHATGPT_UA);

        // Cache-Treffer pro Domain
        $this->request('https://pdir.de/', self::CHATGPT_UA);
        $this->request('https://maklermodul.de/', self::CHATGPT_UA);
        $this->request('https://contao-themes.net/', self::CHATGPT_UA);

        $this->assertSame(3, $this->app->calls);
        $this->runDeferred();

        $sent = array_map(static fn (array $s): string => $s['url'].' #'.$s['body']['idsite'].' '.$s['body']['url'], $this->sentToMatomo);

        $this->assertSame([
            'https://matomo.pdir.de/matomo.php #1 https://pdir.de/',
            'https://stats.maklermodul.de/matomo.php #4 https://maklermodul.de/',
            'https://matomo.pdir.de/matomo.php #1 https://pdir.de/',
            'https://stats.maklermodul.de/matomo.php #4 https://maklermodul.de/',
        ], $sent);
    }

    public function testTracksBotOnPageCachedByHumanRequest(): void
    {
        $this->request('https://pdir.de/seite.html', self::BROWSER_UA);
        $response = $this->request('https://pdir.de/seite.html', self::CHATGPT_UA);

        $this->assertSame(1, $this->app->calls);
        $this->assertStringContainsString('fresh', (string) $response->headers->get('X-Symfony-Cache'));

        $this->runDeferred();

        $this->assertCount(1, $this->sentToMatomo);
        $this->assertSame('1', $this->sentToMatomo[0]['body']['idsite']);
    }

    public function testInternalHeadersAreNeverSentToClient(): void
    {
        $miss = $this->request('https://maklermodul.de/', self::BROWSER_UA);
        $hit = $this->request('https://maklermodul.de/', self::BROWSER_UA);

        foreach ([$miss, $hit] as $response) {
            $this->assertFalse($response->headers->has(AiBotCacheSubscriber::SITE_ID_HEADER));
            $this->assertFalse($response->headers->has(AiBotCacheSubscriber::TRACKING_URL_HEADER));
        }

        $this->runDeferred();
        $this->assertSame([], $this->sentToMatomo);
    }

    public function testIgnoresInvalidTrackingUrlHeader(): void
    {
        $this->app->targets['pdir.de'] = ['1', 'file:///etc/passwd'];

        $this->request('https://pdir.de/', self::CHATGPT_UA);
        $this->runDeferred();

        $this->assertSame([], $this->sentToMatomo);
    }

    public function testMarksForwardedRequests(): void
    {
        $this->request('https://pdir.de/', self::BROWSER_UA);

        $this->assertTrue($this->app->lastRequest?->attributes->get(AiBotCacheSubscriber::REQUEST_ATTRIBUTE));
    }

    public function testDoesNotTrackExcludedPaths(): void
    {
        $this->request('https://pdir.de/contao/login', self::CHATGPT_UA);
        $this->runDeferred();

        $this->assertSame([], $this->sentToMatomo);
    }

    private function request(string $uri, string $userAgent): Response
    {
        $request = Request::create($uri, server: ['HTTP_USER_AGENT' => $userAgent]);

        $response = $this->cache->handle($request);
        $this->cache->terminate($request, $response);

        return $response;
    }

    private function runDeferred(): void
    {
        foreach ($this->deferred as $task) {
            $task();
        }

        $this->deferred = [];
    }
}

/**
 * Wie ContaoCache, nur ohne Contao-spezifische Subscriber.
 */
class TestContaoCache extends HttpCache implements CacheInvalidation
{
    use EventDispatchingHttpCache;

    public function fetch(Request $request, $catch = false): Response
    {
        return parent::fetch($request, $catch);
    }
}

/**
 * Simuliert Contao samt MatomoAiTrackingListener im Cache-Modus: Jede Frontend-Antwort
 * eines aktivierten Startpunkts bekommt Site-ID und Matomo-URL als interne Header.
 * contao-themes.net ist nicht aktiviert und bekommt keine Header.
 */
class TestApp implements HttpKernelInterface
{
    public const CONTENT = '<html><body>Seite</body></html>';

    public int $calls = 0;

    /**
     * @var array<string, array{string, string}>
     */
    public array $targets = [
        'pdir.de' => ['1', 'https://matomo.pdir.de'],
        'maklermodul.de' => ['4', 'https://stats.maklermodul.de/'],
    ];

    public Request|null $lastRequest = null;

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        ++$this->calls;
        $this->lastRequest = $request;

        $response = new Response(self::CONTENT);
        $response->setSharedMaxAge(3600);

        if (isset($this->targets[$request->getHost()]) && $request->attributes->get(AiBotCacheSubscriber::REQUEST_ATTRIBUTE)) {
            [$siteId, $url] = $this->targets[$request->getHost()];
            $response->headers->set(AiBotCacheSubscriber::SITE_ID_HEADER, $siteId);
            $response->headers->set(AiBotCacheSubscriber::TRACKING_URL_HEADER, $url);
        }

        return $response;
    }
}
