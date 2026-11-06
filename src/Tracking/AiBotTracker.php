<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tracking;

use Pdir\MatomoAiTrackingBundle\Config\TrackingConfig;
use Pdir\MatomoAiTrackingBundle\Detector\AiBotDetector;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gemeinsame Logik für den Cache-Subscriber und den Kernel-Listener:
 * entscheiden, ob getrackt wird, Payload bauen und an Matomo senden.
 *
 * Gesendete Felder entsprechen poldixd/laravel-matomo-ai-bot-tracking:
 * idsite, rec, recMode, url, http_status, bw_bytes, pf_srv, ua, source.
 * cip/cdt werden bewusst nicht gesendet (kein token_auth nötig).
 */
final class AiBotTracker
{
    private AiBotDetector|null $detector = null;

    public function __construct(
        private readonly TrackingConfig $config,
        private HttpClientInterface|null $httpClient = null,
    ) {
    }

    public function getConfig(): TrackingConfig
    {
        return $this->config;
    }

    public function shouldTrack(Request $request): bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        if (!$request->isMethod(Request::METHOD_GET) || $request->isXmlHttpRequest()) {
            return false;
        }

        // Pendant zu Laravels expectsJson(): reine API-/JSON-Aufrufe ignorieren
        if ('json' === $request->getPreferredFormat(null)) {
            return false;
        }

        $path = $request->getPathInfo();

        foreach ($this->config->excludedPaths as $excludedPath) {
            if (1 === preg_match('{'.$excludedPath.'}', $path)) {
                return false;
            }
        }

        return $this->getDetector()->isAiBot((string) $request->headers->get('User-Agent', ''));
    }

    /**
     * @return array<string, int|string>
     */
    public function createPayload(Request $request, Response $response, TrackingTarget $target): array
    {
        return [
            'idsite' => $target->siteId,
            'rec' => 1,
            'recMode' => 1,
            'url' => $request->getUri(),
            'http_status' => $response->getStatusCode(),
            'bw_bytes' => $this->getResponseSize($response),
            'pf_srv' => $this->getServerTimeMs($request),
            'ua' => (string) $request->headers->get('User-Agent'),
            'source' => $this->config->source,
        ];
    }

    /**
     * Sendet die Payload an Matomo.
     *
     * @param array<string, int|string> $payload
     *
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    public function send(TrackingTarget $target, array $payload): string|null
    {
        if (!$this->config->isActive()) {
            return null;
        }

        $this->httpClient ??= HttpClient::create();

        try {
            $response = $this->httpClient->request('POST', $target->getEndpoint(), [
                'body' => $payload,
                'timeout' => $this->config->timeout,
                'max_duration' => $this->config->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                return \sprintf('Matomo antwortete mit HTTP %d für %s (%s): %s', $statusCode, $payload['url'], $payload['ua'], substr($response->getContent(false), 0, 500));
            }
        } catch (ExceptionInterface $exception) {
            return \sprintf('Matomo nicht erreichbar für %s (%s): %s', $payload['url'], $payload['ua'], $exception->getMessage());
        }

        return null;
    }

    private function getDetector(): AiBotDetector
    {
        return $this->detector ??= new AiBotDetector($this->config->additionalUserAgents, $this->config->ignoredUserAgents);
    }

    private function getResponseSize(Response $response): int
    {
        if ($response->headers->has('Content-Length')) {
            return (int) $response->headers->get('Content-Length');
        }

        if ($response instanceof BinaryFileResponse) {
            return (int) $response->getFile()->getSize();
        }

        if ($response instanceof StreamedResponse) {
            return 0;
        }

        return \strlen((string) $response->getContent());
    }

    private function getServerTimeMs(Request $request): int
    {
        $start = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));

        return max(0, (int) round((microtime(true) - $start) * 1000));
    }
}
