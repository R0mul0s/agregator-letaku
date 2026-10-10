<?php

/**
 * HTTP klient pro stahování od obchodů — User-Agent, timeout, opakování a pauza mezi
 * požadavky z konfigurace (R5, CODING_GUIDELINES sekce 3). Žádný jiný HTTP klient.
 *
 * Instance drží čas posledního požadavku, proto ji kontejner nesdílí: každý zdroj má svou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SourceHttp
{
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

        $request = Http::withUserAgent(config()->string('letaky.http.user_agent'))
            ->timeout(config()->integer('letaky.http.timeout_seconds'));

        if (! $allowNotFound) {
            return $request
                ->retry(config()->integer('letaky.http.retries'), config()->integer('letaky.http.retry_delay_ms'), $this->shouldRetry(...))
                ->throw();
        }

        return $request
            ->retry(config()->integer('letaky.http.retries'), config()->integer('letaky.http.retry_delay_ms'), $this->shouldRetry(...), throw: false)
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
                $pool->as((string) $key)
                    ->withUserAgent(config()->string('letaky.http.user_agent'))
                    ->timeout(config()->integer('letaky.http.timeout_seconds'))
                    ->retry(config()->integer('letaky.http.retries'), config()->integer('letaky.http.retry_delay_ms'), $this->shouldRetry(...))
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
     * Opakovat jen výpadek spojení, chybu serveru (5xx) a 429 — odmítnutý požadavek (400, 401,
     * 403, 404) dopadne stejně i napodruhé a opakování by jen čekalo (R113).
     */
    private function shouldRetry(Throwable $error): bool
    {
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
