<?php

/**
 * Akce z PDF potravinového letáku Lidlu bez LLM (R86) — dlaždice z textu s polohou
 * (`pdftotext -bbox-layout`, ZDROJE_DAT.md, Lidl → PDF letáku).
 *
 * Dlaždice je sloupec zarovnaný vlevo: název (~15 b., značka a produkt), popis (~12 b., balení
 * a cena za jednotku), štítek (~25–37 b.) a velká cena (~35–110 b.). Velké ceny sousedních dlaždic
 * bývají v jednom řádku („339.90 299.90 119.90“), proto se řádky dělí na úseky slov.
 * Pravidla:
 * - cena se přijme, jen když ji ověří balení × cena za jednotku (jako leták Penny, R26); balení
 *   1 kg / 1 l / kus / 100 g / „cena za 1 kg“ bez ceny za jednotku platí, jen když je popis přímo nad štítkem
 * - „-28% 34.90“ = sleva s původní cenou, procento musí sedět; „Super cena“ a „Ušetřete* 24%“
 *   (úspora na ceně za jednotku) slevy nejsou (R8)
 * - Lidl Plus („S Lidl Plus“ nad štítkem): velká cena je cena s aplikací, běžná cena je menší
 *   cena nad ní (sleva i bez aplikace) nebo vedle ní („standardní cena bez Lidl Plus“ = akce jen
 *   s aplikací, jako `LidlParser`); štítek „-25%“ / „-10 Kč“ musí sedět k běžné nebo původní ceně
 * - „4+2 zdarma“ = akce na více kusů, cena je běžná cena kusu vedle velké ceny (jako `LidlParser`)
 * - platnost z hlavičky strany („Od čtvrtka 8. 10. do 11. 10.“), jinak z patičky („Nabídka zboží
 *   platí od 8. 10. do 11. 10. 2026“), jinak z letáku; „jen v sobotu“ v dlaždici ji zkrátí
 * Neověřitelná dlaždice se vynechá a zůstane zmínkou bez ceny (R27) — chybějící akce je lepší
 * než akce se špatnou cenou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Lidl;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\PackageSize;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type Badges array{lidlPlus: bool, percent: ?array{int, ?int}, loyaltyPercent: ?int, loyaltyAmount: ?int, amount: ?int, multibuy: ?string, save: ?int, super: bool, sidePrices: list<int>, loyaltySidePrices: list<int>}
 * @phpstan-type Tile array{names: list<PdfLine>, descriptions: list<PdfLine>, badges: Badges, upperPrice: ?PdfLine}
 */
final class LidlLeafletParser
{
    /** Cena v letáku: „25.90“ (tečka, dvě desetinná místa). */
    private const PRICE_PATTERN = '/^\d{1,4}\.\d{2}$/';

    /** Malá cena vedle velké ceny nebo štítku; „209.90**“ = doporučená cena výrobce. */
    private const SMALL_PRICE_PATTERN = '/^(\d{1,4}\.\d{2})\**$/';

    /** Nejmenší výška velké ceny (b.): běžná cena u Lidl Plus nad velkou cenou má ~34 b., malé ceny vedle ~15 b. */
    private const PRICE_MIN_HEIGHT = 30.0;

    /** Mezera mezi slovy řádku větší než tento násobek výšky písma = jiný úsek (jiná dlaždice). */
    private const SEGMENT_GAP_RATIO = 0.6;

    /** Poměr výšek sousedních slov, nad kterým jde o jiný úsek („-40% 49.90“ a vedle název „Mandarinky“). */
    private const SEGMENT_HEIGHT_RATIO = 1.5;

    /** Odchylka levého okraje textu dlaždice od levého okraje velké ceny (b.); štítek bývá o ~4–6 b. vpravo. */
    private const COLUMN_TOLERANCE = 8.0;

    /** Největší svislá mezera mezi částmi jedné dlaždice (b.); mezi dlaždicemi bývá přes 50 b. */
    private const STACK_MAX_GAP = 20.0;

    /** Výška písma popisu (b.): balení ~12 b., podmínky akce na více kusů ~9 b. */
    private const DESCRIPTION_MIN_HEIGHT = 8.0;

    private const DESCRIPTION_MAX_HEIGHT = 13.6;

    /** Výška písma názvu (b.), ~15 b. */
    private const NAME_MIN_HEIGHT = 14.0;

    private const NAME_MAX_HEIGHT = 17.0;

    /** Malá cena vedle velké ceny nebo štítku: největší vodorovná mezera (b.). */
    private const SIDE_PRICE_MAX_GAP = 45.0;

    /** Procento na štítku se smí lišit o 1 (Lidl zaokrouhluje dolů i nahoru). */
    private const BADGE_TOLERANCE = 1;

    private const HALERS_PER_CROWN = 100;

    /** Předpona ID nabídky z letáku — odliší ji od ID produktu z webu. */
    private const EXTERNAL_ID_PREFIX = 'letak-';

    /** Délka otisku názvu a balení v ID (sloupec external_id má 64 znaků). */
    private const EXTERNAL_ID_HASH_LENGTH = 24;

    /** Hlavička strany: „Od čtvrtka 8. 10. do 11. 10.“. */
    private const HEADER_PATTERN = '/^Od\s+\p{L}+\s+(\d{1,2})\.\s*(\d{1,2})\.\s+do\s+(\d{1,2})\.\s*(\d{1,2})\./u';

    /** Patička strany: „Nabídka zboží platí od 8. 10. do 11. 10. 2026 nebo do vyprodání zásob“. */
    private const FOOTER_PATTERN = '/platí\s+od\s+(\d{1,2})\.\s*(\d{1,2})\.\s+do\s+(\d{1,2})\.\s*(\d{1,2})\.\s+(\d{4})/u';

    /** Kratší akce v dlaždici: „jen v sobotu“, „pouze v pátek“. */
    private const SHORT_VALIDITY_PATTERN = '/(?:jen|pouze)\s+(?:v|ve)\s+(pondělí|úterý|středu|čtvrtek|pátek|sobotu|neděli)/iu';

    /** Den v týdnu ze SHORT_VALIDITY_PATTERN => ISO číslo dne. */
    private const WEEKDAYS = ['pondělí' => 1, 'úterý' => 2, 'středu' => 3, 'čtvrtek' => 4, 'pátek' => 5, 'sobotu' => 6, 'neděli' => 7];

    /** Hlavička bez roku přes přelom roku: datum víc než půl roku před letákem je v dalším roce. */
    private const YEAR_WRAP_MONTHS = 6;

    /** Štítek Lidl Plus nad slevou s aplikací. */
    private const LIDL_PLUS_PATTERN = '/^S\s+Lidl\s+Plus$/iu';

    /** Sleva „-28% 34.90“ (s původní cenou, někdy s „**“ = doporučená cena výrobce) nebo jen „-46%“. */
    private const PERCENT_BADGE_PATTERN = '/^-\s*(\d{1,2})\s*%(?:\s+(\d{1,4}\.\d{2}))?\s*\**$/u';

    /** Sleva v korunách „-10 Kč“. */
    private const AMOUNT_BADGE_PATTERN = '/^-\s*(\d{1,4})\s*Kč$/u';

    /** Procento úspory pod „Ušetřete*“ („33%“, bez minus). */
    private const SAVE_PERCENT_PATTERN = '/^(\d{1,2})\s*%$/u';

    private const SAVE_PATTERN = '/^Ušetřete\s*\**$/iu';

    /** „Super cena“ bývá na jednom řádku i na dvou („Super“ / „cena“). */
    private const SUPER_PATTERN = '/^(?:Super(?:\s+cena)?|cena)$/iu';

    /** Akce na více kusů „4+2“ a pod ní „zdarma“. */
    private const MULTIBUY_PATTERN = '/^(\d\s*\+\s*\d)(?:\s+zdarma)?$/iu';

    /** Ostatní text štítku, který nic nemění: „zdarma“ pod „4+2“, „Novinka“, hvězdičky. */
    private const IGNORED_BADGE_PATTERN = '/^(?:zdarma|Novinka|\*+)$/iu';

    /** Cena za jednotku v popisu: „100 g = 20,72 Kč“, „1 kg = 111 Kč“, „100g= 19,95 Kč“. */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l|ks)\s*=\s*(\d+(?:[.,]\d{1,2})?)\s*Kč(?:\/\p{L}+)?/iu';

    /**
     * Balení o jedné jednotce, u kterého je velká cena zároveň cenou za jednotku — Lidl pak cenu
     * za jednotku neuvádí (1 kg, kus, „cena za 100 g“ u masa a ryb, 100 g u sušenek).
     */
    private const SINGLE_UNIT_PATTERN = '/^(?:cena\s+za\s+)?(?:1\s*kg|1\s*l|1\s*ks|kus|100\s*g|100\s*ml)(?:\s*[–-]\s*balení)?$/iu';

    /** Oddělovač částí popisu („500 g, s chráněným označením“) — čárka, která není desetinná („0,7 l“). */
    private const DESCRIPTION_SEPARATOR_PATTERN = '/,(?!\d)/';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Ověřené akce všech stran letáku; stejná dlaždice na více stranách jen jednou.
     *
     * @param  list<PdfPage>  $pages
     * @param  string  $pageUrlPattern  Odkaz na stránku v prohlížeči letáku (sprintf: slug, číslo stránky)
     * @return list<OfferData>
     */
    public function offers(array $pages, LeafletData $leaflet, string $slug, string $pageUrlPattern): array
    {
        $offers = [];
        foreach ($pages as $page) {
            foreach ($this->pageOffers($page, $leaflet, sprintf($pageUrlPattern, $slug, $page->number)) as $offer) {
                $offers[$offer->key()] ??= $offer;
            }
        }

        return array_values($offers);
    }

    /**
     * Velké ceny stránky — kotvy dlaždic (pro měření pokrytí).
     *
     * @return list<PdfLine>
     */
    public function bigPrices(PdfPage $page): array
    {
        return array_values(array_filter($this->segments($page), $this->isBigPrice(...)));
    }

    /**
     * Ověřené akce jedné strany.
     *
     * @return list<OfferData>
     */
    private function pageOffers(PdfPage $page, LeafletData $leaflet, string $pageUrl): array
    {
        $validity = $this->pageValidity($page, $leaflet);
        if ($validity === null) {
            return [];
        }

        $segments = $this->segments($page);
        $smallPrices = array_values(array_filter(
            $page->words(),
            fn (PdfWord $word): bool => preg_match(self::SMALL_PRICE_PATTERN, $word->text) === 1 && $word->height() < self::PRICE_MIN_HEIGHT,
        ));

        $tiles = [];
        $consumed = [];
        foreach (array_filter($segments, $this->isBigPrice(...)) as $anchor) {
            $tile = $this->tile($anchor, $segments, $smallPrices);
            if ($tile !== null) {
                $tiles[] = [$anchor, $tile];
                // Běžná cena nad cenou s Lidl Plus patří do této dlaždice, ne do vlastní
                if ($tile['upperPrice'] !== null) {
                    $consumed[] = $tile['upperPrice'];
                }
            }
        }

        $offers = [];
        foreach ($tiles as [$anchor, $tile]) {
            $offer = in_array($anchor, $consumed, true) ? null : $this->offer($anchor, $tile, $validity, $page->number, $pageUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * Řádky stránky rozdělené na úseky — slova blízko sebe se stejně velkým písmem.
     *
     * @return list<PdfLine>
     */
    private function segments(PdfPage $page): array
    {
        $segments = [];
        foreach ($page->lines as $line) {
            $words = [];
            foreach ($line->words as $word) {
                $previous = $words === [] ? null : $words[array_key_last($words)];
                if ($previous !== null && $this->splits($previous, $word)) {
                    $segments[] = $this->segment($words, $line->block);
                    $words = [];
                }
                $words[] = $word;
            }
            if ($words !== []) {
                $segments[] = $this->segment($words, $line->block);
            }
        }

        return $segments;
    }

    /**
     * Začíná slovo nový úsek? Velká mezera nebo jiná velikost písma než předchozí slovo.
     */
    private function splits(PdfWord $previous, PdfWord $word): bool
    {
        $smaller = min($previous->height(), $word->height());
        $larger = max($previous->height(), $word->height());

        return $word->xMin - $previous->xMax > self::SEGMENT_GAP_RATIO * $smaller
            || $larger > self::SEGMENT_HEIGHT_RATIO * $smaller;
    }

    /**
     * Úsek ze slov — řádek s rozměry podle svých slov.
     *
     * @param  non-empty-list<PdfWord>  $words
     */
    private function segment(array $words, int $block): PdfLine
    {
        return new PdfLine(
            $words,
            $block,
            min(array_map(fn (PdfWord $word): float => $word->xMin, $words)),
            min(array_map(fn (PdfWord $word): float => $word->yMin, $words)),
            max(array_map(fn (PdfWord $word): float => $word->xMax, $words)),
            max(array_map(fn (PdfWord $word): float => $word->yMax, $words)),
        );
    }

    /**
     * Je úsek velká cena dlaždice?
     */
    private function isBigPrice(PdfLine $segment): bool
    {
        return count($segment->words) === 1
            && $segment->height() >= self::PRICE_MIN_HEIGHT
            && preg_match(self::PRICE_PATTERN, $segment->text()) === 1;
    }

    /**
     * Dlaždice nad velkou cenou: od ceny nahoru štítek, popis a název ve stejném sloupci;
     * null, když chybí název nebo popis.
     *
     * @param  list<PdfLine>  $segments
     * @param  list<PdfWord>  $smallPrices
     * @return Tile|null
     */
    private function tile(PdfLine $anchor, array $segments, array $smallPrices): ?array
    {
        $badgeSegments = [];
        $descriptions = [];
        $names = [];
        $upperPrice = null;
        $lidlPlusIndex = null;
        $zone = 'badge';

        foreach ($this->column($anchor, $segments) as $segment) {
            $text = $segment->text();
            $height = $segment->height();

            if ($zone === 'badge') {
                if ($this->isBigPrice($segment)) {
                    // Nad cenou s Lidl Plus smí být jen běžná cena téže dlaždice
                    if ($lidlPlusIndex === null || $upperPrice !== null) {
                        break;
                    }
                    $upperPrice = $segment;

                    continue;
                }
                if ($this->isBadge($text)) {
                    if (preg_match(self::LIDL_PLUS_PATTERN, $text) === 1) {
                        $lidlPlusIndex = count($badgeSegments);
                    }
                    $badgeSegments[] = $segment;

                    continue;
                }
                $zone = 'description';
            }

            if ($zone === 'description') {
                if ($height >= self::DESCRIPTION_MIN_HEIGHT && $height <= self::DESCRIPTION_MAX_HEIGHT) {
                    $descriptions[] = $segment;

                    continue;
                }
                $zone = 'name';
            }

            if ($height < self::NAME_MIN_HEIGHT || $height > self::NAME_MAX_HEIGHT) {
                break;
            }
            $names[] = $segment;
        }

        if ($names === [] || $descriptions === []) {
            return null;
        }

        return [
            'names' => array_reverse($names),
            'descriptions' => array_reverse($descriptions),
            'badges' => $this->badges($anchor, $badgeSegments, $lidlPlusIndex, $smallPrices),
            'upperPrice' => $upperPrice,
        ];
    }

    /**
     * Úseky ve sloupci nad cenou od nejbližšího, dokud mezera mezi nimi nepřesáhne STACK_MAX_GAP.
     *
     * @param  list<PdfLine>  $segments
     * @return list<PdfLine>
     */
    private function column(PdfLine $anchor, array $segments): array
    {
        $above = array_filter(
            $segments,
            fn (PdfLine $segment): bool => $segment !== $anchor
                && abs($segment->xMin - $anchor->xMin) <= self::COLUMN_TOLERANCE
                && $segment->yMin < $anchor->yMin,
        );
        usort($above, fn (PdfLine $a, PdfLine $b): int => $b->yMax <=> $a->yMax);

        $column = [];
        $top = $anchor->yMin;
        foreach ($above as $segment) {
            if ($top - $segment->yMax > self::STACK_MAX_GAP) {
                break;
            }
            $column[] = $segment;
            $top = min($top, $segment->yMin);
        }

        return $column;
    }

    /**
     * Je text úsek štítku (sleva, Lidl Plus, „Super cena“, „Ušetřete*“, „4+2 zdarma“…)?
     */
    private function isBadge(string $text): bool
    {
        foreach ([self::LIDL_PLUS_PATTERN, self::PERCENT_BADGE_PATTERN, self::AMOUNT_BADGE_PATTERN, self::SAVE_PERCENT_PATTERN,
            self::SAVE_PATTERN, self::SUPER_PATTERN, self::MULTIBUY_PATTERN, self::IGNORED_BADGE_PATTERN] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Význam štítku. Úseky pod „S Lidl Plus“ (blíž ceně) patří ke slevě s aplikací, nad ním k běžné ceně.
     * Malé ceny vedle velké ceny nebo vedle štítku jsou běžná nebo původní cena.
     *
     * @param  list<PdfLine>  $segments  Úseky štítku od ceny nahoru
     * @param  list<PdfWord>  $smallPrices
     * @return Badges
     */
    private function badges(PdfLine $anchor, array $segments, ?int $lidlPlusIndex, array $smallPrices): array
    {
        $badges = [
            'lidlPlus' => $lidlPlusIndex !== null, 'percent' => null, 'loyaltyPercent' => null, 'loyaltyAmount' => null,
            'amount' => null, 'multibuy' => null, 'save' => null, 'super' => false,
            'sidePrices' => [], 'loyaltySidePrices' => $this->sidePrices($anchor, $smallPrices),
        ];

        foreach ($segments as $index => $segment) {
            $text = $segment->text();
            $loyalty = $lidlPlusIndex !== null && $index < $lidlPlusIndex;
            $side = $this->sidePrices($segment, $smallPrices);

            if (preg_match(self::PERCENT_BADGE_PATTERN, $text, $matches) === 1) {
                if ($loyalty) {
                    $badges['loyaltyPercent'] = (int) $matches[1];
                } else {
                    $original = isset($matches[2]) ? $this->prices->parse($matches[2]) : ($side[0] ?? null);
                    $badges['percent'] = [(int) $matches[1], $original];
                }
            } elseif (preg_match(self::AMOUNT_BADGE_PATTERN, $text, $matches) === 1) {
                $badges[$loyalty ? 'loyaltyAmount' : 'amount'] = (int) $matches[1] * self::HALERS_PER_CROWN;
                $badges['sidePrices'] = [...$badges['sidePrices'], ...$side];
            } elseif (preg_match(self::MULTIBUY_PATTERN, $text, $matches) === 1) {
                $badges['multibuy'] = preg_replace('/\s+/', '', $matches[1]);
                $badges['sidePrices'] = [...$badges['sidePrices'], ...$side];
            } elseif (preg_match(self::SAVE_PERCENT_PATTERN, $text, $matches) === 1) {
                $badges['save'] = (int) $matches[1];
            } elseif (preg_match(self::SUPER_PATTERN, $text) === 1) {
                $badges['super'] = true;
            } else {
                $badges['sidePrices'] = [...$badges['sidePrices'], ...$side];
            }
        }

        return $badges;
    }

    /**
     * Malé ceny vpravo vedle úseku (na stejné výšce), od nejbližší; v haléřích.
     *
     * @param  list<PdfWord>  $smallPrices
     * @return list<int>
     */
    private function sidePrices(PdfLine $segment, array $smallPrices): array
    {
        $found = array_filter(
            $smallPrices,
            fn (PdfWord $word): bool => $word->xMin >= ($segment->xMin + $segment->xMax) / 2
                && $word->xMin - $segment->xMax <= self::SIDE_PRICE_MAX_GAP
                && $word->yMin < $segment->yMax && $word->yMax > $segment->yMin,
        );
        usort($found, fn (PdfWord $a, PdfWord $b): int => $a->xMin <=> $b->xMin);

        return array_map(fn (PdfWord $word): int => $this->prices->parse(rtrim($word->text, '*')), $found);
    }

    /**
     * Nabídka z dlaždice podle pravidel v popisu třídy; null, když cenu nejde ověřit.
     *
     * @param  Tile  $tile
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(PdfLine $anchor, array $tile, array $validity, int $pageNumber, string $pageUrl): ?OfferData
    {
        $name = $this->joinLines($tile['names']);
        $description = $this->joinLines($tile['descriptions']);
        if ($name === null || $description === null) {
            return null;
        }

        $big = $this->prices->parse($anchor->text());
        $badges = $tile['badges'];
        $terms = $badges['lidlPlus']
            ? $this->lidlPlusTerms($big, $badges, $tile['upperPrice'] === null ? null : $this->prices->parse($tile['upperPrice']->text()))
            : $this->regularTerms($big, $badges);
        if ($terms === null) {
            return null;
        }

        $packageText = $this->packageText($description);
        $package = $this->packages->parse($packageText);
        if (! $this->verified($terms['checked'], $description, $packageText, $package)) {
            return null;
        }

        $validity = $this->shortValidity($validity, Text::join($name, $description));

        return new OfferData(
            externalId: $this->externalId($name, $packageText, $big),
            name: $name,
            offerType: $terms['type'],
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: [
                'page' => $pageNumber,
                'name' => $name,
                'description' => $description,
                'price' => $big,
                'badges' => $badges,
                'upper_price' => $tile['upperPrice']?->text(),
            ],
            price: $terms['price'],
            originalPrice: $terms['original'],
            loyaltyPrice: $terms['loyalty'],
            loyaltyProgram: $terms['loyalty'] === null ? null : LoyaltyProgram::LidlPlus,
            discountPercent: $terms['percent'],
            promotionText: $terms['promotion'],
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $package,
            sourceUrl: $pageUrl,
        );
    }

    /**
     * Ceny dlaždice s Lidl Plus: velká cena s aplikací, běžná cena nad ní (sleva i bez aplikace)
     * nebo vedle ní (standardní cena, akce jen s aplikací). Sleva s aplikací musí sedět
     * k původní, jinak k běžné ceně.
     *
     * @param  Badges  $badges
     * @return array{type: OfferType, price: ?int, original: ?int, loyalty: int, percent: ?int, promotion: ?string, checked: list<int>}|null
     */
    private function lidlPlusTerms(int $loyalty, array $badges, ?int $upperPrice): ?array
    {
        $regular = $upperPrice ?? ($badges['loyaltySidePrices'][0] ?? null);
        $original = $badges['percent'][1] ?? null;
        $reference = $original ?? $regular;

        if ($reference !== null && $badges['loyaltyPercent'] !== null && ! DiscountCheck::roundedWithin($loyalty, $reference, $badges['loyaltyPercent'], self::BADGE_TOLERANCE)) {
            return null;
        }
        if ($reference !== null && $badges['loyaltyAmount'] !== null && $reference - $loyalty !== $badges['loyaltyAmount']) {
            return null;
        }

        $type = OfferType::LoyaltyOnly;
        $percent = null;
        if ($upperPrice !== null && $badges['percent'] !== null) {
            // Sleva i bez aplikace: „-40% 49.90“ nad běžnou cenou
            if ($original === null || ! DiscountCheck::roundedWithin($upperPrice, $original, $badges['percent'][0], self::BADGE_TOLERANCE)) {
                return null;
            }
            [$type, $percent] = [OfferType::Discount, $badges['percent'][0]];
        } elseif ($upperPrice !== null) {
            $type = OfferType::PromoPrice;
        }

        return [
            'type' => $type,
            'price' => $regular,
            'original' => $type === OfferType::Discount ? $original : null,
            'loyalty' => $loyalty,
            'percent' => $percent,
            'promotion' => $this->promotion($badges),
            'checked' => $regular === null ? [$loyalty] : [$loyalty, $regular],
        ];
    }

    /**
     * Ceny dlaždice bez Lidl Plus: sleva „-28% 34.90“ (procento musí sedět), „-50 Kč“ s původní
     * cenou vedle, akce na více kusů s běžnou cenou kusu vedle, jinak akční cena (R8).
     *
     * @param  Badges  $badges
     * @return array{type: OfferType, price: int, original: ?int, loyalty: null, percent: ?int, promotion: ?string, checked: list<int>}|null
     */
    private function regularTerms(int $big, array $badges): ?array
    {
        $terms = ['type' => OfferType::PromoPrice, 'price' => $big, 'original' => null, 'loyalty' => null, 'percent' => null,
            'promotion' => $this->promotion($badges), 'checked' => [$big]];

        if ($badges['multibuy'] !== null) {
            // Cena za kus při koupi více kusů je velká, běžná cena kusu je vedle (jako LidlParser)
            $standard = $badges['sidePrices'][0] ?? ($badges['loyaltySidePrices'][0] ?? null);

            return [...$terms, 'type' => OfferType::Multibuy, 'price' => $standard ?? $big, 'checked' => $standard === null ? [$big] : [$big, $standard]];
        }

        if ($badges['percent'] !== null) {
            [$percent, $original] = $badges['percent'];

            return $original !== null && DiscountCheck::roundedWithin($big, $original, $percent, self::BADGE_TOLERANCE)
                ? [...$terms, 'type' => OfferType::Discount, 'original' => $original, 'percent' => $percent]
                : null;
        }

        if ($badges['amount'] !== null) {
            $original = $badges['sidePrices'][0] ?? ($badges['loyaltySidePrices'][0] ?? null);

            return $original !== null && $original - $big === $badges['amount']
                ? [...$terms, 'type' => OfferType::Discount, 'original' => $original]
                : null;
        }

        return $terms;
    }

    /**
     * Text akce pro `promotion_text` jako na webu: „Super cena“, „Ušetřete* 24%“, „4+2 zdarma“, „-25%“.
     *
     * @param  Badges  $badges
     */
    private function promotion(array $badges): ?string
    {
        return match (true) {
            $badges['multibuy'] !== null => $badges['multibuy'].' zdarma',
            $badges['save'] !== null => "Ušetřete* {$badges['save']}%",
            $badges['super'] => 'Super cena',
            $badges['percent'] !== null => "-{$badges['percent'][0]}%",
            $badges['loyaltyPercent'] !== null => "-{$badges['loyaltyPercent']}%",
            default => null,
        };
    }

    /**
     * Ověří některou z cen cenou za jednotku z popisu; balení o jedné jednotce („1 kg“, „cena za 100 g“)
     * bez ceny za jednotku ověří samo sebe — popis je přímo nad štítkem.
     *
     * @param  list<int>  $prices
     */
    private function verified(array $prices, string $description, ?string $packageText, ?PackageSize $package): bool
    {
        if ($package === null || $packageText === null) {
            return false;
        }

        preg_match_all(self::UNIT_PRICE_PATTERN, $description, $units, PREG_SET_ORDER);
        if ($units === []) {
            return preg_match(self::SINGLE_UNIT_PATTERN, $packageText) === 1;
        }

        foreach ($units as $unit) {
            $unitSize = $this->packages->parse($unit[1].' '.$unit[2]);
            if ($unitSize === null || $unitSize->unit !== $package->unit) {
                continue;
            }
            $stated = $this->prices->parse($unit[3]);
            foreach ($prices as $price) {
                if (UnitPriceCheck::matches($price, $package->quantity, $unitSize->quantity, $stated)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Balení — první část popisu bez ceny za jednotku: „500 g – balení“, „6 x 0,5 l“, „cena za 1 kg“;
     * null, když popis balením nezačíná („⌀ 12 cm“, jen „1 kg = 27,98 Kč“).
     */
    private function packageText(string $description): ?string
    {
        $rest = trim(preg_replace(self::UNIT_PRICE_PATTERN, '', $description) ?? '', " ,;/\t\n");
        $first = Text::clean((preg_split(self::DESCRIPTION_SEPARATOR_PATTERN, $rest) ?: [''])[0]);

        return $first !== null && $this->packages->parse($first) !== null ? $first : null;
    }

    /**
     * Platnost strany: hlavička („Od čtvrtka 8. 10. do 11. 10.“) s rokem z patičky nebo letáku,
     * jinak patička, jinak platnost letáku; null, když nic z toho není.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function pageValidity(PdfPage $page, LeafletData $leaflet): ?array
    {
        $header = null;
        $footer = null;
        foreach ($page->lines as $line) {
            $text = $line->text();
            if ($header === null && preg_match(self::HEADER_PATTERN, $text, $matches) === 1) {
                $header = array_map(intval(...), array_slice($matches, 1));
            }
            if ($footer === null && preg_match(self::FOOTER_PATTERN, $text, $matches) === 1) {
                $footer = array_map(intval(...), array_slice($matches, 1));
            }
        }

        $range = $header ?? $footer;
        $year = $footer[4] ?? $leaflet->validFrom?->year;
        if ($range === null || $year === null) {
            return $leaflet->validFrom !== null && $leaflet->validTo !== null ? [$leaflet->validFrom, $leaflet->validTo] : null;
        }

        $from = $this->localDate($year, $range[1], $range[0]);
        if ($footer === null && $leaflet->validFrom !== null && $from !== null && $from->lt($leaflet->validFrom->subMonths(self::YEAR_WRAP_MONTHS))) {
            $from = $from->addYear();
        }
        $to = $from === null ? null : $this->localDate($from->year, $range[3], $range[2]);
        if ($from === null || $to === null) {
            return null;
        }

        return [$from, $to->lt($from) ? $to->addYear() : $to];
    }

    /**
     * Místní datum z čísel; null pro neexistující den.
     */
    private function localDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? $this->calendar->date(sprintf('%04d-%02d-%02d', $year, $month, $day)) : null;
    }

    /**
     * Kratší akce uvedená v dlaždici („jen v sobotu“): první takový den v platnosti strany.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function shortValidity(array $validity, ?string $text): array
    {
        if ($text === null || preg_match(self::SHORT_VALIDITY_PATTERN, $text, $matches) !== 1) {
            return $validity;
        }

        $weekday = self::WEEKDAYS[mb_strtolower($matches[1])];
        for ($day = $validity[0]; $day->lte($validity[1]); $day = $day->addDay()) {
            if ($day->dayOfWeekIso === $weekday) {
                return [$day, $day];
            }
        }

        return $validity;
    }

    /**
     * Stabilní ID dlaždice (R16): stejný název, balení a velká cena dají při dalším stažení
     * i na jiné straně letáku stejný klíč. Cena odliší různé výrobky se stejným názvem
     * („OPTISANA Nosní sprej“ 30 ml za 59,90 a za 69,90); změněná cena je nová akce.
     */
    private function externalId(string $name, ?string $packageText, int $price): string
    {
        $fingerprint = trim($this->normalizer->normalize($name, $packageText)).'|'.$price;

        return self::EXTERNAL_ID_PREFIX.substr(hash('sha256', $fingerprint), 0, self::EXTERNAL_ID_HASH_LENGTH);
    }

    /**
     * Text úseků shora dolů jedním řádkem; „Hladká/ Polohrubá“ bez mezery za lomítkem.
     *
     * @param  list<PdfLine>  $lines
     */
    private function joinLines(array $lines): ?string
    {
        $text = implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $lines));

        return Text::clean(preg_replace('#/\s+#u', '/', $text));
    }
}
