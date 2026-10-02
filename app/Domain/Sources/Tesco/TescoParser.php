<?php

/**
 * Převod odpovědí Tesco GraphQL (e-shop xapi a letáky leaflets-be) na nabídky (ZDROJE_DAT.md, Tesco).
 *
 * Pravidla převodu akce e-shopu:
 * - bez `price` (null) je to akce na množství nebo kombinaci („3 za cenu 2“, „MENU“) — Multibuy
 *   s cenou produktu `price.actual`
 * - `CLUBCARD_PRICING`: cena s kartou je jen v textu `description`, `afterDiscount` je běžná
 *   cena (R8) — LoyaltyOnly
 * - jinak `beforeDiscount` → `afterDiscount` je sleva, procento jen v textu („-53%, předtím…“)
 * - u zboží na váhu je `afterDiscount` cena za kg (`price.actual` je cena odhadovaného kusu),
 *   balení se pak bere z jednotky v `unitSellingInfo` („27,90 Kč/kg“)
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Tesco;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\PackageSize;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\StoreFormat;

final class TescoParser
{
    private const CLUBCARD_ATTRIBUTE = 'CLUBCARD_PRICING';

    /** Typ letáku => formát prodejny. Katalog (CAT) a jiné typy se nesledují. */
    private const LEAFLET_FORMATS = [
        'HM' => StoreFormat::Hypermarket,
        'SM' => StoreFormat::Supermarket,
    ];

    /** Procento slevy v popisu akce („-53%, předtím 59,90 Kč“). */
    private const DISCOUNT_PATTERN = '/-(\d{1,2})\s*%/';

    /** Jednotka ceny v `unitSellingInfo` („27,90 Kč/kg“). */
    private const SELLING_UNIT_PATTERN = '/\/\s*(\p{L}+)\s*$/u';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Platné letáky hypermarketů a supermarketů z odpovědi `leaflets`.
     *
     * @param  array<mixed>  $response
     * @return list<TescoLeaflet>
     *
     * @throws SourceResponseChanged
     */
    public function leaflets(array $response): array
    {
        $leaflets = [];
        foreach ($this->list($response['data']['leaflets']['items'] ?? null, 'leaflets.items') as $item) {
            $item = $this->array($item, 'leaflet');
            $format = self::LEAFLET_FORMATS[$item['type'] ?? null] ?? null;
            if ($format === null) {
                continue;
            }

            $leaflets[] = new TescoLeaflet(
                new LeafletData(
                    kind: LeafletKind::Leaflet,
                    externalId: (string) $this->scalar($item, 'id'),
                    format: $format,
                    validFrom: $this->calendar->startFromInstant($this->string($item, 'validFrom')),
                    validTo: $this->calendar->endFromInstant($this->string($item, 'validTo')),
                    sourceUrl: is_string($item['leafletUrl'] ?? null) ? $item['leafletUrl'] : null,
                ),
                slug: $this->string($item, 'slug'),
                type: $this->string($item, 'type'),
            );
        }

        return $leaflets;
    }

    /**
     * Koncovky ID produktů v letáku z odkazů do e-shopu (`addToBasketURL`).
     *
     * @param  array<mixed>  $response
     * @return array<string, true> Koncovka ID => true (rychlé vyhledání)
     *
     * @throws SourceResponseChanged
     */
    public function leafletProductSuffixes(array $response, int $suffixLength): array
    {
        $suffixes = [];
        $pages = $this->list($response['data']['leafletBySlug']['pages'] ?? null, 'leafletBySlug.pages');
        foreach ($pages as $page) {
            foreach ($this->list($this->array($page, 'page')['positions'] ?? [], 'positions') as $position) {
                foreach ($this->list($this->array($position, 'position')['products'] ?? [], 'products') as $product) {
                    $url = $this->array($product, 'product')['addToBasketURL'] ?? null;
                    if (is_string($url) && preg_match('/(\d+)$/', $url, $matches) === 1) {
                        $suffixes[substr($matches[1], -$suffixLength)] = true;
                    }
                }
            }
        }

        return $suffixes;
    }

    /**
     * Stránka akcí e-shopu: celkový počet a produkty s akcemi.
     *
     * @param  array<mixed>  $response  Odpověď xapi — pole s jedním výsledkem operace
     * @return array{total: int, products: list<array<string, mixed>>}
     *
     * @throws SourceResponseChanged
     */
    public function promotionsPage(array $response): array
    {
        $promotions = $this->array($response[0]['data']['promotions'] ?? null, 'data.promotions');
        $total = $promotions['info']['total'] ?? null;

        return [
            'total' => is_int($total) ? $total : throw SourceResponseChanged::because(Chain::Tesco, 'chybí info.total'),
            'products' => array_map(fn (mixed $product): array => $this->array($product, 'product'), $this->list($promotions['products'] ?? null, 'products')),
        ];
    }

    /**
     * Nabídky jednoho produktu e-shopu — jedna za každou jeho akci. Dostupnost (leták, jen online)
     * doplní zdroj podle letáků.
     *
     * @param  array<string, mixed>  $product
     * @return list<OfferData>
     *
     * @throws SourceResponseChanged
     */
    public function offers(array $product, string $productUrlBase): array
    {
        $offers = [];
        foreach ($this->list($product['promotions'] ?? null, 'promotions') as $promotion) {
            $offers[] = $this->offer($product, $this->array($promotion, 'promotion'), $productUrlBase);
        }

        return $offers;
    }

    /**
     * Jedna akce produktu podle pravidel v popisu třídy.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $promotion
     *
     * @throws SourceResponseChanged
     */
    private function offer(array $product, array $promotion, string $productUrlBase): OfferData
    {
        $id = $this->string($product, 'id');
        $title = $this->string($product, 'title');
        $description = Text::clean(is_string($promotion['description'] ?? null) ? $promotion['description'] : null);
        $clubcard = in_array(self::CLUBCARD_ATTRIBUTE, $this->list($promotion['attributes'] ?? [], 'attributes'), true);
        $promotionPrice = is_array($promotion['price'] ?? null) ? $promotion['price'] : null;
        $after = $this->optionalPrice($promotionPrice['afterDiscount'] ?? null);
        $before = $this->optionalPrice($promotionPrice['beforeDiscount'] ?? null);
        $cardPrice = $description === null ? null : $this->prices->findFirst($description);

        $originalPrice = null;
        $loyaltyPrice = null;
        $discountPercent = null;

        if ($after === null) {
            $offerType = OfferType::Multibuy;
            $price = $this->optionalPrice($this->array($product['price'] ?? null, 'product.price')['actual'] ?? null);
        } elseif ($clubcard && $cardPrice !== null && $cardPrice < $after) {
            $offerType = OfferType::LoyaltyOnly;
            $price = $after;
            $loyaltyPrice = $cardPrice;
        } elseif ($before !== null && $before > $after) {
            $offerType = OfferType::Discount;
            $price = $after;
            $originalPrice = $before;
            $discountPercent = $description !== null && preg_match(self::DISCOUNT_PATTERN, $description, $matches) === 1 ? (int) $matches[1] : null;
        } else {
            $offerType = OfferType::PromoPrice;
            $price = $after;
        }

        $sellingInfo = is_string($promotion['unitSellingInfo'] ?? null) ? $promotion['unitSellingInfo'] : '';

        return new OfferData(
            externalId: $id,
            name: Text::clean($title) ?? $id,
            offerType: $offerType,
            validFrom: $this->calendar->startFromInstant($this->string($promotion, 'startDate')),
            validTo: $this->calendar->endFromInstant($this->string($promotion, 'endDate')),
            raw: ['product' => array_diff_key($product, ['promotions' => true]), 'promotion' => $promotion],
            price: $price,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyaltyPrice,
            loyaltyProgram: $clubcard && ($loyaltyPrice !== null || $offerType === OfferType::Multibuy) ? LoyaltyProgram::Clubcard : null,
            discountPercent: $discountPercent,
            promotionText: $description,
            brand: Text::clean(is_string($product['brandName'] ?? null) ? $product['brandName'] : null),
            variantNote: $this->variants->detect($title, $description),
            package: $this->packages->findInText($title) ?? $this->sellingUnitPackage($sellingInfo),
            sourceCategory: Text::clean(is_string($product['departmentName'] ?? null) ? $product['departmentName'] : null),
            imageUrl: is_string($product['defaultImageUrl'] ?? null) ? $product['defaultImageUrl'] : null,
            sourceUrl: $productUrlBase.$id,
        );
    }

    /**
     * Balení o jedné jednotce ceny („…Kč/kg“ = 1 kg) pro zboží bez balení v názvu.
     */
    private function sellingUnitPackage(string $sellingInfo): ?PackageSize
    {
        return preg_match(self::SELLING_UNIT_PATTERN, $sellingInfo, $matches) === 1
            ? $this->packages->fromUnitLabel($matches[1])
            : null;
    }

    /**
     * Cena z JSON (číslo v Kč) v haléřích; null zůstává null.
     *
     * @throws SourceResponseChanged
     */
    private function optionalPrice(mixed $value): ?int
    {
        return match (true) {
            $value === null => null,
            is_int($value), is_float($value) => $this->prices->fromFloat((float) $value),
            default => throw SourceResponseChanged::because(Chain::Tesco, 'cena není číslo'),
        };
    }

    /**
     * Pole položek; jiný typ znamená změnu odpovědi.
     *
     * @return list<mixed>
     *
     * @throws SourceResponseChanged
     */
    private function list(mixed $value, string $field): array
    {
        return is_array($value) && array_is_list($value)
            ? $value
            : throw SourceResponseChanged::because(Chain::Tesco, "{$field} není seznam");
    }

    /**
     * Objekt JSON jako asociativní pole.
     *
     * @return array<string, mixed>
     *
     * @throws SourceResponseChanged
     */
    private function array(mixed $value, string $field): array
    {
        return is_array($value) ? $value : throw SourceResponseChanged::because(Chain::Tesco, "{$field} není objekt");
    }

    /**
     * Povinný textový údaj.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws SourceResponseChanged
     */
    private function string(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : throw SourceResponseChanged::because(Chain::Tesco, "chybí {$field}");
    }

    /**
     * Povinný údaj typu text nebo číslo (ID letáku).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws SourceResponseChanged
     */
    private function scalar(array $data, string $field): string|int
    {
        $value = $data[$field] ?? null;

        return is_string($value) || is_int($value) ? $value : throw SourceResponseChanged::because(Chain::Tesco, "chybí {$field}");
    }
}
