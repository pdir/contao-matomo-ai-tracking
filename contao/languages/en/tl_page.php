<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_page']['matomo_ai_legend'] = 'Matomo AI bot tracking';
$GLOBALS['TL_LANG']['tl_page']['matomoAiEnable'] = ['Enable AI bot tracking', 'Send requests from AI chatbots (e.g. ChatGPT-User, Claude-User) to this website root to Matomo. Crawlers such as GPTBot or ClaudeBot are not sent. As soon as one website root is enabled, only enabled website roots are tracked and MATOMO_SITE_ID is ignored.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiSiteId'] = ['Matomo site ID', 'Site ID of the website in Matomo.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrl'] = ['Matomo URL', 'Base URL of the Matomo installation without /matomo.php, e.g. https://matomo.example.com. Leave empty to use MATOMO_TRACKING_URL.'];
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlInvalid'] = 'Please enter an absolute URL starting with http:// or https://.';
$GLOBALS['TL_LANG']['tl_page']['matomoAiTrackingUrlMissing'] = 'Please enter a Matomo URL. MATOMO_TRACKING_URL is not set, so this website root would not be tracked without one.';
