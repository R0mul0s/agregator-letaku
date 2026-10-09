<?php

/**
 * Převod odpovědi Penny product-discovery API na nabídky (ZDROJE_DAT.md, Penny).
 *
 * Ceny jsou v haléřích. Pravidla převodu:
 * - `price.loyalty` = cena s PENNY kartou; `regular` je pak cena bez karty — bez štítku
 *   `pt-aktion` je to běžná cena a akce platí jen s kartou
 * - `crossed` a `discountPercentage` = sleva z přeškrtnuté ceny na `regular`
 * - platnost `validityStart` / `validityEnd` jsou místní data
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Penny;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\CommercetoolsImage;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;

final class PennyApiParser
{
    /** Štítek akční ceny bez karty. */
    private const PROMOTION_TAG = 'pt-aktion';

    public function __construct(
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Stránka výpisu: celkový počet a produkty.
     *
     * @param  array<mixed>  $response
     * @return array{total: int, products: list<array<string, mixed>>}
     *
     * @throws SourceResponseChanged
     */
    public function page(array $response): array
    {
        $total = $response['total'] ?? null;
        $results = $response['results'] ?? null;

        if (! is_int($total) || ! is_array($results) || ! array_is_list($results)) {
            throw SourceResponseChanged::because(Chain::Penny, 'chybí total nebo results');
        }

        return [
            'total' => $total,
            'products' => array_values(array_filter($results, is_array(...))),
        ];
    }

    /**
     * Jedna nabídka; null, když produkt nemá cenu nebo platnost.
     *
     * @param  array<string, mixed>  $product
     */
    public function offer(array $product, string $productUrlBase): ?OfferData
    {
        $price = is_array($product['price'] ?? null) ? $product['price'] : [];
        $regular = $this->halers($price['regular']['value'] ?? null);
        $loyalty = $this->halers($price['loyalty']['value'] ?? null);
        $crossed = $this->halers($price['crossed'] ?? null);
        $tags = is_array($price['regular']['tags'] ?? null) ? $price['regular']['tags'] : [];
        $name = Text::clean(is_string($product['name'] ?? null) ? $product['name'] : null);
        $start = $price['validityStart'] ?? null;
        $end = $price['validityEnd'] ?? null;

        if ($regular === null || $name === null || ! is_string($start) || ! is_string($end) || ! is_string($product['sku'] ?? null)) {
            return null;
        }

        $originalPrice = null;
        $discountPercent = null;
        if ($loyalty !== null && ! in_array(self::PROMOTION_TAG, $tags, true)) {
            $offerType = OfferType::LoyaltyOnly;
        } elseif ($crossed !== null && $crossed > $regular) {
            $offerType = OfferType::Discount;
            $originalPrice = $crossed;
            $discountPercent = is_int($price['discountPercentage'] ?? null) ? abs($price['discountPercentage']) : null;
        } else {
            $offerType = OfferType::PromoPrice;
        }

        $description = Text::join(
            is_string($product['descriptionShort'] ?? null) ? $product['descriptionShort'] : null,
            is_string($product['descriptionLong'] ?? null) ? $product['descriptionLong'] : null,
        );
        $packageText = Text::join(
            is_string($product['amount'] ?? null) ? $product['amount'] : null,
            is_string($product['volumeLabelShort'] ?? null) ? $product['volumeLabelShort'] : null,
        );
        $slug = $product['slug'] ?? null;

        return new OfferData(
            externalId: $product['sku'],
            name: $name,
            offerType: $offerType,
            validFrom: $this->calendar->date($start),
            validTo: $this->calendar->date($end),
            raw: $product,
            price: $regular,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyalty,
            loyaltyProgram: $loyalty === null ? null : LoyaltyProgram::PennyKarta,
            discountPercent: $discountPercent,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceCategory: Text::clean(is_string($product['category'] ?? null) ? $product['category'] : null),
            imageUrl: CommercetoolsImage::fromProduct($product),
            sourceUrl: is_string($slug) ? $productUrlBase.$slug : null,
        );
    }

    /**
     * Cena z API — celé číslo v haléřích; jiná hodnota je null.
     */
    private function halers(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }
}
