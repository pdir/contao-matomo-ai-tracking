<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Pdir\MatomoAiTrackingBundle\EventListener\MatomoAiTrackingListener;
use Pdir\MatomoAiTrackingBundle\HttpCache\AiBotCacheSubscriber;
use Pdir\MatomoAiTrackingBundle\Tracking\AiBotTracker;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTarget;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTargetResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MatomoAiTrackingListenerTest extends TestCase
{
    private const CLAUDE_UA = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)';

    /**
     * @var list<array{url: string, body: array<string, string>}>
     */
    private array $sentToMatomo = [];

    public function testBehindCacheOnlySetsTargetHeaders(): void
    {
        $listener = $this->createListener();

        [$response] = $this->dispatch($listener, $this->createRequest('Mozilla/5.0 Chrome/130.0', behindCache: true));

        $this->assertSame('5', $response->headers->get(AiBotCacheSubscriber::SITE_ID_HEADER));
        $this->assertSame('https://stats.maklermodul.de', $response->headers->get(AiBotCacheSubscriber::TRACKING_URL_HEADER));
        $this->assertSame([], $this->sentToMatomo);
    }

    public function testBehindCacheDoesNotSendForBots(): void
    {
        $this->dispatch($this->createListener(), $this->createRequest(self::CLAUDE_UA, behindCache: true));

        // Das übernimmt der AiBotCacheSubscriber
        $this->assertSame([], $this->sentToMatomo);
    }

    public function testWithoutCacheSendsOnTerminateToTarget(): void
    {
        [$response] = $this->dispatch($this->createListener(), $this->createRequest(self::CLAUDE_UA));

        $this->assertFalse($response->headers->has(AiBotCacheSubscriber::SITE_ID_HEADER));
        $this->assertCount(1, $this->sentToMatomo);
        $this->assertSame('https://stats.maklermodul.de/matomo.php', $this->sentToMatomo[0]['url']);
        $this->assertSame('5', $this->sentToMatomo[0]['body']['idsite']);
        $this->assertSame('1', $this->sentToMatomo[0]['body']['recMode']);
        $this->assertSame(self::CLAUDE_UA, $this->sentToMatomo[0]['body']['ua']);
    }

    public function testDoesNothingForDisabledRootPage(): void
    {
        $listener = $this->createListener(target: null);

        [$response] = $this->dispatch($listener, $this->createRequest(self::CLAUDE_UA, behindCache: true));
        $this->dispatch($listener, $this->createRequest(self::CLAUDE_UA));

        $this->assertFalse($response->headers->has(AiBotCacheSubscriber::SITE_ID_HEADER));
        $this->assertSame([], $this->sentToMatomo);
    }

    public function testIgnoresHumansWithoutCache(): void
    {
        $this->dispatch($this->createListener(), $this->createRequest('Mozilla/5.0 Chrome/130.0'));

        $this->assertSame([], $this->sentToMatomo);
    }

    public function testIgnoresBackendRequests(): void
    {
        [$response] = $this->dispatch($this->createListener(backend: true), $this->createRequest(self::CLAUDE_UA, behindCache: true));

        $this->assertFalse($response->headers->has(AiBotCacheSubscriber::SITE_ID_HEADER));
        $this->assertSame([], $this->sentToMatomo);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        [$response] = $this->dispatch($this->createListener(enabled: false), $this->createRequest(self::CLAUDE_UA, behindCache: true));

        $this->assertFalse($response->headers->has(AiBotCacheSubscriber::SITE_ID_HEADER));
        $this->assertSame([], $this->sentToMatomo);
    }

    private function createListener(TrackingTarget|null|false $target = false, bool $backend = false, bool $enabled = true): MatomoAiTrackingListener
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            parse_str($options['body'], $body);
            $this->sentToMatomo[] = ['url' => $url, 'body' => $body];

            return new MockResponse('{"status":"success"}');
        });

        $tracker = new AiBotTracker(new TrackingConfig(enabled: $enabled), $httpClient);

        $resolver = $this->createStub(TrackingTargetResolver::class);
        $resolver->method('resolve')->willReturn(false === $target ? new TrackingTarget(5, 'https://stats.maklermodul.de') : $target);

        $scopeMatcher = $this->createStub(ScopeMatcher::class);
        $scopeMatcher->method('isBackendRequest')->willReturn($backend);

        return new MatomoAiTrackingListener($tracker, $resolver, $scopeMatcher);
    }

    private function createRequest(string $userAgent, bool $behindCache = false): Request
    {
        $request = Request::create('https://maklermodul.de/seite.html', server: ['HTTP_USER_AGENT' => $userAgent]);

        if ($behindCache) {
            $request->attributes->set(AiBotCacheSubscriber::REQUEST_ATTRIBUTE, true);
        }

        return $request;
    }

    /**
     * @return array{Response}
     */
    private function dispatch(MatomoAiTrackingListener $listener, Request $request): array
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response('<html></html>');

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $listener->onKernelTerminate(new TerminateEvent($kernel, $request, $response));

        return [$response];
    }
}
