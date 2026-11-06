<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tracking;

use Contao\CoreBundle\Routing\PageFinder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Ermittelt das Tracking-Ziel für einen Request.
 *
 * Modus "Startpunkte" – mindestens ein Startpunkt hat das AI-Bot-Tracking aktiviert:
 *   Getrackt wird nur auf aktivierten Startpunkten, mit deren Site-ID und
 *   optional eigener Matomo-URL (sonst MATOMO_TRACKING_URL). MATOMO_SITE_ID
 *   wird ignoriert.
 *
 * Modus "global" – kein Startpunkt aktiviert (z. B. Single-Domain):
 *   MATOMO_SITE_ID und MATOMO_TRACKING_URL gelten für alle Domains.
 *
 * Der Startpunkt wird über PageFinder::findRootPageForRequest() bestimmt: bei
 * normalen Seiten über das pageModel, bei robots.txt, sitemap.xml und eigenen
 * Routen über Hostname und Sprache.
 */
class TrackingTargetResolver implements ResetInterface
{
    private bool|null $rootPageMode = null;

    public function __construct(
        private readonly TrackingConfig $config,
        private readonly PageFinder $pageFinder,
        private readonly Connection $connection,
    ) {
    }

    public function resolve(Request $request): TrackingTarget|null
    {
        if (!$this->isRootPageMode()) {
            return TrackingTarget::create($this->config->defaultSiteId, $this->config->trackingUrl);
        }

        $rootPage = $this->pageFinder->findRootPageForRequest($request);

        if (null === $rootPage || !$rootPage->matomoAiEnable) {
            return null;
        }

        return TrackingTarget::create(
            $rootPage->matomoAiSiteId,
            $rootPage->matomoAiTrackingUrl ?: $this->config->trackingUrl,
        );
    }

    public function reset(): void
    {
        $this->rootPageMode = null;
    }

    private function isRootPageMode(): bool
    {
        if (null !== $this->rootPageMode) {
            return $this->rootPageMode;
        }

        try {
            $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_page WHERE type = 'root' AND matomoAiEnable = 1");
        } catch (DbalException) {
            // Spalte existiert noch nicht (contao:migrate nicht ausgeführt)
            $count = 0;
        }

        return $this->rootPageMode = $count > 0;
    }
}
