<?php

/**
 * IndexNow (R105): ohlásí vyhledávačům změněné veřejné stránky, aby nečekaly na další
 * průchod robota. Jeden požadavek na api.indexnow.org dostanou Bing, Seznam, Yandex i Naver
 * (adresy si sdílejí). Google IndexNow nepodporuje — ten čte sitemapu.
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /** Cache: ohlašování stojí po odpovědi 429 (příliš mnoho požadavků, R157). */
    public const PAUSED_CACHE_KEY = 'indexnow.paused';

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
     * Ohlásí adresy, které se v posledních `min_interval_hours` neohlásily. Během pauzy po
     * odpovědi 429 neohlásí nic — opakování by limit vyhledávače jen prodlužovalo (R157).
     *
     * @param  list<string>  $urls  Absolutní adresy webu
     */
    public function submit(array $urls): void
    {
        $key = $this->key();
        if (! $this->enabled() || $key === null || Cache::has(self::PAUSED_CACHE_KEY)) {
            return;
        }

        $fresh = array_values(array_filter(array_unique($urls), fn (string $url): bool => ! Cache::has($this->cacheKey($url))));
        foreach (array_chunk($fresh, self::MAX_URLS_PER_REQUEST) as $chunk) {
            $status = $this->send($key, $chunk);
            if ($status === Response::HTTP_TOO_MANY_REQUESTS) {
                $hours = config()->integer('letaky.indexnow.pause_hours');
                Cache::put(self::PAUSED_CACHE_KEY, true, CarbonImmutable::now()->addHours($hours));
                Log::warning('IndexNow: příliš mnoho požadavků, ohlašování stojí.', ['pause_hours' => $hours]);

                return;
            }

            if (in_array($status, self::ACCEPTED_STATUSES, true)) {
                $until = CarbonImmutable::now()->addHours(config()->integer('letaky.indexnow.min_interval_hours'));
                foreach ($chunk as $url) {
                    Cache::put($this->cacheKey($url), true, $until);
                }
            }
        }
    }

    /**
     * Jeden hromadný požadavek; vrátí kód odpovědi, null = vyhledávač nešel zastihnout.
     * Odmítnutí kromě 429 (to řeší submit) zapíše do logu.
     *
     * @param  list<string>  $urls
     */
    private function send(string $key, array $urls): ?int
    {
        try {
            $response = Http::withUserAgent(config()->string('letaky.http.user_agent'))
                ->timeout(config()->integer('letaky.indexnow.timeout_seconds'))
                ->asJson()
                ->post(config()->string('letaky.indexnow.endpoint'), [
                    'host' => parse_url(config()->string('app.url'), PHP_URL_HOST),
                    'key' => $key,
                    'keyLocation' => route('indexnow-key', ['key' => $key]),
                    'urlList' => $urls,
                ]);
        } catch (ConnectionException $error) {
            Log::warning('IndexNow: vyhledávače se nepodařilo zastihnout.', ['error' => $error->getMessage()]);

            return null;
        }

        $status = $response->status();
        if ($status !== Response::HTTP_TOO_MANY_REQUESTS && ! in_array($status, self::ACCEPTED_STATUSES, true)) {
            Log::warning('IndexNow: ohlášení stránek odmítnuto.', ['status' => $status, 'body' => $response->body()]);
        }

        return $status;
    }

    /**
     * Klíč cache pro adresu.
     */
    private function cacheKey(string $url): string
    {
        return self::SENT_CACHE_PREFIX.sha1($url);
    }
}
