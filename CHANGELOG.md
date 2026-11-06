# Changelog

[//]: <> (
Types of changes
    Added for new features.
    Changed for changes in existing functionality.
    Deprecated for soon-to-be removed features.
    Removed for now removed features.
    Fixed for any bug fixes.
    Security in case of vulnerabilities.
)

## [1.0.0](https://github.com/pdir/contao-matomo-ai-tracking/tree/1.0.0) – 2026-11-06

- [Added] Support Contao 5.3, 5.7 and 6.0 (PHP 8.2+, Contao 6 requires PHP 8.4+)
- [Added] Server-side tracking of AI chatbots for Matomo's "AI Chatbots" reports (Matomo 5.8+ with the BotTracking plugin): `ChatGPT-User`, `Claude-User`, `Perplexity-User`, `MistralAI-User`, `Gemini-Deep-Research`, `Google-NotebookLM`, `Google-GeminiNotebook`
- [Added] Crawlers such as GPTBot, ClaudeBot or PerplexityBot are deliberately not sent, because Matomo discards them at `recMode=1`
- [Added] Tracking of pages delivered by the Contao HTTP cache via a cache subscriber (`HttpCacheSubscriberPluginInterface`); the request to Matomo is sent after the response, so the bot does not wait
- [Added] Settings per website root (enable tracking, Matomo site ID, own Matomo URL) for multi-domain installations; changes take effect immediately, also for cached pages
- [Added] Global mode for single-domain installations via `MATOMO_SITE_ID` and `MATOMO_TRACKING_URL`
- [Added] Configuration via environment variables (`MATOMO_AI_TRACKING_ENABLED`, `MATOMO_AI_TRACKING_SOURCE`, `MATOMO_AI_TRACKING_TIMEOUT`, `MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS`, `MATOMO_AI_TRACKING_IGNORED_USER_AGENTS`, `MATOMO_AI_TRACKING_EXCLUDED_PATHS`)
- [Added] Validation in the backend: a Matomo URL is required at the website root if `MATOMO_TRACKING_URL` is not set; invalid excluded path patterns are ignored
- [Added] Script `composer update-user-agents` that takes the chatbot list from Matomo's `BotDetector.php`
- [Added] German and English translations
- [Added] PHPUnit tests incl. a replica of the Contao HTTP cache, GitHub Actions for Contao 5.3, 5.7 and 6.0 and a weekly check of Matomo's chatbot list
