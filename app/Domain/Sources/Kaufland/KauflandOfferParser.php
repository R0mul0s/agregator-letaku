<?php

/**
 * Převod stránky nabídky prodejny.kaufland.cz na nabídky (ZDROJE_DAT.md, Kaufland).
 *
 * Nabídka je v HTML jako JSON `window.SSR['<uuid>'] = {...}` komponenty OfferTemplate:
 * props.offerData.cycles[].categories[].offers[]. Pravidla převodu:
 * - název: `title` (často značka) + `subtitle`; bez `title` `detailTitle`, pokud to není
 *   reklamní „Tvoje cena s Kaufland XTRA“; bez názvu se položka přeskočí
 * - `customerType` KDN = cena s Kaufland Card v `loyaltyFormattedPrice`; bez `formattedPrice`
 *   platí akce jen s kartou a běžná cena je `loyaltyFormattedOldPrice`
 * - sleva jen s `formattedOldPrice` vyšší než cena; `smallPrice`/`specialItems`
 *   („AKCE! pouze“) bez původní ceny jsou PromoPrice (R8)
 * - stejná položka bývá ve více kategoriích (sortiment i „Superkauf“) — první výskyt vyhrává,
 *   sortimentní kategorie jsou v odpovědi první
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
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

final class KauflandOfferParser
{
    /** Začátek a konec vloženého JSON se stavem komponenty. */
    private const SSR_START = "window.SSR['";

    private const SSR_ASSIGNMENT = '] = ';

    private const SCRIPT_END = '</script>';

    private const OFFER_COMPONENT = 'OfferTemplate';

    /** Zákazník s Kaufland Card — cena v loyalty* polích. */
    private const LOYALTY_CUSTOMER_TYPE = 'KDN';

    /** Reklamní text místo názvu u nabídek s kartou („Tvoje cena s Kaufland XTRA“). */
    private const MARKETING_TITLE_PATTERN = '/kaufland/iu';

    /** Parametr stránky nabídky s kategorií (hodnota = `name` kategorie). */
    private const CATEGORY_PARAMETER = 'kloffer-category';

    /** Začátek textového fragmentu adresy (Scroll to Text Fragment). */
    private const TEXT_FRAGMENT = '#:~:text=';

    /** Předpona externího ID zdroje — jedna akční stránka na týden. */
    private const LEAFLET_ID_PREFIX = 'nabidka-';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Převede HTML stránky nabídky na dávku nabídek a zjistí, jestli je zveřejněný příští týden.
     *
     * @throws SourceResponseChanged
     */
    public function parse(string $html, string $sourceUrl): KauflandOfferPage
    {
        $props = $this->offerTemplateProps($html);
        $categories = $this->categories($props);

        $offers = [];
        foreach ($categories as $category) {
            foreach ($this->list($category['offers'] ?? null, 'offers') as $item) {
                $offer = $this->offer($this->array($item, 'offer'), $category, $sourceUrl);
                if ($offer !== null) {
                    $offers[$offer->key()] ??= $offer;
                }
            }
        }

        $nextWeekDates = $props['weekData']['nextWeekDates'] ?? [];

        return new KauflandOfferPage(
            new SourceBatch($this->leaflet($categories, $sourceUrl), array_values($offers)),
            nextWeekPublished: is_array($nextWeekDates) && $nextWeekDates !== [],
        );
    }

    /**
     * Najde mezi vloženými stavy komponent tu s nabídkou a vrátí její props.
     *
     * @return array<string, mixed>
     *
     * @throws SourceResponseChanged
     */
    private function offerTemplateProps(string $html): array
    {
        $offset = 0;
        while (($start = strpos($html, self::SSR_START, $offset)) !== false) {
            $jsonStart = strpos($html, self::SSR_ASSIGNMENT, $start);
            $jsonEnd = $jsonStart === false ? false : strpos($html, self::SCRIPT_END, $jsonStart);
            if ($jsonStart === false || $jsonEnd === false) {
                break;
            }

            $json = rtrim(substr($html, $jsonStart + strlen(self::SSR_ASSIGNMENT), $jsonEnd - $jsonStart - strlen(self::SSR_ASSIGNMENT)), "; \n\r\t");
            $state = json_decode($json, true);
            if (is_array($state) && ($state['component'] ?? null) === self::OFFER_COMPONENT && is_array($state['props'] ?? null)) {
                return $state['props'];
            }

            $offset = $jsonEnd;
        }

        throw SourceResponseChanged::because(Chain::Kaufland, 'chybí stav komponenty '.self::OFFER_COMPONENT);
    }

    /**
     * Všechny kategorie a kampaně ze všech cyklů.
     *
     * @param  array<string, mixed>  $props
     * @return list<array<string, mixed>>
     *
     * @throws SourceResponseChanged
     */
    private function categories(array $props): array
    {
        $categories = [];
        foreach ($this->list($props['offerData']['cycles'] ?? null, 'offerData.cycles') as $cycle) {
            foreach ($this->list($this->array($cycle, 'cycle')['categories'] ?? null, 'categories') as $category) {
                $categories[] = $this->array($category, 'category');
            }
        }

        return $categories === [] ? throw SourceResponseChanged::because(Chain::Kaufland, 'žádné kategorie') : $categories;
    }

    /**
     * Týden nabídky podle sortimentních kategorií (main) — kampaně mívají delší platnost.
     *
     * @param  list<array<string, mixed>>  $categories
     *
     * @throws SourceResponseChanged
     */
    private function leaflet(array $categories, string $sourceUrl): LeafletData
    {
        $main = array_filter($categories, fn (array $category): bool => ($category['main'] ?? false) === true) ?: $categories;
        $starts = array_map(fn (array $category): string => $this->string($category, 'dateFrom'), $main);
        $ends = array_map(fn (array $category): string => $this->string($category, 'dateTo'), $main);
        if ($starts === [] || $ends === []) {
            throw SourceResponseChanged::because(Chain::Kaufland, 'žádné kategorie');
        }

        $from = min($starts);
        $to = max($ends);

        return new LeafletData(
            kind: LeafletKind::Web,
            externalId: self::LEAFLET_ID_PREFIX.$from,
            validFrom: $this->calendar->date($from),
            validTo: $this->calendar->date($to),
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * Jedna nabídka; null, když nemá název ani žádnou cenu.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $category
     *
     * @throws SourceResponseChanged
     */
    private function offer(array $item, array $category, string $sourceUrl): ?OfferData
    {
        $title = Text::clean($this->optionalString($item, 'title'));
        $subtitle = Text::clean($this->optionalString($item, 'subtitle'));
        $detailTitle = Text::clean($this->optionalString($item, 'detailTitle'));
        $description = Text::clean($this->optionalString($item, 'detailDescription'));

        $name = $title !== null
            ? Text::join($title, $subtitle)
            : ($detailTitle !== null && preg_match(self::MARKETING_TITLE_PATTERN, $detailTitle) !== 1 ? $detailTitle : null);

        $price = $this->prices->parseOptional($this->optionalString($item, 'formattedPrice'));
        $originalPrice = $this->prices->parseOptional($this->optionalString($item, 'formattedOldPrice'));
        $loyaltyPrice = ($item['customerType'] ?? null) === self::LOYALTY_CUSTOMER_TYPE
            ? $this->prices->parseOptional($this->optionalString($item, 'loyaltyFormattedPrice'))
            : null;

        if ($name === null || ($price === null && $loyaltyPrice === null)) {
            return null;
        }

        if ($price === null) {
            $offerType = OfferType::LoyaltyOnly;
            $price = $this->prices->parseOptional($this->optionalString($item, 'loyaltyFormattedOldPrice'));
            $originalPrice = null;
        } elseif ($originalPrice !== null && $originalPrice > $price) {
            $offerType = OfferType::Discount;
        } else {
            $offerType = OfferType::PromoPrice;
            $originalPrice = null;
        }

        $discount = $item['discount'] ?? null;
        $unit = $this->optionalString($item, 'unit');

        return new OfferData(
            externalId: $this->string($item, 'klNr'),
            name: $name,
            offerType: $offerType,
            validFrom: $this->calendar->date($this->string($item, 'dateFrom')),
            validTo: $this->calendar->date($this->string($item, 'dateTo')),
            raw: $item,
            price: $price,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyaltyPrice,
            loyaltyProgram: $loyaltyPrice === null ? null : LoyaltyProgram::KauflandCard,
            discountPercent: $offerType === OfferType::Discount && is_int($discount) && $discount > 0 ? $discount : null,
            description: $description,
            variantNote: $this->variants->detect($title, $subtitle, $description),
            packageText: Text::clean($unit),
            package: $this->packages->parse($unit),
            sourceCategory: Text::clean($this->optionalString($category, 'displayName')),
            imageUrl: $this->optionalString($item, 'listImage'),
            sourceUrl: $this->offerUrl($sourceUrl, $category, $title ?? $detailTitle ?? $name),
        );
    }

    /**
     * Odkaz na akci: detail akce nemá vlastní adresu (otevírá se jen v okně stránky), proto
     * stránka její kategorie a textový fragment s nadpisem dlaždice — prohlížeč na akci odroluje
     * a zvýrazní ji. Ve fragmentu musí být kódovaná i pomlčka (oddělovač syntaxe fragmentu).
     *
     * @param  string  $pageUrl  Stránka nabídky týdne (s parametrem kloffer-week)
     * @param  array<string, mixed>  $category
     */
    private function offerUrl(string $pageUrl, array $category, string $tileTitle): string
    {
        $categoryName = $this->optionalString($category, 'name');
        $url = $categoryName === null ? $pageUrl : $pageUrl.'&'.http_build_query([self::CATEGORY_PARAMETER => $categoryName]);

        return $url.self::TEXT_FRAGMENT.str_replace('-', '%2D', rawurlencode($tileTitle));
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
            : throw SourceResponseChanged::because(Chain::Kaufland, "{$field} není seznam");
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
        return is_array($value) ? $value : throw SourceResponseChanged::because(Chain::Kaufland, "{$field} není objekt");
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

        return is_string($value) && $value !== '' ? $value : throw SourceResponseChanged::because(Chain::Kaufland, "chybí {$field}");
    }

    /**
     * Nepovinný textový údaj.
     *
     * @param  array<string, mixed>  $data
     */
    private function optionalString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
