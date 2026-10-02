<?php

/**
 * Převod odpovědí API Globusu na nabídky (ZDROJE_DAT.md, Globus; R46).
 *
 * Akce s cenou je položka katalogu akcí (`actionProductsCatalog`). Pravidla převodu:
 * - ceny jsou desetinná čísla v Kč (`productInHouse.actualPrice`, `originalPrice`)
 * - původní cena = sleva (R8); bez ní je to akční cena, která nemusí být nižší než obvykle
 * - `bonusProgramPrice.actualPrice` = cena s aplikací Můj Globus, jen když je nižší
 * - platnost má místní posun („2026-10-06T23:59:59.000+02:00“) → místní datum (R7)
 * - popis bere z položky letáku (`actionProducts`, „různé druhy“) spárované podle EAN —
 *   popis z katalogu je dlouhý reklamní text, ve kterém by hlídání chytalo cizí slova
 * - balení ze `sellUnitSizeText`; zboží na váhu ho nemá, cena je pak za `unitAmount` `unitId` (1 kg)
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;

final class GlobusParser
{
    /** Jednotky, u kterých `unitAmount` + `unitId` udává balení (zboží na váhu bez `sellUnitSizeText`). */
    private const MEASURED_UNITS = ['g', 'kg', 'ml', 'l'];

    /** Odrážky a konce řádků v popisu položky letáku („- dámská\n- různé barvy“). */
    private const DESCRIPTION_LINE_PATTERN = '/\s*\n\s*-?\s*|^\s*-\s*/u';

    /** Oddělovač řádků popisu po sloučení. */
    private const DESCRIPTION_SEPARATOR = ', ';

    /** Části položky katalogu, které se do `raw` neukládají — dlouhé texty bez užitku pro akce. */
    private const RAW_OMITTED_KEYS = ['description', 'contains', 'allergens', 'nutritionValues', 'storage', 'regulatedName'];

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Stránka katalogu akcí: produkty a jestli následuje další stránka.
     *
     * @param  array<mixed>  $response
     * @return array{products: list<array<string, mixed>>, hasMore: bool}
     *
     * @throws SourceResponseChanged
     */
    public function catalogPage(array $response): array
    {
        $products = $response['products'] ?? null;
        $hasMore = $response['paginationShowMore'] ?? null;

        if (! is_array($products) || ! array_is_list($products) || ! is_bool($hasMore)) {
            throw SourceResponseChanged::because(Chain::Globus, 'katalog akcí nemá products nebo paginationShowMore');
        }

        return ['products' => array_values(array_filter($products, is_array(...))), 'hasMore' => $hasMore];
    }

    /**
     * Stránka položek letáku.
     *
     * @param  array<mixed>  $response
     * @return list<array<string, mixed>>
     *
     * @throws SourceResponseChanged
     */
    public function leafletItemsPage(array $response): array
    {
        $items = $response['actionProducts'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw SourceResponseChanged::because(Chain::Globus, 'položky letáku nemají actionProducts');
        }

        return array_values(array_filter($items, is_array(...)));
    }

    /**
     * Popisy položek letáku podle EAN; první neprázdný popis vyhrává.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, string>
     */
    public function descriptionsByEan(array $items): array
    {
        $descriptions = [];
        foreach ($items as $item) {
            $ean = $item['ean'] ?? null;
            $description = $this->description($item['description'] ?? null);
            if (is_string($ean) && $description !== null) {
                $descriptions[$ean] ??= $description;
            }
        }

        return $descriptions;
    }

    /**
     * Jedna nabídka; null, když položka není akce (jiný typ ceny), je ze skupiny zboží, která
     * se nesleduje, nebo nemá cenu, název či platnost.
     *
     * @param  array<string, mixed>  $product  Položka katalogu akcí
     * @param  array<string, string>  $descriptions  Popisy z letáku podle EAN
     */
    public function offer(array $product, array $descriptions): ?OfferData
    {
        $inHouse = is_array($product['productInHouse'] ?? null) ? $product['productInHouse'] : [];
        $price = $this->halers($inHouse['actualPrice'] ?? null);
        $name = Text::clean(is_string($product['name'] ?? null) ? $product['name'] : null);
        $from = $inHouse['priceValidFrom'] ?? null;
        $to = $inHouse['priceValidTo'] ?? null;
        $id = $product['vanr'] ?? null;

        if (! in_array($inHouse['priceType'] ?? null, config()->array('letaky.sources.globus.action_price_types'), true)
            || $this->isExcludedWareGroup($product['warengroup'] ?? null)
            || $price === null || $name === null || ! is_string($from) || ! is_string($to) || ! is_string($id)) {
            return null;
        }

        $original = $this->halers($inHouse['originalPrice'] ?? null);
        $isDiscount = $original !== null && $original > $price;
        $bonus = $this->halers(is_array($inHouse['bonusProgramPrice'] ?? null) ? ($inHouse['bonusProgramPrice']['actualPrice'] ?? null) : null);
        $loyaltyPrice = $bonus !== null && $bonus < $price ? $bonus : null;
        $discount = $inHouse['discountPercentage'] ?? null;

        $description = $this->leafletDescription($product, $descriptions);
        $packageText = $this->packageText($product);
        $placement = is_array($inHouse['placements'][0] ?? null) ? $inHouse['placements'][0] : [];
        $brand = is_array($product['commonBrand'] ?? null) ? ($product['commonBrand']['name'] ?? null) : null;

        return new OfferData(
            externalId: $id,
            name: $name,
            offerType: $isDiscount ? OfferType::Discount : OfferType::PromoPrice,
            validFrom: $this->calendar->startFromInstant($from),
            validTo: $this->calendar->endFromInstant($to),
            raw: array_diff_key($product, array_flip(self::RAW_OMITTED_KEYS)),
            price: $price,
            originalPrice: $isDiscount ? $original : null,
            loyaltyPrice: $loyaltyPrice,
            loyaltyProgram: $loyaltyPrice === null ? null : LoyaltyProgram::MujGlobus,
            discountPercent: $isDiscount && (is_int($discount) || is_float($discount)) ? (int) round($discount) : null,
            brand: Text::clean(is_string($brand) ? $brand : null),
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceCategory: Text::clean(is_string($placement['category'] ?? null) ? $placement['category'] : null),
            imageUrl: is_string($product['imgThumbnail'] ?? null) ? $product['imgThumbnail'] : null,
        );
    }

    /**
     * Patří zboží do skupiny, která se nesleduje (oblečení, obuv, bytový textil)?
     */
    private function isExcludedWareGroup(mixed $wareGroup): bool
    {
        return is_string($wareGroup) && in_array(
            substr($wareGroup, 0, config()->integer('letaky.sources.globus.ware_group_prefix_length')),
            config()->array('letaky.sources.globus.excluded_ware_groups'),
            true,
        );
    }

    /**
     * Popis položky letáku se stejným EAN.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, string>  $descriptions
     */
    private function leafletDescription(array $product, array $descriptions): ?string
    {
        foreach (is_array($product['ean'] ?? null) ? $product['ean'] : [] as $ean) {
            if (is_string($ean) && isset($descriptions[$ean])) {
                return $descriptions[$ean];
            }
        }

        return null;
    }

    /**
     * Údaj o balení; zboží na váhu bez `sellUnitSizeText` dostane „1 kg“ z `unitAmount` a `unitId`.
     *
     * @param  array<string, mixed>  $product
     */
    private function packageText(array $product): ?string
    {
        $text = Text::clean(is_string($product['sellUnitSizeText'] ?? null) ? $product['sellUnitSizeText'] : null);
        if ($text !== null) {
            return $text;
        }

        $amount = $product['unitAmount'] ?? null;
        $unit = $product['unitId'] ?? null;
        if ((is_int($amount) || is_float($amount)) && $amount > 0 && in_array($unit, self::MEASURED_UNITS, true)) {
            return str_replace('.', ',', (string) $amount).' '.$unit;
        }

        return null;
    }

    /**
     * Popis z letáku jako jeden řádek: „- dámská\n- různé barvy“ → „dámská, různé barvy“.
     */
    private function description(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $lines = preg_split(self::DESCRIPTION_LINE_PATTERN, trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return Text::clean(implode(self::DESCRIPTION_SEPARATOR, array_map(trim(...), $lines)));
    }

    /**
     * Cena z API v Kč na haléře; nula, záporná nebo chybějící je null.
     */
    private function halers(mixed $crowns): ?int
    {
        return (is_int($crowns) || is_float($crowns)) && $crowns > 0 ? $this->prices->fromFloat((float) $crowns) : null;
    }
}
