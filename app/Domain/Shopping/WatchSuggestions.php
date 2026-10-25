<?php

/**
 * Návrhy, co hlídat (R148): co si uživatel opakovaně dává do nákupního seznamu. Vlastní
 * položka („mléko“) se počítá podle názvu — shoduje-li se s produktem katalogu, návrh nabídne
 * produkt; akce se počítá podle produktu katalogu, ke kterému je přiřazená (WatchTargets).
 * Akce bez produktu se nepočítá — její název je na hlídání moc konkrétní.
 *
 * Zásah se počítá nejvýš jednou za `hit_interval_hours` (přidat, smazat a přidat znovu není
 * opakovaný nákup); po `window_days` bez zásahu se počítá znovu od jedné. Návrh se ukáže od
 * `min_hits` zásahů, dokud ho uživatel nezavře („Ne, díky“) nebo položku nezačne hlídat —
 * pak se nevrátí; zavřené návrhy po `dismiss_days` maže úklid (PruneExpiredSessions).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Shopping;

use App\Domain\Catalog\WatchTargets;
use App\Domain\Matching\TextNormalizer;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use App\Models\WatchSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class WatchSuggestions
{
    public function __construct(
        private TextNormalizer $normalizer,
        private WatchTargets $watchTargets,
    ) {}

    /**
     * Vlastní položka seznamu: započítá její název; shodný produkt katalogu se nabídne místo slov.
     */
    public function recordCustomItem(User $user, string $name): void
    {
        $term = $this->term($name);
        if (mb_strlen($term) < config()->integer('letaky.watch.min_search_word_length')) {
            return;
        }

        $product = $this->productNamed($term);
        $this->record($user, $term, $product->name ?? $name, $product?->id);
    }

    /**
     * Akce přidaná do seznamu: započítá produkt katalogu, ke kterému je přiřazená; už hlídaný
     * produkt a akci bez produktu přeskočí.
     */
    public function recordOffer(User $user, Offer $offer): void
    {
        $target = $this->watchTargets->forOffers([$offer], $user)[$offer->id] ?? null;
        if ($target === null || $target['productId'] === null || $target['watched']) {
            return;
        }

        $this->record($user, $this->term($target['name']), $target['name'], $target['productId']);
    }

    /**
     * Otevřené návrhy k ukázání — dost zásahů v okně, nezavřené a ještě nehlídané; nejčastější první.
     *
     * @return list<array{id: int, name: string, productId: int|null, hits: int, dismissUrl: string}>
     */
    public function forUser(User $user): array
    {
        $suggestions = $user->watchSuggestions()
            ->whereNull('dismissed_at')
            ->where('hits', '>=', config()->integer('letaky.watch_suggestions.min_hits'))
            ->where('last_hit_at', '>=', $this->windowStart()->toDateTimeString())
            ->orderByDesc('hits')
            ->orderByDesc('last_hit_at')
            ->get();
        if ($suggestions->isEmpty()) {
            return [];
        }

        $watched = $this->watchedKeys($user);
        $open = $suggestions
            ->reject(fn (WatchSuggestion $suggestion): bool => isset($watched['terms'][$suggestion->term])
                || ($suggestion->product_id !== null && isset($watched['products'][$suggestion->product_id])))
            ->take(config()->integer('letaky.watch_suggestions.max_shown'));

        return array_values($open->map(fn (WatchSuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'name' => $suggestion->name,
            'productId' => $suggestion->product_id,
            'hits' => $suggestion->hits,
            'dismissUrl' => route('watch-suggestions.destroy', $suggestion, absolute: false),
        ])->all());
    }

    /**
     * „Ne, díky“ — návrh se už neukáže.
     */
    public function dismiss(WatchSuggestion $suggestion): void
    {
        $suggestion->update(['dismissed_at' => CarbonImmutable::now()]);
    }

    /**
     * Uživatel položku začal hlídat — návrh stejného produktu nebo názvu se uzavře a po případném
     * smazání hlídání se nevrátí.
     */
    public function closeFor(User $user, WatchItem $item): void
    {
        $user->watchSuggestions()
            ->whereNull('dismissed_at')
            ->where(fn (Builder $query) => $query
                ->where('term', $this->term($item->name))
                ->when($item->product_id, fn (Builder $query, int $productId) => $query->orWhere('product_id', $productId)))
            ->update(['dismissed_at' => CarbonImmutable::now()]);
    }

    /**
     * Smaže návrhy bez zásahu za `window_days` a zavřené před `dismiss_days` (zásady, kap. 3);
     * vrátí počet smazaných.
     */
    public function prune(): int
    {
        $dismissedBefore = CarbonImmutable::now()->subDays(config()->integer('letaky.watch_suggestions.dismiss_days'));

        return WatchSuggestion::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('dismissed_at')->where('last_hit_at', '<', $this->windowStart()->toDateTimeString()))
                ->orWhere('dismissed_at', '<', $dismissedBefore->toDateTimeString()))
            ->delete();
    }

    /**
     * Započítá zásah: nový návrh, další zásah v okně, nebo po okně znovu od jedné. Zavřený
     * návrh se jen ponechá.
     */
    private function record(User $user, string $term, string $name, ?int $productId): void
    {
        $now = CarbonImmutable::now();
        $name = Str::limit($name, config()->integer('letaky.watch.name_max_length'), '');
        $suggestion = $user->watchSuggestions()->where('term', $term)->first();

        if ($suggestion === null) {
            // Dva souběžné požadavky (dvojklik) by jinak narazily na unikátní klíč (R170)
            $user->watchSuggestions()->createOrFirst(['term' => $term], ['name' => $name, 'product_id' => $productId, 'last_hit_at' => $now]);

            return;
        }
        if ($suggestion->dismissed_at !== null) {
            return;
        }
        if ($suggestion->last_hit_at->greaterThan($now->subHours(config()->integer('letaky.watch_suggestions.hit_interval_hours')))) {
            return;
        }

        $suggestion->update([
            'name' => $name,
            'product_id' => $productId,
            'hits' => $suggestion->last_hit_at->lessThan($this->windowStart()) ? 1 : $suggestion->hits + 1,
            'last_hit_at' => $now,
        ]);
    }

    /**
     * Produkty a názvy, které uživatel už hlídá — návrh na ně se neukáže.
     *
     * @return array{products: array<int, true>, terms: array<string, true>}
     */
    private function watchedKeys(User $user): array
    {
        $keys = ['products' => [], 'terms' => []];
        foreach ($user->watchItems()->get(['name', 'product_id', 'keywords']) as $item) {
            if ($item->product_id !== null) {
                $keys['products'][$item->product_id] = true;
            }
            $keys['terms'][$this->term($item->name)] = true;
            if ($item->keywords !== null) {
                $keys['terms'][$this->term($item->keywords)] = true;
            }
        }

        return $keys;
    }

    /**
     * Produkt katalogu se stejným názvem po normalizaci („Mléko“ i „mleko“), nebo null.
     */
    private function productNamed(string $term): ?Product
    {
        return Product::query()->get(['id', 'name'])->first(fn (Product $product): bool => $this->term($product->name) === $term);
    }

    /**
     * Klíč návrhu: název po normalizaci jako u párování („Mléko!“ → „mleko“).
     */
    private function term(string $name): string
    {
        return Str::limit($this->normalizer->word($name), config()->integer('letaky.watch.name_max_length'), '');
    }

    /**
     * Začátek okna, ve kterém se zásahy počítají.
     */
    private function windowStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(config()->integer('letaky.watch_suggestions.window_days'));
    }
}
