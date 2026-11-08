<?php

/**
 * Převod odpovědí JSON API e-shopu Rohlik.cz na nabídky (ZDROJE_DAT.md, Rohlík; R180).
 *
 * Produkt má v `products/prices` seznam `sales[]` s typem akce. Pravidla převodu:
 * - akce pro všechny jsou typy `offer_sale_types` (týdenní `sale`, dlouhodobá `longtermAction`) s kusovou
 *   cenou (`triggerAmount` 1) nižší než původní; je-li jich víc, platí nejnižší cena
 * - `multipack` („Kup 3, −16 %“) je akce na množství: bez kusové akce typ Multibuy s běžnou cenou kusu
 *   a výhodnou cenou v textu („od 3 ks: 9,90 Kč“), s kusovou akcí jen text k ní (jako Billa, R48)
 * - akce jen pro předplatitele Xtra (`premium`, „−10 % pro vás“), dětského klubu, při doručení dnes
 *   a krátké trvanlivosti se neberou (R180)
 * - ceny jsou float v Kč přes PriceParser::fromFloat; konec akce `validTill` je okamžik s posunem,
 *   začátek API nemá — je to den stažení a pokračující akce si ho ponese z prvního stažení (R54)
 * - vážené zboží (`weightedItem`, „cca 500 g“): ceny jsou za odhadovaný kus, bere se cena za kg
 *   (`pricePerUnit`) a původní cena v poměru
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

namespace App\Domain\Sources\Rohlik;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\WebUrl;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;

final class RohlikParser
{
    /** Počet kusů kusové akce (`triggerAmount`). */
    private const SINGLE_PIECE = 1;

    /** Balení, ke kterému se vztahuje cena váženého zboží. */
    private const PER_KG_PACKAGE = '1 kg';

    /** Oddělovač podmínky akce na množství a výhodné ceny kusu („od 3 ks: 9,90 Kč“). */
    private const MULTIBUY_PRICE_SEPARATOR = ': ';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PriceFormatter $formatter,
        private readonly PackageParser $packages,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * ID produktů jedné stránky akcí oddělení (`categories/sales/{id}/products`).
     *
     * @param  array<mixed>  $response
     * @return list<int>
     *
     * @throws SourceResponseChanged
     */
    public function productIds(array $response, int $department): array
    {
        $ids = $response['productIds'] ?? null;
        if (! is_array($ids) || ! array_is_list($ids)) {
            throw SourceResponseChanged::because(Chain::Rohlik, "akce oddělení {$department} nemají productIds");
        }

        return array_values(array_filter($ids, is_int(...)));
    }

    /**
     * Položky odpovědi `products/prices` (podle `productId`) nebo `products` (podle `id`).
     *
     * @param  array<mixed>  $response
     * @return array<int, array<string, mixed>>
     *
     * @throws SourceResponseChanged
     */
    public function byId(array $response, string $idKey, string $what): array
    {
        if (! array_is_list($response)) {
            throw SourceResponseChanged::because(Chain::Rohlik, "{$what} nejsou seznam");
        }

        $byId = [];
        foreach ($response as $item) {
            if (is_array($item) && is_int($item[$idKey] ?? null)) {
                $byId[$item[$idKey]] = $item;
            }
        }

        return $byId;
    }

    /**
     * Má produkt akci, kterou bereme (kusovou pro všechny nebo na množství)? Bez ní (jen Xtra, dětský
     * klub…) se vynechá — to není chyba převodu.
     *
     * @param  array<string, mixed>  $prices  Položka `products/prices`
     */
    public function hasOfferSale(array $prices): bool
    {
        return $this->singleSale($prices) !== null || $this->multipack($prices) !== null;
    }

    /**
     * Skončila akce produktu už včera nebo dřív (API ji ještě vrací)?
     *
     * @param  array<string, mixed>  $prices
     */
    public function hasEnded(array $prices, CarbonImmutable $today): bool
    {
        return $this->validTo($this->singleSale($prices) ?? $this->multipack($prices))?->lessThan($today) === true;
    }

    /**
     * Nabídka produktu; null bez akce, kterou bereme, bez názvu nebo konce akce a u skončené akce.
     *
     * @param  array<string, mixed>  $product  Položka `products`
     * @param  array<string, mixed>  $prices  Položka `products/prices`
     * @param  string  $category  Oddělení, ze kterého produkt je
     * @param  CarbonImmutable  $today  Místní dnešek — začátek nově nalezené akce
     */
    public function offer(array $product, array $prices, string $category, CarbonImmutable $today): ?OfferData
    {
        $id = $product['id'] ?? null;
        $name = Text::clean(is_string($product['name'] ?? null) ? $product['name'] : null);
        $single = $this->singleSale($prices);
        $multipack = $this->multipack($prices);
        $validTo = $this->validTo($single ?? $multipack);
        if (! is_int($id) || $name === null || $validTo === null || $validTo->lessThan($today)) {
            return null;
        }

        $sizeText = Text::clean(is_string($product['textualAmount'] ?? null) ? $product['textualAmount'] : null);
        $multibuyText = $multipack === null ? null : $this->multibuyText($multipack);

        if ($single !== null) {
            $offerType = OfferType::Discount;
            [$price, $originalPrice] = $this->salePrices($single, $this->isWeighted($product));
            $promotionText = $multibuyText;
        } elseif ($multipack !== null) {
            $offerType = OfferType::Multibuy;
            // Akce na množství: běžná cena kusu, výhodná cena v textu
            $price = $this->amount($this->money($prices['price'] ?? null));
            $originalPrice = null;
            $promotionText = $multibuyText;
        } else {
            return null;
        }
        if ($price === null) {
            return null;
        }

        $weighted = $offerType === OfferType::Discount && $this->isWeighted($product);
        $packageText = $weighted ? self::PER_KG_PACKAGE : $sizeText;
        $images = is_array($product['images'] ?? null) ? $product['images'] : [];
        $slug = $product['slug'] ?? null;

        return new OfferData(
            externalId: (string) $id,
            name: $name,
            offerType: $offerType,
            validFrom: $today,
            validTo: $validTo,
            raw: ['product' => $product, 'prices' => $prices],
            price: $price,
            originalPrice: $originalPrice,
            promotionText: $promotionText,
            brand: Text::clean(is_string($product['brand'] ?? null) ? $product['brand'] : null),
            // Vážené zboží: cena je za kg, odhadovaná hmotnost kusu („cca 500 g“) zůstane v popisu
            description: $weighted ? $sizeText : null,
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            onlineOnly: true,
            sourceCategory: $category,
            imageUrl: WebUrl::orNull(is_string($images[0] ?? null) ? $images[0] : null),
            sourceUrl: is_string($slug) ? WebUrl::orNull(sprintf(config()->string('letaky.sources.rohlik.product_url'), $id, $slug)) : null,
        );
    }

    /**
     * Prodejce produktu (`sellerId`: Rohlík, partneři), null když chybí.
     *
     * @param  array<string, mixed>  $product
     */
    public function sellerId(array $product): ?int
    {
        return is_int($product['sellerId'] ?? null) ? $product['sellerId'] : null;
    }

    /**
     * Akční a původní cena kusové akce; u váženého zboží za kg (akční z `pricePerUnit`, původní v poměru
     * cen kusu — `originalPricePerUnit` API nevyplňuje).
     *
     * @param  array<string, mixed>  $sale
     * @return array{int|null, int|null}
     */
    private function salePrices(array $sale, bool $weighted): array
    {
        $price = $this->money($sale['price'] ?? null);
        $original = $this->money($sale['originalPrice'] ?? null);
        $perUnit = $this->money($sale['pricePerUnit'] ?? null);

        if ($weighted && $perUnit !== null && $price !== null && $price > 0 && $original !== null) {
            return [$this->amount($perUnit), $this->amount($perUnit * $original / $price)];
        }

        return [$this->amount($price), $this->amount($original)];
    }

    /**
     * Kusová akce pro všechny s nejnižší cenou (cena nižší než původní), nebo null.
     *
     * @param  array<string, mixed>  $prices
     * @return array<string, mixed>|null
     */
    private function singleSale(array $prices): ?array
    {
        $best = null;
        foreach ($this->sales($prices, config()->array('letaky.sources.rohlik.offer_sale_types')) as $sale) {
            if (($sale['triggerAmount'] ?? null) === self::SINGLE_PIECE && ($best === null || $this->money($sale['price'] ?? null) < $this->money($best['price'] ?? null))) {
                $best = $sale;
            }
        }

        return $best;
    }

    /**
     * Akce na množství (víc kusů), nebo null.
     *
     * @param  array<string, mixed>  $prices
     * @return array<string, mixed>|null
     */
    private function multipack(array $prices): ?array
    {
        foreach ($this->sales($prices, [config()->string('letaky.sources.rohlik.multibuy_sale_type')]) as $sale) {
            if (is_int($sale['triggerAmount'] ?? null) && $sale['triggerAmount'] > self::SINGLE_PIECE) {
                return $sale;
            }
        }

        return null;
    }

    /**
     * Aktivní akce produktu daných typů s cenou nižší než původní.
     *
     * @param  array<string, mixed>  $prices
     * @param  array<mixed>  $types
     * @return list<array<string, mixed>>
     */
    private function sales(array $prices, array $types): array
    {
        $sales = is_array($prices['sales'] ?? null) ? $prices['sales'] : [];

        return array_values(array_filter($sales, function (mixed $sale) use ($types): bool {
            if (! is_array($sale) || ($sale['active'] ?? false) !== true || ! in_array($sale['type'] ?? null, $types, true)) {
                return false;
            }
            $price = $this->money($sale['price'] ?? null);
            $original = $this->money($sale['originalPrice'] ?? null);

            return $price !== null && $original !== null && $price > 0 && $price < $original;
        }));
    }

    /**
     * Text akce na množství „od 3 ks: 9,90 Kč“ (výhodná cena kusu).
     *
     * @param  array<string, mixed>  $sale
     */
    private function multibuyText(array $sale): ?string
    {
        $price = $this->amount($this->money($sale['price'] ?? null));
        $quantity = $sale['triggerAmount'] ?? null;

        return $price === null || ! is_int($quantity) ? null
            : sprintf(config()->string('letaky.sources.rohlik.multibuy_text'), $quantity).self::MULTIBUY_PRICE_SEPARATOR.$this->formatter->format($price);
    }

    /**
     * Poslední místní den akce z `validTill` („2026-11-13T23:59:00+01:00“), null bez něj.
     *
     * @param  array<string, mixed>|null  $sale
     */
    private function validTo(?array $sale): ?CarbonImmutable
    {
        $validTill = $sale['validTill'] ?? null;

        return is_string($validTill) && $validTill !== '' ? $this->calendar->endFromInstant($validTill) : null;
    }

    /**
     * Je produkt vážené zboží (cena za odhadovaný kus, jednotka ceny kg)?
     *
     * @param  array<string, mixed>  $product
     */
    private function isWeighted(array $product): bool
    {
        return ($product['weightedItem'] ?? false) === true && ($product['unit'] ?? null) === config()->string('letaky.sources.rohlik.kilogram_unit');
    }

    /**
     * Částka v Kč z `{amount, currency}`, null když chybí.
     */
    private function money(mixed $money): ?float
    {
        $amount = is_array($money) ? ($money['amount'] ?? null) : null;

        return is_int($amount) || is_float($amount) ? (float) $amount : null;
    }

    /**
     * Cena v haléřích z částky v Kč; null, když chybí nebo není kladná.
     */
    private function amount(?float $crowns): ?int
    {
        if ($crowns === null) {
            return null;
        }

        $halers = $this->prices->fromFloat($crowns);

        return $halers > 0 ? $halers : null;
    }
}
