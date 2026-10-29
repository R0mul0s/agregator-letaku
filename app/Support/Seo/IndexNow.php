<?php

/**
 * IndexNow (R105): ohlásí vyhledávačům změněné veřejné stránky, aby nečekaly na další
 * průchod robota. Ohlášení přijaté jedním vyhledávačem dostanou i ostatní (Bing, Seznam,
 * Yandex, Naver si adresy sdílejí) — nejdřív Seznam přímo, při odmítnutí api.indexnow.org
 * (R178). Google IndexNow nepodporuje — ten čte sitemapu.
 *
 * Jen na produkci a s klíčem z konfigurace; klíč vystavuje CrawlerFilesController na
 * /{klíč}.txt. Každá adresa se ohlásí nejvýš jednou za `min_interval_hours` (cache) —
 * opakované ohlášení stejné adresy vyhledávače berou jako spam. Selhání se jen zapíše do logu,
 * stažení akcí ho nesmí ovlivnit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Support\Seo;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class IndexNow
{
    /** Znaky klíče podle protokolu (8–128 znaků: písmena, číslice, pomlčka) — i pro omezení routy. */
    public const KEY_PATTERN = '[A-Za-z0-9-]{8,128}';

    /** Nejvíc adres v jednom požadavku (limit protokolu). */
    private const MAX_URLS_PER_REQUEST = 10000;

    /** Předpona cache s časem posledního ohlášení adresy. */
    private const SENT_CACHE_PREFIX = 'indexnow.sent.';

    /** Kódy odpovědi, kterými vyhledávač ohlášení přijal (202 = klíč ještě ověřuje). */
    private const ACCEPTED_STATUSES = [200, 202];

    /** Předpona cache pauzy vyhledávače po odpovědi 429 (příliš mnoho požadavků, R157, R178). */
    private const PAUSED_CACHE_PREFIX = 'indexnow.paused.';

    /** Nejvíc znaků odpovědi vyhledávače v logu (chybová stránka může být celé HTML). */
    private const LOGGED_BODY_LENGTH = 500;

    /**
     * Ohlašuje se? Jen na produkci s platným klíčem.
     */
    public function enabled(): bool
    {
        return app()->isProduction() && $this->key() !== null;
    }

    /**
     * Klíč z konfigurace, nebo null, když chybí nebo nemá tvar podle protokolu.
     */
    public function key(): ?string
    {
        $key = config('letaky.indexnow.key');

        return is_string($key) && preg_match('/^'.self::KEY_PATTERN.'$/', $key) === 1 ? $key : null;
    }

    /**
     * Ohlásí adresy, které se v posledních `min_interval_hours` neohlásily, prvnímu vyhledávači
     * z `endpoints`, který ohlášení přijme — vyhledávače si adresy sdílejí, víc není potřeba.
     * Vyhledávač v pauze po odpovědi 429 se přeskočí — opakování by jeho limit jen prodlužovalo
     * (R157); Bing vracel 429 i po celodenní pauze, Seznam ohlášení dostane přímo (R178).
     *
     * @param  list<string>  $urls  Absolutní adresy webu
     */
    public function submit(array $urls): void
    {
        $key = $this->key();
        if (! $this->enabled() || $key === null) {
            return;
        }

        $fresh = array_values(array_filter(array_unique($urls), fn (string $url): bool => ! Cache::has($this->cacheKey($url))));
        foreach (array_chunk($fresh, self::MAX_URLS_PER_REQUEST) as $chunk) {
            foreach (config()->array('letaky.indexnow.endpoints') as $endpoint) {
                if (is_string($endpoint) && ! Cache::has($this->pausedKey($endpoint)) && $this->deliver($key, $endpoint, $chunk)) {
                    $until = CarbonImmutable::now()->addHours(config()->integer('letaky.indexnow.min_interval_hours'));
                    foreach ($chunk as $url) {
                        Cache::put($this->cacheKey($url), true, $until);
                    }

                    break;
                }
            }
        }
    }

    /**
     * Ohlášení jednomu vyhledávači; true = přijal. Po odpovědi 429 ho na `pause_hours` vynechá.
     *
     * @param  list<string>  $urls
     */
    private function deliver(string $key, string $endpoint, array $urls): bool
    {
        $response = $this->send($key, $endpoint, $urls);
        if ($response?->status() === Response::HTTP_TOO_MANY_REQUESTS) {
            $hours = config()->integer('letaky.indexnow.pause_hours');
            Cache::put($this->pausedKey($endpoint), true, CarbonImmutable::now()->addHours($hours));
            // Důvod a Retry-After do logu — 429 chodí i po celodenní pauze (R176)
            Log::warning('IndexNow: příliš mnoho požadavků, ohlašování stojí.', [
                'endpoint' => $endpoint,
                'pause_hours' => $hours,
                'urls' => count($urls),
                'retry_after' => $response->header('Retry-After'),
                'body' => Str::limit($response->body(), self::LOGGED_BODY_LENGTH),
            ]);

            return false;
        }

        return in_array($response?->status(), self::ACCEPTED_STATUSES, true);
    }

    /**
     * Jeden hromadný požadavek; vrátí odpověď, null = vyhledávač nešel zastihnout.
     * Odmítnutí kromě 429 (to řeší deliver) zapíše do logu.
     *
     * @param  list<string>  $urls
     */
    private function send(string $key, string $endpoint, array $urls): ?ClientResponse
    {
        try {
            $response = Http::withUserAgent(config()->string('letaky.http.user_agent'))
                ->timeout(config()->integer('letaky.indexnow.timeout_seconds'))
                ->asJson()
                ->post($endpoint, [
                    'host' => parse_url(config()->string('app.url'), PHP_URL_HOST),
                    'key' => $key,
                    'keyLocation' => route('indexnow-key', ['key' => $key]),
                    'urlList' => $urls,
                ]);
        } catch (ConnectionException $error) {
            Log::warning('IndexNow: vyhledávače se nepodařilo zastihnout.', ['endpoint' => $endpoint, 'error' => $error->getMessage()]);

            return null;
        }

        $status = $response->status();
        if ($status !== Response::HTTP_TOO_MANY_REQUESTS && ! in_array($status, self::ACCEPTED_STATUSES, true)) {
            Log::warning('IndexNow: ohlášení stránek odmítnuto.', ['endpoint' => $endpoint, 'status' => $status, 'body' => Str::limit($response->body(), self::LOGGED_BODY_LENGTH)]);
        }

        return $response;
    }

    /**
     * Klíč cache pro adresu.
     */
    private function cacheKey(string $url): string
    {
        return self::SENT_CACHE_PREFIX.sha1($url);
    }

    /**
     * Klíč cache pauzy vyhledávače po odpovědi 429.
     */
    private function pausedKey(string $endpoint): string
    {
        return self::PAUSED_CACHE_PREFIX.sha1($endpoint);
    }
}
