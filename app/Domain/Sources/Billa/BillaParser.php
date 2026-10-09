<?php

/**
 * Převod odpovědi Billa product-discovery API na nabídky (ZDROJE_DAT.md, Billa; R48).
 *
 * Stejná platforma REWE jako Penny, ceny v haléřích. Pravidla převodu:
 * - akce = štítek `pt-aktion` nebo `pt-multi` u `price.regular` (`pt-abverkauf` = doprodej, ne)
 * - `standard.value` (= `crossed`) vyšší než `regular.value` = sleva; jinak akční cena (R8)
 * - `pt-multi` s množstvím od 2 ks („cena 1ks při koupi 3ks“, „od 2 ks“) = akce na množství:
 *   cena je běžná cena kusu, výhodná cena kusu v textu akce (jako Tesco a Lidl)
 * - `price.loyalty` se štítkem `pt-loyalclub` = cena s BILLA Klubem, jen když je nižší;
 *   bez akčního štítku je to akce jen s Klubem a `regular` je běžná cena
 * - zboží na kusy vážené (`weightPieceArticle`): `value` je cena odhadovaného kusu, bere se
 *   cena za kg (`perStandardizedQuantity`); vážené zboží (`weightArticle`) má `value` za kg
 * - API nemá platnost akce — dodá ji zdroj (akční týden)
 * - každá akce nese předběžné ID stejné akce z PDF letáku (`supersedes`, R89) — import ji převezme
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Parsing\CommercetoolsImage;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;

final class BillaParser
{
    /** Štítek akce na množství. */
    private const MULTIBUY_TAG = 'pt-multi';

    /** Oddělovač textu akce na množství a výhodné ceny kusu („cena 1ks při koupi 3ks: 9,93 Kč“). */
    private const MULTIBUY_PRICE_SEPARATOR = ': ';

    public function __construct(
        private readonly PackageParser $packages,
        private readonly PriceFormatter $prices,
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
            throw SourceResponseChanged::because(Chain::Billa, 'chybí total nebo results');
        }

        return ['total' => $total, 'products' => array_values(array_filter($results, is_array(...)))];
    }

    /**
     * Jedna nabídka; null, když produkt není v akci ani nemá nižší cenu s Klubem.
     *
     * @param  array<string, mixed>  $product
     * @param  CarbonImmutable  $validFrom  Začátek akčního týdne (místní datum)
     * @param  CarbonImmutable  $validTo  Konec akčního týdne (místní datum včetně)
     */
    public function offer(array $product, CarbonImmutable $validFrom, CarbonImmutable $validTo, string $productUrlBase): ?OfferData
    {
        $price = is_array($product['price'] ?? null) ? $product['price'] : [];
        $regular = is_array($price['regular'] ?? null) ? $price['regular'] : [];
        $tags = is_array($regular['tags'] ?? null) ? $regular['tags'] : [];
        $byWeight = ($product['weightPieceArticle'] ?? false) === true;
        $name = Text::clean(is_string($product['name'] ?? null) ? $product['name'] : null);
        $sku = $product['sku'] ?? null;

        $current = $this->amount($regular, $byWeight);
        $standard = $this->amount(is_array($price['standard'] ?? null) ? $price['standard'] : [], $byWeight);
        $loyalty = $this->loyalty($price, $byWeight);

        if ($current === null || $name === null || ! is_string($sku)) {
            return null;
        }

        $isPromotion = array_intersect($tags, config()->array('letaky.sources.billa.promotion_tags')) !== [];
        $promotionText = null;
        $originalPrice = null;
        $discountPercent = null;

        if ($isPromotion && in_array(self::MULTIBUY_TAG, $tags, true) && $this->quantity($regular) >= config()->integer('letaky.sources.billa.multibuy_min_quantity')) {
            $offerType = OfferType::Multibuy;
            // Bez Text::join — ten by nezlomitelnou mezeru v ceně („9,93 Kč“) nahradil obyčejnou
            $condition = Text::clean(is_string($regular['promotionText'] ?? null) ? $regular['promotionText'] : null);
            $promotionText = ($condition === null ? '' : $condition.self::MULTIBUY_PRICE_SEPARATOR).$this->prices->format($current);
            $current = $standard ?? $current;
        } elseif ($isPromotion && $standard !== null && $standard > $current) {
            $offerType = OfferType::Discount;
            $originalPrice = $standard;
            $discount = $price['discountPercentage'] ?? null;
            $discountPercent = is_int($discount) ? abs($discount) : null;
        } elseif ($isPromotion) {
            $offerType = OfferType::PromoPrice;
        } elseif ($loyalty !== null && $loyalty < $current) {
            $offerType = OfferType::LoyaltyOnly;
        } else {
            return null;
        }

        $loyaltyPrice = $loyalty !== null && $loyalty < $current ? $loyalty : null;
        $packageText = $this->packageText($product);
        $slug = $product['slug'] ?? null;
        $badges = is_array($product['badges'] ?? null) ? $product['badges'] : [];
        $brand = is_array($product['brand'] ?? null) ? ($product['brand']['name'] ?? null) : null;

        return new OfferData(
            externalId: $sku,
            name: $name,
            offerType: $offerType,
            validFrom: $validFrom,
            validTo: $validTo,
            raw: $product,
            price: $current,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyaltyPrice,
            loyaltyProgram: $loyaltyPrice === null ? null : LoyaltyProgram::BillaKlub,
            discountPercent: $discountPercent,
            promotionText: $promotionText,
            brand: Text::clean(is_string($brand) ? $brand : null),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            onlineOnly: in_array(config()->string('letaky.sources.billa.eshop_only_badge'), $badges, true),
            sourceCategory: Text::clean(is_string($product['category'] ?? null) ? $product['category'] : null),
            imageUrl: CommercetoolsImage::fromProduct($product),
            sourceUrl: is_string($slug) ? $productUrlBase.$slug : null,
            // Akce z PDF letáku s jinou platností než akční týden je uložená pod předběžným ID (R89)
            supersedes: config()->string('letaky.sources.billa.pdf_provisional_prefix').$sku,
        );
    }

    /**
     * Produkt katalogu pro párování s letákem (R89), i bez akce; null bez ceny, názvu nebo SKU.
     * Běžná cena je přeškrtnutá cena (`standard`), když je vyšší než aktuální, jinak aktuální cena.
     *
     * @param  array<string, mixed>  $product
     */
    public function catalogProduct(array $product, string $productUrlBase): ?BillaCatalogProduct
    {
        $price = is_array($product['price'] ?? null) ? $product['price'] : [];
        $byWeight = ($product['weightPieceArticle'] ?? false) === true;
        $current = $this->amount(is_array($price['regular'] ?? null) ? $price['regular'] : [], $byWeight);
        $standard = $this->amount(is_array($price['standard'] ?? null) ? $price['standard'] : [], $byWeight);
        $name = Text::clean(is_string($product['name'] ?? null) ? $product['name'] : null);
        $sku = $product['sku'] ?? null;
        if ($current === null || $name === null || ! is_string($sku)) {
            return null;
        }

        $packageText = $this->packageText($product);
        $package = $this->packages->parse($packageText);
        $slug = $product['slug'] ?? null;
        $brand = is_array($product['brand'] ?? null) ? ($product['brand']['name'] ?? null) : null;

        return new BillaCatalogProduct(
            sku: $sku,
            name: $name,
            brand: Text::clean(is_string($brand) ? $brand : null),
            packageText: $packageText,
            quantity: $package?->quantity,
            unit: $package?->unit->value,
            usualPrice: $standard !== null && $standard > $current ? $standard : $current,
            category: Text::clean(is_string($product['category'] ?? null) ? $product['category'] : null),
            imageUrl: CommercetoolsImage::fromProduct($product),
            sourceUrl: is_string($slug) ? $productUrlBase.$slug : null,
        );
    }

    /**
     * Cena z části `price` (regular, standard, loyalty): u zboží na kusy vážené za kg.
     *
     * @param  array<mixed>  $part
     */
    private function amount(array $part, bool $byWeight): ?int
    {
        $value = $byWeight ? ($part['perStandardizedQuantity'] ?? null) : ($part['value'] ?? null);

        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * Cena s BILLA Klubem, nebo null.
     *
     * @param  array<mixed>  $price
     */
    private function loyalty(array $price, bool $byWeight): ?int
    {
        $loyalty = is_array($price['loyalty'] ?? null) ? $price['loyalty'] : [];
        $tags = is_array($loyalty['tags'] ?? null) ? $loyalty['tags'] : [];

        return in_array(config()->string('letaky.sources.billa.loyalty_tag'), $tags, true) ? $this->amount($loyalty, $byWeight) : null;
    }

    /**
     * Od kolika kusů platí akce na množství (0, když ho API neuvádí).
     *
     * @param  array<mixed>  $regular
     */
    private function quantity(array $regular): float
    {
        $quantity = $regular['promotionQuantity'] ?? null;

        return is_int($quantity) || is_float($quantity) ? (float) $quantity : 0.0;
    }

    /**
     * Balení „0,7 l“, „250 g“, „10 ks“ z `amount` a `volumeLabelShort`.
     *
     * @param  array<string, mixed>  $product
     */
    private function packageText(array $product): ?string
    {
        $amount = $product['amount'] ?? null;
        $unit = $product['volumeLabelShort'] ?? null;

        return is_string($amount) && is_string($unit) ? Text::join(str_replace('.', ',', $amount), $unit) : null;
    }
}
