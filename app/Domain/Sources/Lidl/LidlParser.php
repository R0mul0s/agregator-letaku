<?php

/**
 * Převod stránek lidl.cz na nabídky (ZDROJE_DAT.md, Lidl).
 *
 * Úvodní stránka odkazuje na kampaně týdne (`/c/{slug}/a{id}`). Na stránce kampaně má každá
 * dlaždice JSON v atributu `data-grid-data`. Pravidla převodu:
 * - jen kategorie z konfigurace (`Food`); „Ceny v klidu“ nejsou akce (R8)
 * - Lidl Plus: cena s aplikací v `lidlPlus[0].price.price`, cena bez ní jen někdy v textu
 *   `prefix` („29,90 Kč bez Lidl Plus“); bez ní platí akce jen s aplikací a běžná cena je `oldPrice`
 * - `discountText` „-25%“ = sleva; „1+1 zdarma“ = akce na více kusů (cena je za kus při koupi
 *   dvou, běžná za kus je `oldPrice`); „Super cena“ a „Ušetřete* 24%“ (úspora na ceně
 *   za jednotku) slevy nejsou (R8)
 * - platnost `storeStartDate` / `storeEndDate` (unix); položky „Pouze v prodejnách“ bez data
 *   převezmou platnost ostatních akcí stránky
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Lidl;

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
use Carbon\CarbonImmutable;

final class LidlParser
{
    /** Odkaz na kampaň v HTML úvodní stránky. */
    private const CAMPAIGN_LINK_PATTERN = '#href="(/c/(?:[a-z0-9-]+/)*([a-z0-9-]+)/a(\d+))"#';

    /** JSON dlaždice produktu v atributu (s HTML entitami). */
    private const GRID_DATA_PATTERN = '/data-grid-data="([^"]*)"/';

    /** Sleva podle obchodu („-25%“). */
    private const DISCOUNT_PATTERN = '/^-\s*(\d{1,2})\s*%$/';

    /** Akce na více kusů („1+1 zdarma“, „2+1“). */
    private const MULTIBUY_PATTERN = '/\d\s*\+\s*\d/';

    /** Cena za jednotku v údaji o balení („100 g = 28,52 Kč“, „1 kg = 59,01 Kč“, „… Kč/PP“). */
    private const UNIT_PRICE_PATTERN = '/\d+(?:[.,]\d+)?\s*\p{L}+\s*=\s*[\d\s.,]+\s*Kč(?:\/\p{L}+)?/u';

    /** Oddělovač cesty kategorie („…/Mléko a smetana“). */
    private const CATEGORY_SEPARATOR = '/';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Kampaně odkazované z úvodní stránky: cesta => ID kampaně, bez vyloučených slugů.
     *
     * @param  list<string>  $excludedSlugs
     * @return array<string, string>
     *
     * @throws SourceResponseChanged
     */
    public function campaigns(string $html, array $excludedSlugs): array
    {
        preg_match_all(self::CAMPAIGN_LINK_PATTERN, $html, $matches, PREG_SET_ORDER);

        $campaigns = [];
        foreach ($matches as [, $path, $slug, $id]) {
            if (! in_array($slug, $excludedSlugs, true)) {
                $campaigns[$path] = 'a'.$id;
            }
        }

        return $campaigns === [] ? throw SourceResponseChanged::because(Chain::Lidl, 'úvodní stránka neodkazuje na žádnou kampaň') : $campaigns;
    }

    /**
     * Nabídky stránky kampaně z povolených kategorií; bez produktů prázdné pole.
     *
     * @param  list<string>  $categories
     * @return list<OfferData>
     */
    public function offers(string $html, array $categories, string $baseUrl): array
    {
        $products = array_filter(
            $this->products($html),
            fn (array $product): bool => in_array($product['category'] ?? null, $categories, true),
        );

        // Platnost stránky pro položky bez data („Pouze v prodejnách“)
        $dated = array_values(array_filter(array_map($this->validity(...), $products)));
        $fallback = $dated === [] ? null : array_reduce(
            $dated,
            fn (array $range, array $validity): array => [$range[0]->min($validity[0]), $range[1]->max($validity[1])],
            $dated[0],
        );

        $offers = [];
        foreach ($products as $product) {
            $validity = $this->validity($product) ?? $fallback;
            $offer = $validity === null ? null : $this->offer($product, $validity, $baseUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * JSON všech dlaždic produktů na stránce.
     *
     * @return list<array<string, mixed>>
     */
    private function products(string $html): array
    {
        preg_match_all(self::GRID_DATA_PATTERN, $html, $matches);

        $products = [];
        foreach ($matches[1] as $encoded) {
            $data = json_decode(html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5), true);
            if (is_array($data) && isset($data['productId'])) {
                $products[] = $data;
            }
        }

        return $products;
    }

    /**
     * Platnost položky v prodejnách jako místní data; null, když ji položka neuvádí.
     *
     * @param  array<string, mixed>  $product
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function validity(array $product): ?array
    {
        $start = $product['storeStartDate'] ?? null;
        $end = $product['storeEndDate'] ?? null;

        if (! is_int($start) || ! is_int($end)) {
            return null;
        }

        return [
            $this->calendar->startFromInstant('@'.$start),
            $this->calendar->endFromInstant('@'.$end),
        ];
    }

    /**
     * Jedna nabídka podle pravidel v popisu třídy; null, když nemá cenu.
     *
     * @param  array<string, mixed>  $product
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $product, array $validity, string $baseUrl): ?OfferData
    {
        $lidlPlus = $product['lidlPlus'][0]['price'] ?? null;
        $priceBlock = is_array($lidlPlus) ? $lidlPlus : ($product['price'] ?? []);
        if (! is_array($priceBlock) || ! is_numeric($priceBlock['price'] ?? null)) {
            return null;
        }

        $shownPrice = $this->prices->fromFloat((float) $priceBlock['price']);
        $oldPrice = is_numeric($priceBlock['oldPrice'] ?? null) && $priceBlock['oldPrice'] > 0
            ? $this->prices->fromFloat((float) $priceBlock['oldPrice'])
            : null;
        $discountText = Text::clean(is_string($priceBlock['discount']['discountText'] ?? null) ? $priceBlock['discount']['discountText'] : null);

        $loyaltyPrice = null;
        $originalPrice = null;
        $discountPercent = null;

        if (is_array($lidlPlus)) {
            $loyaltyPrice = $shownPrice;
            $price = is_string($lidlPlus['prefix'] ?? null) ? $this->prices->findFirst($lidlPlus['prefix']) : null;
            if ($price === null) {
                $offerType = OfferType::LoyaltyOnly;
                $price = $oldPrice;
            } elseif ($oldPrice !== null && $oldPrice > $price) {
                $offerType = OfferType::Discount;
                $originalPrice = $oldPrice;
            } else {
                $offerType = OfferType::PromoPrice;
            }
        } elseif ($discountText !== null && preg_match(self::MULTIBUY_PATTERN, $discountText) === 1) {
            $offerType = OfferType::Multibuy;
            $price = $oldPrice ?? $shownPrice;
        } elseif ($discountText !== null && preg_match(self::DISCOUNT_PATTERN, $discountText, $matches) === 1 && $oldPrice !== null && $oldPrice > $shownPrice) {
            $offerType = OfferType::Discount;
            $price = $shownPrice;
            $originalPrice = $oldPrice;
            $discountPercent = (int) $matches[1];
        } else {
            $offerType = OfferType::PromoPrice;
            $price = $shownPrice;
        }

        $name = Text::clean(is_string($product['fullTitle'] ?? null) ? $product['fullTitle'] : null);
        if ($name === null) {
            return null;
        }

        $description = $this->description($product);
        $packageText = $this->packageText($priceBlock);
        $url = $product['canonicalUrl'] ?? null;

        return new OfferData(
            externalId: (string) $product['productId'],
            name: $name,
            offerType: $offerType,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: $product,
            price: $price,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyaltyPrice,
            loyaltyProgram: $loyaltyPrice === null ? null : LoyaltyProgram::LidlPlus,
            discountPercent: $discountPercent,
            promotionText: $discountText,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceCategory: $this->category($product),
            imageUrl: is_string($product['image'] ?? null) ? $product['image'] : null,
            sourceUrl: is_string($url) ? $baseUrl.$url : null,
        );
    }

    /**
     * Popis z odrážek `keyfacts.description` (HTML) jako prostý text.
     *
     * @param  array<string, mixed>  $product
     */
    private function description(array $product): ?string
    {
        $html = $product['keyfacts']['description'] ?? null;

        return is_string($html)
            ? Text::clean(html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5))
            : null;
    }

    /**
     * Balení z `basePrice.text` bez ceny za jednotku: „210 g, 100 g = 28,52 Kč“ → „210 g“,
     * „500 g - balení,1 kg = 49,80 Kč“ → „500 g - balení“; jen „1 kg = 59,01 Kč“ balení neuvádí.
     *
     * @param  array<mixed>  $priceBlock
     */
    private function packageText(array $priceBlock): ?string
    {
        $text = $priceBlock['basePrice']['text'] ?? null;

        return is_string($text) ? Text::clean(trim(preg_replace(self::UNIT_PRICE_PATTERN, '', $text) ?? '', " ,;\t\n")) : null;
    }

    /**
     * Poslední úroveň kategorie Lidlu („Mléko a smetana“).
     *
     * @param  array<string, mixed>  $product
     */
    private function category(array $product): ?string
    {
        $path = $product['keyfacts']['wonCategoryPrimary'] ?? null;
        if (! is_string($path)) {
            return null;
        }

        $parts = explode(self::CATEGORY_SEPARATOR, $path);

        return Text::clean(end($parts) ?: null);
    }
}
