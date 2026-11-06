<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\Config;

use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use PHPUnit\Framework\TestCase;

class TrackingConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = TrackingConfig::fromEnvironment([]);

        $this->assertFalse($config->enabled);
        $this->assertFalse($config->isActive());
        $this->assertNull($config->trackingUrl);
        $this->assertNull($config->defaultSiteId);
        $this->assertSame('contao', $config->source);
        $this->assertSame(2.0, $config->timeout);
        $this->assertSame(TrackingConfig::DEFAULT_EXCLUDED_PATHS, $config->excludedPaths);
    }

    public function testReadsEnvironment(): void
    {
        $config = TrackingConfig::fromEnvironment([
            'MATOMO_AI_TRACKING_ENABLED' => 'true',
            'MATOMO_TRACKING_URL' => 'https://matomo.example.com',
            'MATOMO_SITE_ID' => '3',
            'MATOMO_AI_TRACKING_SOURCE' => 'pool.jetzt',
            'MATOMO_AI_TRACKING_TIMEOUT' => '1.5',
            'MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS' => 'MyAgent, OtherAgent',
            'MATOMO_AI_TRACKING_IGNORED_USER_AGENTS' => 'applebot,,facebookexternalhit',
            'MATOMO_AI_TRACKING_EXCLUDED_PATHS' => '^/api/',
        ]);

        $this->assertTrue($config->isActive());
        $this->assertSame('https://matomo.example.com', $config->trackingUrl);
        $this->assertSame(3, $config->defaultSiteId);
        $this->assertSame('pool.jetzt', $config->source);
        $this->assertSame(1.5, $config->timeout);
        $this->assertSame(['MyAgent', 'OtherAgent'], $config->additionalUserAgents);
        $this->assertSame(['applebot', 'facebookexternalhit'], $config->ignoredUserAgents);
        $this->assertSame(['^/api/'], $config->excludedPaths);
    }

    public function testIgnoresInvalidExcludedPaths(): void
    {
        $config = TrackingConfig::fromEnvironment(['MATOMO_AI_TRACKING_EXCLUDED_PATHS' => '^/api/,^/(broken,^/foo}bar']);

        $this->assertSame(['^/api/'], $config->excludedPaths);
    }

    public function testFallsBackToDefaultsIfAllExcludedPathsAreInvalid(): void
    {
        $config = TrackingConfig::fromEnvironment(['MATOMO_AI_TRACKING_EXCLUDED_PATHS' => '^/(broken']);

        $this->assertSame(TrackingConfig::DEFAULT_EXCLUDED_PATHS, $config->excludedPaths);
    }

    public function testActiveWithoutTrackingUrl(): void
    {
        // Die Matomo-URL kann auch pro Startpunkt gesetzt werden
        $this->assertTrue(TrackingConfig::fromEnvironment(['MATOMO_AI_TRACKING_ENABLED' => '1'])->isActive());
        $this->assertFalse(TrackingConfig::fromEnvironment(['MATOMO_AI_TRACKING_ENABLED' => '0'])->isActive());
    }
}
