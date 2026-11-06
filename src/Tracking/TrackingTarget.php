<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tracking;

/**
 * Wohin ein Request getrackt wird: Matomo-Installation und Site-ID.
 */
final class TrackingTarget
{
    public function __construct(
        public readonly int $siteId,
        public readonly string $trackingUrl,
    ) {
    }

    /**
     * Erzeugt ein Ziel nur, wenn Site-ID und eine gültige http(s)-URL vorhanden sind.
     */
    public static function create(int|string|null $siteId, string|null $trackingUrl): self|null
    {
        $siteId = (int) $siteId;
        $trackingUrl = trim((string) $trackingUrl);

        if ($siteId <= 0 || !self::isValidUrl($trackingUrl)) {
            return null;
        }

        return new self($siteId, $trackingUrl);
    }

    public function getEndpoint(): string
    {
        return rtrim($this->trackingUrl, '/').'/matomo.php';
    }

    private static function isValidUrl(string $url): bool
    {
        return false !== filter_var($url, FILTER_VALIDATE_URL)
            && \in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
