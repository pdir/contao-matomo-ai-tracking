<?php

declare(strict_types=1);

/*
 * Aktualisiert config/ai_user_agents.php aus Matomos eigener Chatbot-Erkennung
 * (plugins/BotTracking/BotDetector.php).
 *
 * Matomo speichert bei recMode=1 nur Requests, die dieser BotDetector erkennt –
 * alle anderen verwirft es. Die Liste des Bundles soll deshalb genau diese
 * Kennungen enthalten, damit keine Requests umsonst an Matomo gehen.
 *
 * Aufruf:
 *   composer update-user-agents
 *   MATOMO_REF=5.14.2 composer update-user-agents   (bestimmte Version/Branch)
 */

$ref = getenv('MATOMO_REF') ?: '5.x-dev';
$sourceUrl = getenv('AI_BOT_SOURCE_URL') ?: "https://raw.githubusercontent.com/matomo-org/matomo/{$ref}/plugins/BotTracking/BotDetector.php";
$targetFile = getenv('AI_BOT_TARGET_FILE') ?: __DIR__.'/../config/ai_user_agents.php';

$source = @file_get_contents($sourceUrl, false, stream_context_create([
    'http' => [
        'header' => "User-Agent: pdir/contao-matomo-ai-tracking\r\n",
        'timeout' => 20,
    ],
]));

if (false === $source) {
    fwrite(STDERR, "Quelle konnte nicht geladen werden: {$sourceUrl}\n");
    exit(1);
}

// Den Inhalt nur als Text auswerten, niemals ausführen
if (1 !== preg_match('/\$aiAssistantPatterns\s*=\s*\[(.*?)\];/s', $source, $block)) {
    fwrite(STDERR, "\$aiAssistantPatterns nicht gefunden in: {$sourceUrl}\n");
    exit(1);
}

preg_match_all("/'([A-Za-z0-9._-]+)'\s*=>/", $block[1], $matches);

$needles = array_values(array_unique(array_map('strtolower', $matches[1])));
sort($needles, SORT_STRING);

if ([] === $needles) {
    fwrite(STDERR, "Keine User-Agents gefunden in: {$sourceUrl}\n");
    exit(1);
}

$content = "<?php\n\ndeclare(strict_types=1);\n\n"
    ."// Automatisch erzeugt aus {$sourceUrl}\n"
    ."// Entspricht den AI-Chatbots, die Matomo bei recMode=1 speichert.\n"
    ."// Nicht manuell bearbeiten – stattdessen composer update-user-agents ausführen\n"
    ."// bzw. MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS / MATOMO_AI_TRACKING_IGNORED_USER_AGENTS nutzen.\n\n"
    ."return [\n"
    .implode('', array_map(static fn (string $n): string => '    '.var_export($n, true).",\n", $needles))
    ."];\n";

if (is_file($targetFile) && file_get_contents($targetFile) === $content) {
    echo 'Liste ist aktuell ('.count($needles)." Einträge).\n";
    exit(0);
}

file_put_contents($targetFile, $content);
echo 'Liste aktualisiert: '.count($needles).' Einträge ('.implode(', ', $needles).").\n";
