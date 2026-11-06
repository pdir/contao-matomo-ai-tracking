<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_page']['matomo_ai_legend'] = 'Matomo AI-Bot-Tracking';
$GLOBALS['TL_LANG']['tl_page']['matomoAiEnable'] = ['AI-Bot-Tracking aktivieren', 'Zugriffe von AI-Chatbots (z. B. ChatGPT-User, Claude-User) auf diesen Startpunkt an Matomo senden. Crawler wie GPTBot oder ClaudeBot werden nicht gesendet. Sobald ein Startpunkt aktiviert ist, werden nur noch aktivierte Startpunkte getrackt und MATOMO_SITE_ID wird ignoriert.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiSiteId'] = ['Matomo Site-ID', 'Site-ID der Website in Matomo.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrl'] = ['Matomo-URL', 'Basis-URL der Matomo-Installation ohne /matomo.php, z. B. https://matomo.example.com. Leer lassen, um MATOMO_TRACKING_URL zu verwenden.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlInvalid'] = 'Bitte geben Sie eine absolute URL mit http:// oder https:// ein.';
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlMissing'] = 'Bitte geben Sie eine Matomo-URL ein. MATOMO_TRACKING_URL ist nicht gesetzt, ohne URL würde dieser Startpunkt nicht getrackt.';
