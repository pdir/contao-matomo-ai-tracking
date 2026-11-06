<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\HttpCache;

use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\Events;
use Pdir\MatomoAiTrackingBundle\Tracking\AiBotTracker;
use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTarget;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Subscriber für den Contao-HTTP-Cache (ContaoCache), registriert über
 * Plugin::getHttpCacheSubscribers().
 *
 * Bei einem Cache-Treffer startet Contao nicht, und weder kernel.response noch
 * kernel.terminate werden ausgelöst. POST_HANDLE dagegen feuert für JEDEN
 * Haupt-Request – Treffer wie Fehlschläge. Hier wird deshalb getrackt.
 *
 * Das Tracking-Ziel (Site-ID und Matomo-URL des Startpunkts) kann im Cache nicht
 * aus der Datenbank gelesen werden. Der Kernel-Listener schreibt es daher als
 * interne Header in jede Frontend-Antwort. Die Header werden mitgecacht und hier
 * vor der Auslieferung entfernt. Fehlen sie, wird nicht getrackt (Startpunkt
 * nicht aktiviert).
 */
final class AiBotCacheSubscriber implements EventSubscriberInterface
{
    public const SITE_ID_HEADER = 'X-Pdir-Matomo-Ai-Site-Id';

    public const TRACKING_URL_HEADER = 'X-Pdir-Matomo-Ai-Url';

    /**
     * Markiert Requests, die der Cache an Contao weiterreicht. Der Kernel-Listener
     * sendet dann nicht selbst, sondern überlässt das diesem Subscriber.
     */
    public const REQUEST_ATTRIBUTE = '_pdir_matomo_ai_behind_cache';

    /**
     * @var \Closure(\Closure): void
     */
    private readonly \Closure $defer;

    /**
     * @param (\Closure(\Closure): void)|null $defer Führt die Aufgabe nach der
     *                                               Auslieferung aus (Standard: register_shutdown_function, also nach
     *                                               Response::send() bzw. fastcgi_finish_request())
     */
    public function __construct(
        private readonly AiBotTracker $tracker,
        \Closure|null $defer = null,
    ) {
        $this->defer = $defer ?? static function (\Closure $task): void {
            register_shutdown_function($task);
        };
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_FORWARD => 'onPreForward',
            Events::POST_HANDLE => ['onPostHandle', -1024],
        ];
    }

    public function onPreForward(CacheEvent $event): void
    {
        $event->getRequest()->attributes->set(self::REQUEST_ATTRIBUTE, true);
    }

    public function onPostHandle(CacheEvent $event): void
    {
        $response = $event->getResponse();

        if (null === $response) {
            return;
        }

        // Header immer entfernen, auch bei Sub-Requests (ESI) und Nicht-Bots
        $target = TrackingTarget::create(
            $response->headers->get(self::SITE_ID_HEADER),
            $response->headers->get(self::TRACKING_URL_HEADER),
        );

        $response->headers->remove(self::SITE_ID_HEADER);
        $response->headers->remove(self::TRACKING_URL_HEADER);

        if (null === $target || HttpKernelInterface::MAIN_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->tracker->shouldTrack($request)) {
            return;
        }

        $payload = $this->tracker->createPayload($request, $response, $target);
        $tracker = $this->tracker;

        ($this->defer)(static function () use ($tracker, $target, $payload): void {
            if (null !== ($error = $tracker->send($target, $payload))) {
                // Im Cache gibt es noch keinen Logger aus dem Container
                error_log('[pdir/contao-matomo-ai-tracking] '.$error);
            }
        });
    }
}
