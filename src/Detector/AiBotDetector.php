<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Detector;

/**
 * Erkennt AI-Chatbots anhand des User-Agents.
 *
 * Die Standardliste (config/ai_user_agents.php) entspricht den Chatbots, die
 * Matomo bei recMode=1 speichert (plugins/BotTracking/BotDetector.php). Alle
 * anderen Bots würde Matomo verwerfen. Erzeugt mit composer update-user-agents.
 *
 * Anders als ein einfaches str_contains() prüft der Detector auf Wortgrenzen,
 * damit kurze zusätzliche Kennungen (MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS)
 * nicht in fremden Tokens anschlagen (z. B. "code" in "VSCode").
 */
final class AiBotDetector
{
    private readonly ?string $pattern;

    /**
     * @param list<string>      $additionalUserAgents
     * @param list<string>      $ignoredUserAgents
     * @param list<string>|null $userAgents           null = Standardliste laden
     */
    public function __construct(
        array $additionalUserAgents = [],
        array $ignoredUserAgents = [],
        ?array $userAgents = null,
    ) {
        $userAgents ??= require \dirname(__DIR__, 2).'/config/ai_user_agents.php';

        $normalize = static fn (string $ua): string => strtolower(trim($ua));

        $needles = array_diff(
            array_map($normalize, [...$userAgents, ...$additionalUserAgents]),
            array_map($normalize, $ignoredUserAgents),
            [''],
        );

        // Längere Kennungen zuerst, damit z. B. "claude-user" vor "claude" greift.
        $needles = array_values(array_unique($needles));
        usort($needles, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        $this->pattern = [] === $needles
            ? null
            : '/(?<![a-z0-9])(?:'.implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), $needles)).')(?![a-z0-9])/i';
    }

    public function isAiBot(string $userAgent): bool
    {
        return null !== $this->match($userAgent);
    }

    /**
     * Liefert die erkannte Kennung (z. B. "chatgpt-user") oder null.
     */
    public function match(string $userAgent): ?string
    {
        if (null === $this->pattern || '' === trim($userAgent)) {
            return null;
        }

        return 1 === preg_match($this->pattern, $userAgent, $matches) ? strtolower($matches[0]) : null;
    }
}
