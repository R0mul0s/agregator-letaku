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
     * Ohlásí adresy, které se v posledních `min_interval_hours` neohlásily.
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
            if ($this->send($key, $chunk)) {
                $until = CarbonImmutable::now()->addHours(config()->integer('letaky.indexnow.min_interval_hours'));
                foreach ($chunk as $url) {
                    Cache::put($this->cacheKey($url), true, $until);
                }
            }
        }
    }

    /**
     * Jeden hromadný požadavek; vrátí, jestli ho vyhledávač přijal.
     *
     * @param  list<string>  $urls
     */
    private function send(string $key, array $urls): bool
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

            return false;
        }

        if (! in_array($response->status(), self::ACCEPTED_STATUSES, true)) {
            Log::warning('IndexNow: ohlášení stránek odmítnuto.', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }

    /**
     * Klíč cache pro adresu.
     */
    private function cacheKey(string $url): string
    {
        return self::SENT_CACHE_PREFIX.sha1($url);
    }
}
