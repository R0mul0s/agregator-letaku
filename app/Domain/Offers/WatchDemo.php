<?php

/**
 * Živá ukázka hlídání na úvodní stránce (R90) — návštěvník si bez registrace vybere obchody
 * a pár produktů katalogu a hned vidí, kolik akcí by mu Slevohlídka hlídala a které z nich
 * jsou nejlevnější za kilo, litr nebo kus. Jen akce, které platí dnes (R76).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\OfferProduct;
use Illuminate\Database\Eloquent\Builder;

final class WatchDemo
{
    public function __construct(
        private readonly SearchSuggestions $suggestions,
        private readonly OfferPresenter $presenter,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Produkty k výběru — ty s nejvíc aktuálními akcemi (id, název, ikona oddělení).
     *
     * @return list<array{id: int, name: string, icon: string}>
     */
    public function products(): array
    {
        $products = $this->suggestions->popular(new OfferFilters, null, config()->integer('letaky.landing.demo_products'));

        return array_map(fn (array $product): array => [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'icon' => (string) $product['icon'],
        ], $products);
    }

    /**
     * Počet dnes platných akcí vybraných produktů ve vybraných obchodech a nejlevnější akce
     * za jednotku, po jedné na produkt v pořadí výběru.
     *
     * @param  list<int>  $productIds
     * @param  list<Chain>  $chains  Prázdné = všechny obchody
     * @return array{count: int, offers: list<array<string, mixed>>}
     */
    public function result(array $productIds, array $chains): array
    {
        if ($productIds === []) {
            return ['count' => 0, 'offers' => []];
        }

        // Jen sloupce pro výběr nejlevnější — celé řádky (s `raw`) se načtou až pro vybrané akce
        $rows = OfferProduct::query()
            ->joinCurrentOffers($this->calendar->today(), startedOnly: true)
            ->whereIn('offer_product.product_id', $productIds)
            ->when($chains !== [], fn (Builder $query) => $query->whereIn('offers.chain', $chains))
            ->select(['offer_product.product_id', 'offers.id', 'offers.price', 'offers.loyalty_price', 'offers.quantity', 'offers.unit'])
            ->toBase()
            ->get();

        $byProduct = [];
        $offerIds = [];
        foreach ($rows as $row) {
            $offerIds[(int) $row->id] = true;
            $byProduct[(int) $row->product_id][] = $row;
        }

        $picked = [];
        foreach ($productIds as $productId) {
            $cheapest = $this->cheapest($byProduct[$productId] ?? [], $picked);
            if ($cheapest !== null) {
                $picked[$cheapest] = true;
            }
            if (count($picked) >= config()->integer('letaky.landing.demo_offers')) {
                break;
            }
        }

        $offers = Offer::query()->whereIn('id', array_keys($picked))->with('stores')->get()->keyBy('id');
        $page = [];
        // V pořadí výběru produktů, ne podle ID
        foreach (array_keys($picked) as $id) {
            $offer = $offers->get($id);
            if ($offer instanceof Offer) {
                $page[] = $this->presenter->toPage($offer);
            }
        }

        return ['count' => count($offerIds), 'offers' => $page];
    }

    /**
     * ID akce s nejnižší cenou za jednotku (bez balení podle ceny), kromě už vybraných.
     * Akce jen s kartou se porovnává cenou s kartou.
     *
     * @param  list<\stdClass>  $rows
     * @param  array<int, true>  $picked
     */
    private function cheapest(array $rows, array $picked): ?int
    {
        $best = null;
        $bestKey = null;
        foreach ($rows as $row) {
            $price = $row->price ?? $row->loyalty_price;
            if ($price === null || isset($picked[(int) $row->id])) {
                continue;
            }
            $unitPrice = UnitPrice::of((int) $price, $row->quantity === null ? null : (float) $row->quantity, PackageUnit::tryFrom((string) $row->unit));
            // Akce s cenou za jednotku před akcemi bez ní, pak podle ceny a ID (stabilní výsledek)
            $key = [$unitPrice === null ? 1 : 0, $unitPrice ?? (int) $price, (int) $row->id];
            if ($bestKey === null || $key < $bestKey) {
                $best = (int) $row->id;
                $bestKey = $key;
            }
        }

        return $best;
    }
}
