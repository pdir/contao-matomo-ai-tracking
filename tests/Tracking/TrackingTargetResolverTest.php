<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\Tracking;

use Contao\CoreBundle\Routing\PageFinder;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTargetResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TrackingTargetResolverTest extends TestCase
{
    /**
     * pdir.de und maklermodul.de aktiviert, contao-themes.net nicht.
     */
    private const ROOT_PAGES = [
        'pdir.de' => ['matomoAiEnable' => true, 'matomoAiSiteId' => 1, 'matomoAiTrackingUrl' => ''],
        'maklermodul.de' => ['matomoAiEnable' => true, 'matomoAiSiteId' => 4, 'matomoAiTrackingUrl' => 'https://stats.maklermodul.de'],
        'contao-themes.net' => ['matomoAiEnable' => false, 'matomoAiSiteId' => 0, 'matomoAiTrackingUrl' => ''],
    ];

    public function testRootPageModeUsesSettingsOfEachRootPage(): void
    {
        $resolver = $this->createResolver(enabledRootPages: 2);

        $pdir = $resolver->resolve(Request::create('https://pdir.de/'));
        $this->assertSame(1, $pdir?->siteId);
        $this->assertSame('https://matomo.example.com', $pdir?->trackingUrl, 'Fallback auf MATOMO_TRACKING_URL');

        $makler = $resolver->resolve(Request::create('https://maklermodul.de/robots.txt'));
        $this->assertSame(4, $makler?->siteId);
        $this->assertSame('https://stats.maklermodul.de', $makler?->trackingUrl);

        $this->assertNull($resolver->resolve(Request::create('https://contao-themes.net/')));
    }

    public function testRootPageModeIgnoresDefaultSiteId(): void
    {
        $resolver = $this->createResolver(enabledRootPages: 2);

        // Unbekannte Domain: kein Startpunkt -> kein Tracking, auch nicht mit MATOMO_SITE_ID
        $this->assertNull($resolver->resolve(Request::create('https://unbekannt.de/')));
    }

    public function testRootPageModeWithoutAnyTrackingUrl(): void
    {
        $resolver = $this->createResolver(enabledRootPages: 2, defaultUrl: null);

        $this->assertNull($resolver->resolve(Request::create('https://pdir.de/')));
        $this->assertSame(4, $resolver->resolve(Request::create('https://maklermodul.de/'))?->siteId);
    }

    public function testGlobalModeUsesEnvironmentForAllDomains(): void
    {
        $resolver = $this->createResolver(enabledRootPages: 0);

        foreach (['https://pdir.de/', 'https://contao-themes.net/'] as $uri) {
            $target = $resolver->resolve(Request::create($uri));
            $this->assertSame(9, $target?->siteId);
            $this->assertSame('https://matomo.example.com', $target?->trackingUrl);
        }
    }

    public function testGlobalModeBeforeMigration(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException($this->createStub(InvalidFieldNameException::class));

        $resolver = new TrackingTargetResolver($this->createConfig(), $this->createPageFinder(), $connection);

        $this->assertSame(9, $resolver->resolve(Request::create('https://pdir.de/'))?->siteId);
    }

    public function testModeIsDeterminedOncePerRequestCycle(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturn('1');

        $resolver = new TrackingTargetResolver($this->createConfig(), $this->createPageFinder(), $connection);

        $resolver->resolve(Request::create('https://pdir.de/'));
        $resolver->resolve(Request::create('https://pdir.de/'));
        $resolver->reset();
        $resolver->resolve(Request::create('https://pdir.de/'));
    }

    private function createResolver(int $enabledRootPages, string|null $defaultUrl = 'https://matomo.example.com'): TrackingTargetResolver
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn((string) $enabledRootPages);

        return new TrackingTargetResolver($this->createConfig($defaultUrl), $this->createPageFinder(), $connection);
    }

    private function createConfig(string|null $defaultUrl = 'https://matomo.example.com'): TrackingConfig
    {
        return new TrackingConfig(enabled: true, trackingUrl: $defaultUrl, defaultSiteId: 9);
    }

    private function createPageFinder(): PageFinder
    {
        $pageFinder = $this->createStub(PageFinder::class);
        $pageFinder
            ->method('findRootPageForRequest')
            ->willReturnCallback(fn (Request $request): PageModel|null => isset(self::ROOT_PAGES[$request->getHost()]) ? $this->createPageModel(self::ROOT_PAGES[$request->getHost()]) : null)
        ;

        return $pageFinder;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createPageModel(array $data): PageModel
    {
        $page = $this->createStub(PageModel::class);
        $page->method('__get')->willReturnCallback(static fn (string $key): mixed => $data[$key] ?? null);
        $page->method('__isset')->willReturnCallback(static fn (string $key): bool => isset($data[$key]));

        return $page;
    }
}
