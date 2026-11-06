# Contao Matomo AI Tracking

[![CI](https://github.com/pdir/contao-matomo-ai-tracking/actions/workflows/ci.yml/badge.svg)](https://github.com/pdir/contao-matomo-ai-tracking/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Erfasst Zugriffe von AI-Chatbots wie ChatGPT, Claude oder Perplexity
serverseitig für Matomos Berichte „AI Chatbots“. Diese Bots führen kein
JavaScript aus und tauchen daher im normalen Matomo-Tracking nicht auf.

- Auch Seiten, die der Contao-HTTP-Cache ausliefert, werden erfasst.
- Multidomain: Tracking pro Startpunkt ein- und ausschaltbar, mit eigener
  Site-ID und eigener Matomo-Installation.
- Der Chatbot wartet nie auf Matomo. Gesendet wird erst, nachdem die Antwort
  ausgeliefert ist.
- Es werden keine Cookies gesetzt und keine IP-Adressen übertragen. Getrackt
  werden ausschließlich Requests von AI-Chatbots, nicht von Menschen.

Änderungen stehen im [CHANGELOG](CHANGELOG.md).

## Voraussetzungen

- Contao 5.3, 5.7 oder 6.0 (Managed Edition)
- PHP 8.2+ (Contao 6 selbst setzt PHP 8.4+ voraus)
- Matomo **5.8.0+** mit aktiviertem Plugin BotTracking (AI-Chatbot-Tracking)

## Erfasste Chatbots

Getrackt werden genau die AI-Chatbots, die Matomo selbst erkennt
(`plugins/BotTracking/BotDetector.php`):

| User-Agent | Seit Matomo |
|---|---|
| `ChatGPT-User` | 5.8.0 |
| `Claude-User` | 5.8.0 |
| `Perplexity-User` | 5.8.0 |
| `MistralAI-User` | 5.8.0 |
| `Gemini-Deep-Research` | 5.8.0 |
| `Google-NotebookLM` | 5.8.0 |
| `Google-GeminiNotebook` | 5.14 (wird als Google-NotebookLM gezählt) |

Crawler wie GPTBot, ClaudeBot, PerplexityBot oder Googlebot werden bewusst
**nicht** gesendet. Matomo speichert bei `recMode=1` nur Requests, die sein
`BotDetector` erkennt, und verwirft alle anderen ohne Fehlermeldung. Gerade
Crawler rufen sehr viele Seiten ab, und jeder Request an Matomo wäre umsonst.

Unterstützt eine spätere Matomo-Version weitere Chatbots, lassen sie sich über
`MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS` ergänzen, bis das Bundle
aktualisiert ist. Bei älteren Matomo-Versionen als 5.14 wird
`Google-GeminiNotebook` von Matomo verworfen.

## Installation

```bash
composer require pdir/contao-matomo-ai-tracking
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear --env=prod
```

`cache:clear` leert auch den HTTP-Cache. Nötig ist das, weil Seiten, die vor
der Installation gecacht wurden, noch kein Tracking-Ziel enthalten (siehe
unten). Bis sie neu erzeugt werden, würden Zugriffe auf sie nicht erfasst.

Danach die Umgebungsvariablen setzen und bei Multidomain-Installationen die
Startpunkte einrichten (siehe [Konfiguration](#konfiguration)).

## Konfiguration

### Umgebungsvariablen

Globale Einstellungen erfolgen über Umgebungsvariablen, z. B. in `.env.local`.
Eine Bundle-Konfiguration in `config/config.yaml` gibt es bewusst nicht: Der
HTTP-Cache läuft, bevor der Symfony-Container existiert, und kann nur
Umgebungsvariablen lesen.

```dotenv
# Globaler Schalter (Pflicht)
MATOMO_AI_TRACKING_ENABLED=1

# Standard-Matomo-Installation, ohne /matomo.php
# (optional, wenn jeder aktivierte Startpunkt eine eigene Matomo-URL hat)
MATOMO_TRACKING_URL=https://matomo.example.com

# Site-ID für alle Domains – gilt NUR, solange kein Startpunkt aktiviert ist
MATOMO_SITE_ID=1

# Optional
MATOMO_AI_TRACKING_SOURCE=contao
MATOMO_AI_TRACKING_TIMEOUT=2
MATOMO_AI_TRACKING_ADDITIONAL_USER_AGENTS=NewChatbot-User     # nur sinnvoll, wenn Matomo ihn erkennt
MATOMO_AI_TRACKING_IGNORED_USER_AGENTS=mistralai-user         # einzelne Chatbots nicht tracken
MATOMO_AI_TRACKING_EXCLUDED_PATHS=^/contao(/|$),^/_(wdt|profiler|contao|fragment)
```

`MATOMO_AI_TRACKING_EXCLUDED_PATHS` enthält reguläre Ausdrücke ohne Delimiter,
getrennt durch Kommas. Ungültige Muster werden ignoriert. Ist kein gültiges
Muster übrig, gelten die Standardwerte.

Nach Änderungen an `.env.local` bei Bedarf `composer dump-env prod` erneut
ausführen, falls `.env.local.php` verwendet wird.

### Einstellungen am Startpunkt (Multidomain)

**Seitenstruktur → Startpunkt → Matomo AI-Bot-Tracking**

| Feld | Bedeutung |
|---|---|
| AI-Bot-Tracking aktivieren | Nur aktivierte Startpunkte werden getrackt. |
| Matomo Site-ID | Pflichtfeld, Site-ID in Matomo. |
| Matomo-URL | Optional. Leer = `MATOMO_TRACKING_URL`. Nur absolute `http(s)`-URLs. Pflicht, wenn `MATOMO_TRACKING_URL` nicht gesetzt ist. |

Das Bundle arbeitet in einem von zwei Modi:

- **Startpunkte:** Mindestens ein Startpunkt ist aktiviert. Getrackt werden nur
  aktivierte Startpunkte, jeweils mit eigener Site-ID und eigener oder
  Standard-Matomo-URL. `MATOMO_SITE_ID` wird ignoriert. Nicht aktivierte
  Startpunkte und unbekannte Domains werden nicht getrackt.
- **Global:** Kein Startpunkt ist aktiviert. `MATOMO_SITE_ID` und
  `MATOMO_TRACKING_URL` gelten für alle Domains. Gedacht für
  Single-Domain-Installationen.

Beispiel mit drei Domains, von denen zwei getrackt werden:

| Startpunkt | Aktiv | Site-ID | Matomo-URL |
|---|---|---|---|
| pdir.de | ✅ | 1 | (leer → `MATOMO_TRACKING_URL`) |
| maklermodul.de | ✅ | 4 | `https://stats.maklermodul.de` |
| contao-themes.net | ❌ | | |

Der Startpunkt wird über `PageFinder::findRootPageForRequest()` bestimmt. Bei
normalen Seiten geschieht das über die aufgerufene Seite, bei `robots.txt`,
`sitemap.xml` und eigenen Routen über Hostname und Sprache. Auch diese Aufrufe
landen deshalb in der richtigen Matomo-Site.

Geänderte Einstellungen greifen sofort, auch für bereits gecachte Seiten.
Contao taggt jede Seite mit allen Seiten ihres Pfads, also auch mit dem
Startpunkt. Beim Speichern des Startpunkts leert Contao dieses Tag und die
Sitemap der Domain.

## Prüfen, ob das Tracking funktioniert

1. Eine Seite mit dem User-Agent eines unterstützten Chatbots abrufen:

   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' \
     -A 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot' \
     https://www.example.com/
   ```

2. In Matomo für die passende Website die AI-Chatbot-Berichte öffnen. Der
   Abruf erscheint mit dem Bot-Namen `ChatGPT-User` und dem HTTP-Status. Je
   nach Archivierungs-Einstellung von Matomo kann das etwas dauern.
3. Taucht nichts auf, die Fehlerprotokolle prüfen:
   - mit HTTP-Cache: PHP-Error-Log, Einträge mit `[pdir/contao-matomo-ai-tracking]`;
   - ohne HTTP-Cache: Contao-Systemlog.

Häufige Ursachen:

- `MATOMO_AI_TRACKING_ENABLED` ist nicht gesetzt.
- Der Startpunkt ist nicht aktiviert.
- Ein anderer Startpunkt ist aktiviert, deshalb wird `MATOMO_SITE_ID` ignoriert.
- Seiten liegen noch aus der Zeit vor der Installation im Cache.
- Das Matomo-Plugin BotTracking ist deaktiviert.

## Funktionsweise

Ein Request wird getrackt, wenn alles Folgende zutrifft:

- Er ist ein Haupt-Request mit der Methode `GET`, kein XHR- und kein JSON-Request.
- Er gehört nicht zum Backend und nicht zu einem der ausgeschlossenen Pfade.
- Der User-Agent enthält eine bekannte AI-Bot-Kennung. Geprüft wird auf Wortgrenzen.

Gesendete Parameter: `idsite`, `rec=1`, `recMode=1`, `url`, `http_status`,
`bw_bytes`, `pf_srv`, `ua`, `source`. `cip` und `cdt` werden nicht gesendet,
deshalb ist kein `token_auth` nötig.

### Mit Contao-HTTP-Cache (Produktivbetrieb)

Bei einem Cache-Treffer startet Contao nicht, und es werden weder
`kernel.response` noch `kernel.terminate` ausgelöst. Deshalb registriert das
Bundle über `HttpCacheSubscriberPluginInterface` einen Subscriber direkt im
`ContaoCache`:

1. **`PRE_FORWARD`:** Reicht der Cache einen Request an Contao weiter, markiert
   der Subscriber ihn.
2. **Kernel-Listener:** Bei markierten Requests schreibt er das Tracking-Ziel
   des Startpunkts als interne Header `X-Pdir-Matomo-Ai-Site-Id` und
   `X-Pdir-Matomo-Ai-Url` in jede Frontend-Antwort. Die Header werden zusammen
   mit der Seite gecacht. Nicht aktivierte Startpunkte bekommen keine Header.
3. **`POST_HANDLE`:** Läuft bei Cache-Treffern und -Fehlschlägen. Der
   Subscriber liest die Header, entfernt sie vor der Auslieferung und prüft den
   User-Agent. Den Versand an Matomo plant er mit `register_shutdown_function()`
   ein. Header mit ungültiger Site-ID oder einer URL ohne `http(s)` werden
   ignoriert.

Der Versand läuft erst nach `Response::send()`. Dort ruft Symfony unter PHP-FPM
`fastcgi_finish_request()` auf, der Bot wartet also nicht auf Matomo. Fehler
beim Versand landen im PHP-Error-Log, weil es im Cache noch keinen Logger gibt.

`pf_srv` enthält bei Cache-Treffern die Antwortzeit des Caches, also die Zeit,
die der Bot tatsächlich gewartet hat.

### Ohne HTTP-Cache (Debug-Modus, `DISABLE_HTTP_CACHE=1`)

Der Kernel-Listener sammelt die Daten in `kernel.response` und sendet sie in
`kernel.terminate`. Fehler landen im Contao-Systemlog.

### Einschränkungen

- **Nur Requests, die Contao beantwortet.** Dateien, die der Webserver direkt
  ausliefert (z. B. PDFs unter `/files` oder Assets), werden nicht erfasst.
- **Nicht blockierender Versand nur unter PHP-FPM.** Ohne
  `fastcgi_finish_request()`, etwa mit mod_php, bleibt die Verbindung zum
  Chatbot offen, bis Matomo geantwortet hat (höchstens
  `MATOMO_AI_TRACKING_TIMEOUT`).
- **Nur die Chatbots, die Matomo kennt.** Siehe
  [Erfasste Chatbots](#erfasste-chatbots).

## Bot-Liste aktualisieren

Das Skript liest die Liste aus Matomos `BotDetector.php`. Ausgewertet wird
die Datei nur als Text, nicht ausgeführt. Ergebnis ist
`config/ai_user_agents.php`.

```bash
composer update-user-agents                      # Branch 5.x-dev
MATOMO_REF=5.14.2 composer update-user-agents    # bestimmte Matomo-Version
```

## Tests

```bash
composer install
composer test
```

Getestet mit diesen Kombinationen:

| Contao | Symfony | FOSHttpCache | DBAL | PHP | PHPUnit |
|---|---|---|---|---|---|
| 5.3.52 | 6.4 | 2.16.0 | 3.7.0 | 8.2 | 10.5 |
| 5.7.15 | 7.4 | 3.2.0 | 4.5.0 | 8.5 | 12.5 |
| 6.0.3 | 7.4 | 3.1.0 | 4.4.0 | 8.5 | 10.5 |
| 6.0.4 | 8.1 | 3.2.0 | 4.5.0 | 8.5 | 12.5 |

Die GitHub-Actions-Pipeline (`.github/workflows/ci.yml`) testet diese vier
Kombinationen bei jedem Push auf `main`, bei jedem Pull Request und wöchentlich.
Die niedrigste Contao-6-Kombination läuft dort mit PHP 8.4, der
Mindestversion von Contao 6. Ein weiterer Job prüft, ob
`config/ai_user_agents.php` noch zu Matomos Chatbot-Liste passt.

`tests/HttpCache/AiBotCacheSubscriberTest.php` stellt den Contao-HTTP-Cache
nach (Symfony `HttpCache` + FOS `EventDispatchingHttpCache`). Der Test prüft,
dass Cache-Treffer getrackt werden, jede Domain an ihre eigene Matomo-Site geht
und die internen Header nie beim Client ankommen.

## Lizenz

MIT, siehe [LICENSE](LICENSE).
