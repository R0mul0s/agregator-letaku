<?php

/**
 * Shrnutí akcí produktu katalogu pro jeho stránku `/akce/camembert` (R158): kolik akcí
 * dnes platí, nejnižší cena („od 24,90 Kč“) a nejvýhodnější akce podle ceny za jednotku —
 * stejné pořadí jako výpis produktu (OfferSearch). Používá ho červený pruh stránky
 * i titulek a popis pro vyhledávače (SeoMeta), proto jen veřejná cena bez uživatele
 * a všechny obchody. Akce, které ještě nezačaly (R76), se nepočítají.
 *
 * Výsledek se pamatuje v atributech požadavku — SeoMeta se volá několikrát (šablona, sdílený
 * seoTitle) a pruh stránky chce totéž.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Enums\OfferType;
use App\Models\Offer;
use Carbon\CarbonImmutable;

final class ProductOfferSummary
{
    /** Atribut požadavku se spočítaným shrnutím (+ ID produktu). */
    private const REQUEST_ATTRIBUTE = 'product-offer-summary.';

    public function __construct(
        private readonly OfferSearch $search,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Shrnutí dnes platných akcí produktu; null, když žádná neplatí.
     *
     * @return array{count: int, chains: list<Chain>, lowestPrice: int, best: array{chain: Chain, price: int, unitPrice: int|null, unitKey: string|null, validTo: CarbonImmutable}}|null
     */
    public function for(int $productId): ?array
    {
        $attributes = request()->attributes;
        $key = self::REQUEST_ATTRIBUTE.$productId;
        if (! $attributes->has($key)) {
            $attributes->set($key, $this->build($productId));
        }

        /** @var array{count: int, chains: list<Chain>, lowestPrice: int, best: array{chain: Chain, price: int, unitPrice: int|null, unitKey: string|null, validTo: CarbonImmutable}}|null */
        return $attributes->get($key);
    }

    /**
     * Spočítá shrnutí z akcí v pořadí výpisu produktu (od nejnižší ceny za jednotku).
     *
     * @return array{count: int, chains: list<Chain>, lowestPrice: int, best: array{chain: Chain, price: int, unitPrice: int|null, unitKey: string|null, validTo: CarbonImmutable}}|null
     */
    private function build(int $productId): ?array
    {
        $offers = $this->search->query(null, new OfferFilters(productId: $productId))
            ->where('valid_from', '<=', $this->calendar->today()->toDateString())
            ->get()
            ->filter(fn (Offer $offer): bool => self::publicPrice($offer) !== null)
            ->values();

        $best = $offers->first();
        if (! $best instanceof Offer) {
            return null;
        }

        $present = array_unique(array_map(fn (Offer $offer): string => $offer->chain->value, $offers->all()));
        $bestPrice = (int) self::publicPrice($best);
        // Akce na více kusů má běžnou cenu kusu — cena za jednotku by mátla (jako na kartě);
        // u balení 1 ks je stejná jako cena a jen by se opakovala
        $unitPrice = $best->offer_type === OfferType::Multibuy ? null : UnitPrice::of($bestPrice, $best->quantity, $best->unit);
        $unitPrice = $unitPrice === $bestPrice ? null : $unitPrice;

        return [
            'count' => $offers->count(),
            'chains' => array_values(array_filter(Chain::cases(), fn (Chain $chain): bool => in_array($chain->value, $present, true))),
            'lowestPrice' => (int) $offers->min(fn (Offer $offer): ?int => self::publicPrice($offer)),
            'best' => [
                'chain' => $best->chain,
                'price' => $bestPrice,
                'unitPrice' => $unitPrice,
                'unitKey' => $unitPrice === null ? null : $best->unit?->unitPriceKey(),
                'validTo' => $best->valid_to,
            ],
        ];
    }

    /**
     * Cena bez uživatele jako Offer::PUBLIC_PRICE_SQL — akce jen s kartou svou cenou s kartou.
     */
    private static function publicPrice(Offer $offer): ?int
    {
        return $offer->price ?? $offer->loyalty_price;
    }
}
