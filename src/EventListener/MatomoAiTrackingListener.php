<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Pdir\MatomoAiTrackingBundle\HttpCache\AiBotCacheSubscriber;
use Pdir\MatomoAiTrackingBundle\Tracking\AiBotTracker;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTarget;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTargetResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Zwei Betriebsarten:
 *
 *  1. Hinter dem Contao-HTTP-Cache (Request ist vom AiBotCacheSubscriber markiert):
 *     Der Listener schreibt nur das Tracking-Ziel als interne Header in die Antwort.
 *     Getrackt wird im Cache, damit auch spätere Cache-Treffer erfasst werden.
 *
 *  2. Ohne HTTP-Cache (Debug-Modus, DISABLE_HTTP_CACHE=1):
 *     Der Listener baut die Payload in kernel.response und sendet sie in
 *     kernel.terminate, also nach der Auslieferung an den Bot.
 *
 * Cache-Invalidierung: Contao taggt jede Seite mit allen Seiten ihres Pfads
 * (PageTrailCacheTagsListener), also auch mit dem Startpunkt. Wird der Startpunkt
 * gespeichert, leert Contao dieses Tag samt Sitemap – geänderte Tracking-
 * Einstellungen greifen dadurch sofort.
 */
final class MatomoAiTrackingListener implements ResetInterface
{
    /**
     * @var array{TrackingTarget, array<string, int|string>}|null
     */
    private array|null $pending = null;

    public function __construct(
        private readonly AiBotTracker $tracker,
        private readonly TrackingTargetResolver $targetResolver,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    #[AsEventListener(KernelEvents::RESPONSE, priority: -1024)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->tracker->getConfig()->isActive()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            return;
        }

        $target = $this->targetResolver->resolve($request);

        if (null === $target) {
            return;
        }

        if (true === $request->attributes->get(AiBotCacheSubscriber::REQUEST_ATTRIBUTE)) {
            // Auch für Nicht-Bots setzen: Die Antwort kann gecacht und später an einen Bot ausgeliefert werden.
            $headers = $event->getResponse()->headers;
            $headers->set(AiBotCacheSubscriber::SITE_ID_HEADER, (string) $target->siteId);
            $headers->set(AiBotCacheSubscriber::TRACKING_URL_HEADER, $target->trackingUrl);

            return;
        }

        if ($this->tracker->shouldTrack($request)) {
            $this->pending = [$target, $this->tracker->createPayload($request, $event->getResponse(), $target)];
        }
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (null === $this->pending) {
            return;
        }

        [$target, $payload] = $this->pending;
        $this->pending = null;

        if (null !== ($error = $this->tracker->send($target, $payload))) {
            $this->logger?->warning('Matomo AI-Bot-Tracking fehlgeschlagen: '.$error);
        }
    }

    public function reset(): void
    {
        $this->pending = null;
    }
}
