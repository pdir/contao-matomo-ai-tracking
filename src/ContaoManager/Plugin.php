<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\HttpKernel\HttpCacheSubscriberPluginInterface;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Pdir\MatomoAiTrackingBundle\HttpCache\AiBotCacheSubscriber;
use Pdir\MatomoAiTrackingBundle\PdirMatomoAiTrackingBundle;
use Pdir\MatomoAiTrackingBundle\Tracking\AiBotTracker;

class Plugin implements BundlePluginInterface, HttpCacheSubscriberPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(PdirMatomoAiTrackingBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    /**
     * Wird von ContaoKernel::getHttpCache() aufgerufen, nachdem die .env-Dateien geladen wurden.
     */
    public function getHttpCacheSubscribers(): array
    {
        $config = TrackingConfig::fromEnvironment();

        if (!$config->isActive()) {
            return [];
        }

        return [new AiBotCacheSubscriber(new AiBotTracker($config))];
    }
}
