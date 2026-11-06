<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Pdir\MatomoAiTrackingBundle\ContaoManager\Plugin;
use Pdir\MatomoAiTrackingBundle\HttpCache\AiBotCacheSubscriber;
use Pdir\MatomoAiTrackingBundle\PdirMatomoAiTrackingBundle;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['MATOMO_AI_TRACKING_ENABLED'], $_ENV['MATOMO_AI_TRACKING_ENABLED']);
    }

    public function testRegistersBundleAfterContaoCore(): void
    {
        $bundles = (new Plugin())->getBundles($this->createStub(ParserInterface::class));

        $this->assertCount(1, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(PdirMatomoAiTrackingBundle::class, $bundles[0]->getName());
        $this->assertSame([ContaoCoreBundle::class], $bundles[0]->getLoadAfter());
    }

    public function testRegistersNoCacheSubscriberWhenDisabled(): void
    {
        $this->assertSame([], (new Plugin())->getHttpCacheSubscribers());
    }

    public function testRegistersCacheSubscriberWhenEnabled(): void
    {
        $_SERVER['MATOMO_AI_TRACKING_ENABLED'] = '1';

        $subscribers = (new Plugin())->getHttpCacheSubscribers();

        $this->assertCount(1, $subscribers);
        $this->assertInstanceOf(AiBotCacheSubscriber::class, $subscribers[0]);
    }
}
