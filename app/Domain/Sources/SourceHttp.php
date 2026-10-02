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
use Illuminate\Http\Client\PendingRequest;
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
                ->retry(config()->integer('letaky.http.retries'), config()->integer('letaky.http.retry_delay_ms'))
                ->throw();
        }

        return $request
            ->retry(
                config()->integer('letaky.http.retries'),
                config()->integer('letaky.http.retry_delay_ms'),
                fn (Throwable $error): bool => ! $error instanceof RequestException || $error->response->status() !== Response::HTTP_NOT_FOUND,
                throw: false,
            )
            ->throwIf(fn (ClientResponse $response): bool => $response->status() !== Response::HTTP_NOT_FOUND);
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
