<?php

/**
 * Nabídka připravená pro stránku — ceny v haléřích (formátuje frontend), názvy z lang.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\Store;

final class OfferPresenter
{
    private const DATE_FORMAT = 'Y-m-d';

    /** Bez vybraných prodejen se prodejny akce vypíšou jménem, jen když jich je nejvýš tolik. */
    private const MAX_LISTED_STORES = 3;

    /** @var array<string, string>|null Názvy prodejen podle kódu, načtené při prvním použití */
    private ?array $storeNames = null;

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly PromotionText $promotionText,
    ) {}

    /**
     * Data jedné nabídky pro Vue.
     *
     * @param  list<string>  $selectedStoreCodes  Prodejny vybrané uživatelem (R49) — u akce, která
     *                                            neplatí všude, se vypíšou ty z nich, kde platí
     * @param  array{status: string, price: int, weeks: int, weeksAgo: int}|null  $priceHistory  Porovnání
     *                                                                                           s dřívějšími akcemi (PriceHistory, R59)
     * @return array<string, mixed>
     */
    public function toPage(Offer $offer, array $selectedStoreCodes = [], ?array $priceHistory = null): array
    {
        return [
            'id' => $offer->id,
            'chain' => $offer->chain->value,
            'chainName' => $offer->chain->label(),
            'name' => $offer->name,
            'brand' => $offer->brand,
            'description' => $offer->description,
            'variantNote' => $offer->variant_note,
            'packageText' => $offer->package_text,
            'quantity' => $offer->quantity,
            'unit' => $offer->unit?->value,
            'price' => $offer->price,
            'originalPrice' => $offer->original_price,
            'loyaltyPrice' => $offer->loyalty_price,
            'loyaltyProgramName' => $offer->loyalty_program?->label(),
            'discountPercent' => $offer->discount_percent,
            'offerType' => $offer->offer_type->value,
            // Verzálky od obchodu jako věta (R101)
            'promotionText' => $this->promotionText->forDisplay($offer->promotion_text),
            'onlineOnly' => $offer->online_only,
            'storeFormatName' => $offer->store_format?->label(),
            'unitPrice' => UnitPrice::of($offer->price, $offer->quantity, $offer->unit),
            'loyaltyUnitPrice' => UnitPrice::of($offer->loyalty_price, $offer->quantity, $offer->unit),
            'unitPriceUnit' => $offer->unit?->unitPriceKey(),
            'validFrom' => $offer->valid_from->format(self::DATE_FORMAT),
            'validTo' => $offer->valid_to->format(self::DATE_FORMAT),
            // Akce, která ještě nezačala (R76): za kolik dní začne; null = už platí
            'startsInDays' => $this->startsInDays($offer),
            'sourceUrl' => $offer->source_url,
            // Odkaz na CDN obchodu — obrázek se nestahuje ani neukládá (R22)
            'imageUrl' => $offer->image_url,
            'stores' => $this->stores($offer, $selectedStoreCodes),
            'priceHistory' => $priceHistory,
        ];
    }

    /**
     * Za kolik dní akce začne (R76); null, když už platí.
     */
    private function startsInDays(Offer $offer): ?int
    {
        $today = $this->calendar->today();

        return $offer->isUpcoming($today) ? (int) $today->diffInDays($offer->valid_from) : null;
    }

    /**
     * Kde akce platí, když neplatí ve všech prodejnách (R49): `names` = prodejny ke zobrazení,
     * `count` = počet všech prodejen akce, `elsewhere` = není v žádné vybrané prodejně,
     * `list` = všechny prodejny akce pro okno se seznamem (`selected` = vybraná uživatelem).
     * Null = platí všude, nebo ve všech vybraných prodejnách (pak to uživatele nezajímá),
     * nebo prodejny nejsou načtené.
     *
     * @param  list<string>  $selectedStoreCodes
     * @return array{names: list<string>, count: int, elsewhere: bool, list: list<array{name: string, selected: bool}>}|null
     */
    private function stores(Offer $offer, array $selectedStoreCodes): ?array
    {
        if (! $offer->relationLoaded('stores') || $offer->stores->isEmpty()) {
            return null;
        }

        $codes = array_values($offer->stores->map(fn (OfferStore $store): string => $store->store_code)->all());
        $list = $this->storeList($codes, $selectedStoreCodes);
        if ($selectedStoreCodes !== []) {
            $mine = array_values(array_intersect($codes, $selectedStoreCodes));
            if (count($mine) === count($selectedStoreCodes)) {
                return null;
            }

            return ['names' => $this->storeNames($mine), 'count' => count($codes), 'elsewhere' => $mine === [], 'list' => $list];
        }

        return ['names' => count($codes) <= self::MAX_LISTED_STORES ? $this->storeNames($codes) : [], 'count' => count($codes), 'elsewhere' => false, 'list' => $list];
    }

    /**
     * Prodejny akce pro okno se seznamem; řadí frontend (české řazení podle prohlížeče).
     *
     * @param  list<string>  $codes
     * @param  list<string>  $selectedStoreCodes
     * @return list<array{name: string, selected: bool}>
     */
    private function storeList(array $codes, array $selectedStoreCodes): array
    {
        $this->storeNames ??= Store::query()->pluck('name', 'code')->all();
        $list = [];
        foreach ($codes as $code) {
            if (isset($this->storeNames[$code])) {
                $list[] = ['name' => $this->storeNames[$code], 'selected' => in_array($code, $selectedStoreCodes, true)];
            }
        }

        return $list;
    }

    /**
     * Názvy prodejen podle kódů, abecedně; neznámý kód se vynechá.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function storeNames(array $codes): array
    {
        $this->storeNames ??= Store::query()->pluck('name', 'code')->all();
        $names = array_values(array_filter(array_map(fn (string $code): ?string => $this->storeNames[$code] ?? null, $codes)));
        sort($names);

        return $names;
    }
}
