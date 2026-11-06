<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Config;

/**
 * Konfiguration aus Umgebungsvariablen (.env.local).
 *
 * Bewusst KEINE Bundle-Konfiguration: Der Subscriber im Contao-HTTP-Cache läuft,
 * bevor der Symfony-Container existiert, und kann deshalb nur Umgebungsvariablen
 * lesen – genau wie ContaoCache selbst (COOKIE_ALLOW_LIST etc.).
 *
 *   MATOMO_AI_TRACKING_ENABLED=1
 *   MATOMO_TRACKING_URL=https://matomo.example.com   (Standard, pro Startpunkt überschreibbar)
 *   MATOMO_SITE_ID=1                                 (nur ohne aktivierte Startpunkte)
 *   MATOMO_AI_TRACKING_SOURCE=contao
 *   MATOMO_AI_TRACKING_TIMEOUT=2
 *   MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS=MyAgent,OtherAgent
 *   MATOMO_AI_TRACKING_IGNORED_USER_AGENTS=mistralai-user
 *   MATOMO_AI_TRACKING_EXCLUDED_PATHS=^/contao(/|$),^/_(wdt|profiler|contao|fragment)
 */
final class TrackingConfig
{
    public const DEFAULT_EXCLUDED_PATHS = ['^/contao(/|$)', '^/_(wdt|profiler|contao|fragment)'];

    /**
     * @param list<string> $additionalUserAgents
     * @param list<string> $ignoredUserAgents
     * @param list<string> $excludedPaths
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string|null $trackingUrl = null,
        public readonly int|null $defaultSiteId = null,
        public readonly string $source = 'contao',
        public readonly float $timeout = 2.0,
        public readonly array $additionalUserAgents = [],
        public readonly array $ignoredUserAgents = [],
        public readonly array $excludedPaths = self::DEFAULT_EXCLUDED_PATHS,
    ) {
    }

    /**
     * @param array<string, mixed>|null $env null = $_SERVER + $_ENV
     */
    public static function fromEnvironment(array|null $env = null): self
    {
        $env ??= $_SERVER + $_ENV;

        $string = static fn (string $key): string|null => isset($env[$key]) && '' !== trim((string) $env[$key]) ? trim((string) $env[$key]) : null;

        $csv = static fn (string $key): array => array_values(array_filter(
            array_map('trim', explode(',', (string) ($env[$key] ?? ''))),
            static fn (string $value): bool => '' !== $value,
        ));

        $siteId = (int) $string('MATOMO_SITE_ID');
        $timeout = (float) ($string('MATOMO_AI_TRACKING_TIMEOUT') ?? 2.0);
        // Ungültige Muster verwerfen, sonst löst jeder Request eine Warnung aus
        $excludedPaths = array_values(array_filter(
            $csv('MATOMO_AI_TRACKING_EXCLUDED_PATHS'),
            static fn (string $pattern): bool => self::isValidPathPattern($pattern),
        ));

        return new self(
            enabled: filter_var($string('MATOMO_AI_TRACKING_ENABLED') ?? false, FILTER_VALIDATE_BOOL),
            trackingUrl: $string('MATOMO_TRACKING_URL'),
            defaultSiteId: $siteId > 0 ? $siteId : null,
            source: $string('MATOMO_AI_TRACKING_SOURCE') ?? 'contao',
            timeout: $timeout > 0 ? $timeout : 2.0,
            additionalUserAgents: $csv('MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS'),
            ignoredUserAgents: $csv('MATOMO_AI_TRACKING_IGNORED_USER_AGENTS'),
            excludedPaths: [] !== $excludedPaths ? $excludedPaths : self::DEFAULT_EXCLUDED_PATHS,
        );
    }

    /**
     * Globaler Schalter. Die Matomo-URL ist keine Voraussetzung mehr, weil sie
     * auch pro Startpunkt gesetzt werden kann.
     */
    public function isActive(): bool
    {
        return $this->enabled;
    }

    /**
     * Prüft ein Muster aus MATOMO_AI_TRACKING_EXCLUDED_PATHS (Delimiter: {}).
     */
    public static function isValidPathPattern(string $pattern): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return false !== preg_match('{'.$pattern.'}', '');
        } finally {
            restore_error_handler();
        }
    }
}
