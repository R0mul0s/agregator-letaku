<?php

/**
 * Našeptávač hledání ve Všech akcích (R71) — místo holých textů rovnou odpověď: produkty
 * katalogu s počtem aktuálních akcí a nejnižší cenou (klepnutím filtr akcí produktu nebo
 * hlídání) a první akce stejně seřazené jako výsledky, s obrázkem, obchodem a cenou.
 * Když text nic nenajde, zkusí opravu překlepu (SearchVocabulary). Bez textu oblíbené
 * produkty — ty, které mají právě nejvíc akcí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Catalog\CatalogBrowseTree;
use App\Domain\Catalog\CategoryPaths;
use App\Domain\Chains\ShoppingPreferencesScope;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SearchSuggestions
{
    /** Kolik produktů katalogu podle názvu vybrat, než se odfiltrují ty bez akcí. */
    private const PRODUCT_CANDIDATES = 30;

    public function __construct(
        private readonly OfferSearch $search,
        private readonly SearchVocabulary $vocabulary,
        private readonly LocalCalendar $calendar,
        private readonly CategoryPaths $categories,
        private readonly OfferPages $pages,
        private readonly ShoppingPreferencesScope $preferences,
    ) {}

    /**
     * Návrhy k hledanému textu.
     *
     * @return array{corrected: string|null, total: int, products: list<array<string, mixed>>, offers: list<array<string, mixed>>}
     */
    public function for(string $text, OfferFilters $filters, ?User $user): array
    {
        $corrected = null;
        $total = $this->search->query($text, $filters)->count();
        if ($total === 0) {
            $corrected = $this->vocabulary->correct($text);
            if ($corrected !== null) {
                $text = $corrected;
                $total = $this->search->query($text, $filters)->count();
            }
        }

        $offers = $total === 0 ? [] : $this->search->query($text, $filters)
            ->limit(config()->integer('letaky.search.suggest_offers'))
            ->get()
            ->map(fn (Offer $offer): array => $this->offerToPage($offer))
            ->all();

        return [
            'corrected' => $corrected,
            'total' => $total,
            'products' => $this->products($text, $filters, $user),
            'offers' => array_values($offers),
        ];
    }

    /**
     * Oblíbené produkty pro prázdné pole — nejvíc aktuálních akcí. Bez limitu tolik, kolik
     * ukazuje našeptávač (ukázka hlídání na úvodní stránce, R90, chce víc).
     *
     * @return list<array<string, mixed>>
     */
    public function popular(OfferFilters $filters, ?User $user, ?int $limit = null): array
    {
        $stats = $this->stats(null, $filters);
        uasort($stats, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $ids = array_slice(array_keys($stats), 0, $limit ?? config()->integer('letaky.search.popular_products'));
        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $this->productsToPage(array_values(array_filter(array_map(fn (int $id): ?Product => $products->get($id), $ids))), $stats, $filters, $user);
    }

    /**
     * Produkty katalogu, jejichž název obsahuje všechna slova jako začátky slov a které mají
     * aktuální akce; název začínající textem první, pak podle počtu akcí.
     *
     * @return list<array<string, mixed>>
     */
    private function products(string $text, OfferFilters $filters, ?User $user): array
    {
        $query = Product::query();
        foreach (WordStart::words($text) as $word) {
            WordStart::where($query, ['name'], $word);
        }
        $candidates = $query->orderByRaw('name LIKE ? DESC', [addcslashes($text, '%_\\').'%'])
            ->orderBy('name')
            ->limit(self::PRODUCT_CANDIDATES)
            ->get();

        $stats = $this->stats(array_values($candidates->pluck('id')->all()), $filters);
        $withOffers = array_values(array_filter($candidates->all(), fn (Product $product): bool => isset($stats[$product->id])));
        // Stabilní řazení: pořadí z databáze (začátek názvu, abeceda) jen uvnitř stejného počtu akcí
        // by přeházelo „Pizza“ za „Pizza mražená“ — počet rozhoduje jen u názvů, které textem nezačínají
        $prefix = mb_strtolower($text);
        usort($withOffers, fn (Product $a, Product $b): int => [! str_starts_with(mb_strtolower($a->name), $prefix), -$stats[$a->id]['count']]
            <=> [! str_starts_with(mb_strtolower($b->name), $prefix), -$stats[$b->id]['count']]);

        return $this->productsToPage(array_slice($withOffers, 0, config()->integer('letaky.search.suggest_products')), $stats, $filters, $user);
    }

    /**
     * Počet aktuálních akcí a nejnižší cena (bez karty) produktů; produkt bez akcí chybí.
     *
     * @param  list<int>|null  $productIds  null = všechny produkty
     * @return array<int, array{count: int, lowestPrice: int|null}>
     */
    private function stats(?array $productIds, OfferFilters $filters): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = OfferProduct::query()
            ->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->when($productIds !== null, fn (Builder $query) => $query->whereIn('offer_product.product_id', $productIds ?? []))
            ->whereNull('offers.withdrawn_at')
            ->where('offers.valid_to', '>=', $this->calendar->today()->toDateString())
            ->when($filters->chains !== [], fn (Builder $query) => $query->whereIn('offers.chain', $filters->chains))
            ->when($filters->withoutEshop, fn (Builder $query) => $query->where('offers.online_only', false))
            // Nastavení Mých obchodů (R100) poddotazem — podmínky na prodejny a karty jsou nad modelem Offer
            ->when($filters->preferencesOf, fn (Builder $query, User $user) => $query->whereIn(
                'offer_product.offer_id',
                Offer::query()->select('id')->tap(fn (Builder $offers) => $this->preferences->apply($offers, $user)),
            ))
            ->groupBy('offer_product.product_id')
            ->selectRaw('offer_product.product_id, COUNT(DISTINCT offer_product.offer_id) AS offers_count, MIN(offers.price) AS lowest_price')
            ->toBase()
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->product_id] = [
                'count' => (int) $row->offers_count,
                'lowestPrice' => $row->lowest_price === null ? null : (int) $row->lowest_price,
            ];
        }

        return $stats;
    }

    /**
     * Produkty pro našeptávač: počet akcí, cena od, odkaz na jejich akce a jestli je uživatel hlídá.
     *
     * @param  list<Product>  $products
     * @param  array<int, array{count: int, lowestPrice: int|null}>  $stats
     * @return list<array<string, mixed>>
     */
    private function productsToPage(array $products, array $stats, OfferFilters $filters, ?User $user): array
    {
        $watched = $user === null ? [] : array_flip($user->watchItems()->whereNotNull('product_id')->pluck('product_id')->all());

        return array_map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'icon' => CatalogBrowseTree::icon($this->categories->department($product->category_id)),
            'offersCount' => $stats[$product->id]['count'] ?? 0,
            'lowestPrice' => $stats[$product->id]['lowestPrice'] ?? null,
            // Čistá adresa produktu (R94), vybrané obchody jako parametr
            'url' => $this->pages->url(new OfferFilters($filters->chains, $product->id, withoutEshop: $filters->withoutEshop)),
            'watched' => isset($watched[$product->id]),
        ], $products);
    }

    /**
     * Akce pro našeptávač — jen to, co se v řádku ukáže.
     *
     * @return array<string, mixed>
     */
    private function offerToPage(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'name' => $offer->name,
            'chain' => $offer->chain->value,
            'packageText' => $offer->package_text,
            'price' => $offer->price,
            'loyaltyPrice' => $offer->loyalty_price,
            'discountPercent' => $offer->effectiveDiscountPercent(),
            'imageUrl' => $offer->image_url,
        ];
    }
}
