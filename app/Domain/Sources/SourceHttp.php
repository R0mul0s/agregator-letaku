<?php

/**
 * HTTP klient pro stahování od obchodů — User-Agent, timeout, opakování a pauza mezi
 * požadavky z konfigurace (R5, CODING_GUIDELINES sekce 3). Žádný jiný HTTP klient. Po odpovědi
 * 429 se opakuje až v čase, který server uvede (R184).
 *
 * Instance drží čas posledního požadavku, proto ji kontejner nesdílí: každý zdroj má svou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Domain\Sources\Exceptions\SourceUrlRejected;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SourceHttp
{
    /** Jediné povolené schéma adres ke stažení (R170). */
    private const SCHEME = 'https';

    /** Čas konce kvóty v textu odpovědi 429 (Globus: „You can access API after 2026-Nov-15 04:51:00+0000 UTC“). */
    private const RETRY_AFTER_TEXT_PATTERN = '/access API after (\d{4}-[A-Za-z]{3}-\d{1,2} \d{2}:\d{2}:\d{2}[+-]\d{4})/';

    private const RETRY_AFTER_TEXT_FORMAT = 'Y-M-d H:i:sO';

    private ?CarbonImmutable $lastRequestAt = null;

    /**
     * Připravený požadavek; před ním počká, aby mezi požadavky byla nastavená pauza.
     * Chybová odpověď (4xx, 5xx) po vyčerpání opakování vyhodí RequestException.
     *
     * @param  int|null  $delayMs  Pauza pro zdroj s mnoha malými požadavky (Lidl); null = výchozí z konfigurace
     * @param  bool  $allowNotFound  404 není chyba ani důvod opakovat (stránka letáku bez textové vrstvy)
     */
    public function request(?int $delayMs = null, bool $allowNotFound = false): PendingRequest
    {
        $this->waitForTurn($delayMs ?? config()->integer('letaky.http.request_delay_ms'));

        $request = $this->guarded(Http::withUserAgent(config()->string('letaky.http.user_agent'))
            ->timeout(config()->integer('letaky.http.timeout_seconds')));

        if (! $allowNotFound) {
            return $request
                ->retry(config()->integer('letaky.http.retries'), $this->retryDelay(...), $this->shouldRetry(...))
                ->throw();
        }

        return $request
            ->retry(config()->integer('letaky.http.retries'), $this->retryDelay(...), $this->shouldRetry(...), throw: false)
            ->throwIf(fn (ClientResponse $response): bool => $response->status() !== Response::HTTP_NOT_FOUND);
    }

    /**
     * Několik GET požadavků souběžně, nejvýš `$concurrency` najednou (Globus: katalogy hypermarketů,
     * R131), se stejným User-Agentem, timeoutem a opakováním jako request(). Pauza se počká jednou
     * před celou dávkou. Chyba kteréhokoli požadavku po vyčerpání opakování vyhodí výjimku.
     *
     * @param  array<string, array{string, array<string, mixed>}>  $requests  Klíč => [adresa, parametry dotazu]
     * @param  int|null  $delayMs  Pauza zdroje; null = výchozí z konfigurace
     * @return array<string, ClientResponse> Odpovědi podle klíče
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function pool(array $requests, int $concurrency, ?int $delayMs = null): array
    {
        $this->waitForTurn($delayMs ?? config()->integer('letaky.http.request_delay_ms'));

        // Pool si požadavky pamatuje sám (`as`) — návratová hodnota callbacku se nepoužívá
        $results = Http::pool(function (Pool $pool) use ($requests): void {
            foreach ($requests as $key => [$url, $query]) {
                $this->guarded($pool->as((string) $key))
                    ->withUserAgent(config()->string('letaky.http.user_agent'))
                    ->timeout(config()->integer('letaky.http.timeout_seconds'))
                    ->retry(config()->integer('letaky.http.retries'), $this->retryDelay(...), $this->shouldRetry(...))
                    ->get($url, $query);
            }
        }, max(1, $concurrency));

        $responses = [];
        foreach ($results as $key => $result) {
            if ($result instanceof Throwable) {
                throw $result;
            }
            $responses[(string) $key] = $result->throw();
        }

        return $responses;
    }

    /**
     * Stáhne velký soubor (PDF letáku, 25–55 MB) rovnou na disk s delším časovým limitem —
     * celý obsah se nedrží v paměti. Chyba stažení vyhodí výjimku.
     *
     * @param  int|null  $delayMs  Pauza zdroje; null = výchozí z konfigurace
     */
    public function download(string $url, string $path, ?int $delayMs = null): void
    {
        $this->request($delayMs)
            ->timeout(config()->integer('letaky.http.pdf_timeout_seconds'))
            ->sink($path)
            ->get($url);
    }

    /**
     * Stahovat jen z veřejných adres přes https (R170): před odesláním i při každém přesměrování
     * odmítne jiné schéma, IP adresu místo domény a localhost. Adresy letáků přicházejí i z dat
     * obchodu — podvržená by jinak vedla na vnitřní síť hostingu (SSRF).
     */
    private function guarded(PendingRequest $request): PendingRequest
    {
        return $request
            ->beforeSending(fn (Request $sent) => self::assertPublicUrl($sent->url()))
            // Ostatní volby přesměrování (počet) doplní Guzzle z RedirectMiddleware::DEFAULT_SETTINGS
            ->withOptions(['allow_redirects' => [
                'protocols' => [self::SCHEME],
                'on_redirect' => fn (RequestInterface $from, ResponseInterface $response, UriInterface $to) => self::assertPublicUrl((string) $to),
            ]]);
    }

    /**
     * Vyhodí výjimku, když adresa není https s doménovým jménem.
     *
     * @throws SourceUrlRejected
     */
    private static function assertPublicUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? strtolower(trim($host, '[]')) : '';
        if ($scheme !== self::SCHEME || $host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw SourceUrlRejected::for($url);
        }
    }

    /**
     * Pauza před dalším pokusem (ms). Po 429 do času, který server uvede v hlavičce `Retry-After`
     * (sekundy nebo datum) nebo v textu odpovědi („You can access API after 2026-Nov-15
     * 04:51:00+0000 UTC“, Globus — kvóta za minutu), s rezervou na rozdíl hodin; bez času pevná
     * delší pauza. Opakování po 2 s by narazilo na stejnou minutu kvóty (R184).
     */
    private function retryDelay(int $attempt, Throwable $error): int
    {
        $retryDelay = config()->integer('letaky.http.retry_delay_ms');
        if (! $error instanceof RequestException || $error->response->status() !== Response::HTTP_TOO_MANY_REQUESTS) {
            return $retryDelay;
        }

        $until = $this->retryAfter($error->response);
        $wait = $until === null
            ? config()->integer('letaky.http.rate_limit_fallback_ms')
            : (int) CarbonImmutable::now()->diffInMilliseconds($until, absolute: false) + config()->integer('letaky.http.rate_limit_margin_ms');

        return min(max($wait, $retryDelay), config()->integer('letaky.http.rate_limit_max_wait_ms'));
    }

    /**
     * Od kdy server po 429 znovu přijme požadavek; null, když to neuvádí.
     */
    private function retryAfter(ClientResponse $response): ?CarbonImmutable
    {
        $header = trim($response->header('Retry-After'));
        if ($header !== '') {
            if (ctype_digit($header)) {
                return CarbonImmutable::now()->addSeconds((int) $header);
            }

            return $this->parseDate(DATE_RFC7231, $header);
        }

        return preg_match(self::RETRY_AFTER_TEXT_PATTERN, $response->body(), $m) === 1
            ? $this->parseDate(self::RETRY_AFTER_TEXT_FORMAT, $m[1])
            : null;
    }

    /**
     * Datum v daném formátu; null, když neodpovídá.
     */
    private function parseDate(string $format, string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat($format, $value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Opakovat jen výpadek spojení, chybu serveru (5xx) a 429 — odmítnutý požadavek (400, 401,
     * 403, 404) dopadne stejně i napodruhé a opakování by jen čekalo (R113).
     */
    private function shouldRetry(Throwable $error): bool
    {
        if ($error instanceof SourceUrlRejected) {
            return false;
        }
        if (! $error instanceof RequestException) {
            return true;
        }

        return $error->response->serverError() || $error->response->status() === Response::HTTP_TOO_MANY_REQUESTS;
    }

    /**
     * Počká zbytek pauzy od posledního požadavku.
     */
    private function waitForTurn(int $delayMs): void
    {
        if ($this->lastRequestAt !== null && $delayMs > 0) {
            $elapsedMs = (int) $this->lastRequestAt->diffInMilliseconds(CarbonImmutable::now(), absolute: true);

            if ($elapsedMs < $delayMs) {
                Sleep::for($delayMs - $elapsedMs)->milliseconds();
            }
        }

        $this->lastRequestAt = CarbonImmutable::now();
    }
}
