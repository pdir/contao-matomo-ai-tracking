<?php

declare(strict_types=1);

// Automatisch erzeugt aus https://raw.githubusercontent.com/matomo-org/matomo/5.x-dev/plugins/BotTracking/BotDetector.php
// Entspricht den AI-Chatbots, die Matomo bei recMode=1 speichert.
// Nicht manuell bearbeiten – stattdessen composer update-user-agents ausführen
// bzw. MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS / MATOMO_AI_TRACKING_IGNORED_USER_AGENTS nutzen.

return [
    'chatgpt-user',
    'claude-user',
    'gemini-deep-research',
    'google-gemininotebook',
    'google-notebooklm',
    'mistralai-user',
    'perplexity-user',
];
