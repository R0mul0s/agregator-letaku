<?php

/**
 * Přiřadí neskončené nabídky k produktům katalogu podle jejich pravidel (R30).
 *
 * Automatická přiřazení se pokaždé spočítají znovu; ruční přiřazení (`is_manual`) a ruční
 * vyřazení (`offer_product_exclusions`) přepočet nemění. Skončené nabídky si přiřazení
 * nechávají jako historii (R10). Spouští se po importu obchodu a po uložení produktu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Matching\OfferPrefilter;
use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchItemMatcher;
use App\Domain\Matching\WatchRule;
use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductExclusion;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class AssignProducts
{
    /** Počet řádků v jednom hromadném zápisu. */
    private const INSERT_CHUNK = 500;

    /** Sloupce nabídky, které párování potřebuje (bez velkého `raw`). */
    private const OFFER_COLUMNS = ['id', 'name', 'brand', 'description', 'variant_note'];

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly WatchItemMatcher $matcher,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Přepočítá přiřazení neskončených nabídek obchodu ke všem produktům (po importu).
     */
    public function forChain(Chain $chain): void
    {
        $products = Product::query()->get();
        if ($products->isEmpty()) {
            return;
        }

        $this->assign($this->currentOffers()->where('chain', $chain), $products);
    }

    /**
     * Přepočítá přiřazení neskončených nabídek všech obchodů k produktu (po jeho uložení).
     */
    public function forProduct(Product $product): void
    {
        $rule = WatchRule::fromProduct($product, $this->normalizer);
        $offers = $this->currentOffers();
        OfferPrefilter::containingAny($offers, $rule->firstWord());

        $this->assign($offers, new Collection([$product]), $this->currentOffers());
    }

    /**
     * Smaže automatická přiřazení nabídek k produktům a spočítá je znovu.
     *
     * @param  Builder<Offer>  $candidates  Nabídky, které se párují
     * @param  Collection<int, Product>  $products
     * @param  Builder<Offer>|null  $scope  Nabídky, jejichž automatická přiřazení se smažou; null = kandidáti
     */
    private function assign(Builder $candidates, Collection $products, ?Builder $scope = null): void
    {
        $productIds = array_map(intval(...), array_values($products->modelKeys()));

        OfferProduct::query()
            ->where('is_manual', false)
            ->whereIn('product_id', $productIds)
            ->whereIn('offer_id', ($scope ?? clone $candidates)->select('id'))
            ->delete();

        $offers = $candidates->get(self::OFFER_COLUMNS);
        $skip = $this->manualPairs($productIds, array_map(intval(...), array_values($offers->modelKeys())));
        $rules = $products->mapWithKeys(fn (Product $product): array => [$product->id => WatchRule::fromProduct($product, $this->normalizer)]);

        $now = CarbonImmutable::now();
        $rows = [];
        foreach ($offers as $offer) {
            $text = $this->matcher->offerText($offer);
            foreach ($rules as $productId => $rule) {
                $status = isset($skip[$offer->id.':'.$productId]) ? null : $this->matcher->matchText($rule, $text, $offer->variant_note !== null);
                if ($status !== null) {
                    $rows[] = [
                        'offer_id' => $offer->id,
                        'product_id' => $productId,
                        'status' => $status->value,
                        'is_manual' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            OfferProduct::query()->insert($chunk);
        }
    }

    /**
     * Dvojice nabídka–produkt, které admin opravil ručně (přiřadil nebo vyřadil) — přepočet je nechá být.
     *
     * @param  list<int>  $productIds
     * @param  list<int>  $offerIds
     * @return array<string, true> Klíč „offer_id:product_id“
     */
    private function manualPairs(array $productIds, array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $manual = OfferProduct::query()->where('is_manual', true)
            ->whereIn('product_id', $productIds)->whereIn('offer_id', $offerIds)
            ->get(['offer_id', 'product_id']);
        $excluded = OfferProductExclusion::query()
            ->whereIn('product_id', $productIds)->whereIn('offer_id', $offerIds)
            ->get(['offer_id', 'product_id']);

        $pairs = [];
        foreach ([...$manual, ...$excluded] as $pair) {
            $pairs[$pair->offer_id.':'.$pair->product_id] = true;
        }

        return $pairs;
    }

    /**
     * Neskončené nabídky, které obchod nestáhl.
     *
     * @return Builder<Offer>
     */
    private function currentOffers(): Builder
    {
        return Offer::query()->active()->notExpired($this->calendar->today());
    }
}
