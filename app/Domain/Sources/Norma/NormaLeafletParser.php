<?php

/**
 * Akce s cenou z PDF letáku Normy — text s polohou z `pdftotext -bbox-layout` (R86, R166), bez LLM.
 *
 * Norma nemá web s akcemi ani API, PDF je jediný úplný zdroj (ZDROJE_DAT.md, Norma). Dlaždice:
 * - nahoře **název** ~11–12,5 b. (značka barevně na prvním řádku, „Activia“ / „Kefír“), pod ním
 *   **popis** ~7,5–11 b. s balením a cenou za jednotku v závorce bez rovnítka: „• 280 g (100 g 9,61)“,
 *   „0,5 l“ / „(1 l 39,80)“, u nálevu dvě ceny „820 g / PP 470 g (100 g 4,87/ 8,49)“;
 * - dole pod obrázkem **natočená cenovka**: každá číslice je samostatné slovo posunuté nahoru
 *   („1“ „2“ „,“ „9“ „0“), někdy část jedním slovem („89,9“ „0“); u slevy vedle menší natočená
 *   přeškrtnutá cena („119,9“ „0“) a „levnější o 25%“, jinak „super cena“.
 *
 * Cena se k dlaždici přiřadí jen **ověřená** cenou za jednotku (R26) a leží pod textem dlaždice; ze
 * sedících dvojic vyhrávají nejbližší. Sleva v procentech musí sedět na přeškrtnutou cenu. Oblečení,
 * textil a další zboží bez ceny za jednotku („cena za provedení“) se neověří a neuloží.
 *
 * Platnost: z hlavičky strany („od pondělí 26. října do neděle 1. listopadu“, víkendové strany „od čtvrtka
 * 29. října“), jinak z letáku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Norma;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\CountsTiles;
use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\LeafletOfferId;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\TileUnits;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type UnitPrice array{unit: string, quantity: float, values: list<int>}
 * @phpstan-type Tile array{name: list<string>, details: list<string>, box: PdfBox}
 */
final class NormaLeafletParser
{
    use CountsTiles;

    /** Znaky natočené ceny: číslice a čárka, nejmenší výška (přeškrtnutá cena má ~10–16 b., poslední „0“ i 7,3). */
    private const GLYPH_MIN_HEIGHT = 7.0;

    /** Cenovka (výška znaků ~20–60 b.); menší natočená cena (do ~17 b.) je přeškrtnutá. */
    private const ANCHOR_MIN_HEIGHT = 18.0;

    /** Další znak ceny navazuje vpravo (mezera v bodech) a svisle se s předchozím překrývá. */
    private const GLYPH_MAX_GAP = 3.0;

    private const GLYPH_MIN_OVERLAP_RATIO = 0.5;

    private const GLYPH_MIN_HEIGHT_RATIO = 0.6;

    /** Výška řádků názvu (11,2–12,2; „BALENÍ“ a slogany jsou větší). */
    private const NAME_MIN_HEIGHT = 10.95;

    private const NAME_MAX_HEIGHT = 12.6;

    /** Výška řádků popisu (6,8 až 10,8 podle letáku a strany). */
    private const DETAIL_MIN_HEIGHT = 6.6;

    private const DETAIL_MAX_HEIGHT = 10.95;

    /** Slova jednoho řádku: odchylka horního okraje a největší mezera mezi slovy. */
    private const ROW_TOLERANCE = 1.5;

    private const WORD_GAP = 6.0;

    /** Řádky jedné dlaždice: zarovnání vlevo nebo vpravo (odrážka „•“ odsazuje o ~5 b.), překryv a mezera. */
    private const ALIGN_TOLERANCE = 6.0;

    private const LINE_OVERLAP = 4.0;

    private const LINE_GAP = 6.0;

    /** Cenovka leží pod textem dlaždice (tolerance v bodech), nejvýš tak daleko a s překryvem sloupce. */
    private const PRICE_BELOW_TOLERANCE = 10.0;

    private const TILE_MAX_DISTANCE = 260.0;

    private const TILE_SIDE = 60.0;

    /** Přeškrtnutá cena a sleva leží u cenovky (vzdálenost v bodech). */
    private const CROSSED_MAX_DISTANCE = 60.0;

    private const PERCENT_MAX_DISTANCE = 90.0;

    /** Sleva „25%“ jedním slovem, nebo číslo a „%“; výška od popisku „levnější o“ nahoru. */
    private const PERCENT_MIN_HEIGHT = 9.0;

    private const PERCENT_WORD_PATTERN = '/^(\d{1,2})\s*%$/u';

    private const PERCENT_SIGN = '%';

    private const PERCENT_NUMBER_PATTERN = '/^\d{1,2}$/';

    private const DIGITS_PATTERN = '/^\d+$/';

    private const PERCENT_GAP = 4.0;

    /** Číslice slevy mají výšku jako znak „%“ (podíl odchylky). */
    private const PERCENT_HEIGHT_TOLERANCE = 0.2;

    /** Zaokrouhlení slevy na letáku. */
    private const PERCENT_TOLERANCE = 1;

    /** Znaky ceny jedním slovem, i s mezerou uvnitř („, 9“). */
    private const GLYPH_PATTERN = '/^[\d,](?:[\d,\s]*[\d,])?$/u';

    private const WHITESPACE_PATTERN = '/\s+/u';

    private const PRICE_PATTERN = '/^\d{1,4},\d{2}$/';

    /** Hlavička strany je nahoře (podíl výšky strany). */
    private const HEADER_HEIGHT_RATIO = 0.07;

    /** Data hlavičky „26. října“ / „1. listopadu“. */
    private const HEADER_DATE_PATTERN = '/(\d{1,2})\.\s*(\p{L}+)/u';

    /**
     * Měsíce ve 2. pádě.
     *
     * @var array<string, int>
     */
    private const MONTHS = [
        'ledna' => 1, 'února' => 2, 'března' => 3, 'dubna' => 4, 'května' => 5, 'června' => 6,
        'července' => 7, 'srpna' => 8, 'září' => 9, 'října' => 10, 'listopadu' => 11, 'prosince' => 12,
    ];

    /**
     * Cena za jednotku v závorce bez rovnítka: „(100 g 9,61)“, „(1 l 39,80)“, dvě ceny „(100 g 4,87/ 8,49)“.
     * Skupiny: množství, jednotka, ceny.
     */
    private const UNIT_PRICE_PATTERN = '/\((\d+(?:,\d+)?)\s*(\p{L}+)\s+(\d+,\d{2}(?:\s*\/\s*\d+,\d{2})*)\)/u';

    private const UNIT_VALUES_SEPARATOR = '/\s*\/\s*/u';

    /** Odrážka mezi údaji popisu. */
    private const BULLET_PATTERN = '/\s*•\s*/u';

    /** Balení v popisu: „280 g“, „3 × 20 g“, „50/75 g“, „0,75 l“. */
    private const PACKAGE_TEXT_PATTERN = '/(?:\d+\s*[×x]\s*)?\d+(?:,\d+)?(?:\s*[–\/-]\s*\d+(?:,\d+)?)?\s*(?:kg|g|ml|l|ks)(?!\p{L})/u';

    /** Údaje popisu v uloženém popisu. */
    private const DESCRIPTION_SEPARATOR = ', ';

    /** Zbytek údaje po vyjmutí balení („/ PP 360 g“). */
    private const DESCRIPTION_TRIM = " ,/\u{A0}";

    /** Lomítko mezi variantami bez mezer („Kávenky /Horalky“ → „Kávenky/Horalky“). */
    private const SLASH_PATTERN = '#\s*/\s*#u';

    /** Slovo rozdělené na konci řádku („do- kulturou“). */
    private const HYPHENATION_PATTERN = '/(\p{Ll})-\s+(\p{Ll})/u';

    /**
     * Ligatury písma letáku; pdftotext za nimi dává mezeru i uvnitř slova („ﬂ eecu“, „biﬁ do-“).
     *
     * @var array<string, string>
     */
    private const LIGATURES = ["\u{FB01} " => 'fi', "\u{FB02} " => 'fl', "\u{FB01}" => 'fi', "\u{FB02}" => 'fl'];

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LeafletDates $dates,
        private readonly LeafletOfferId $ids,
    ) {}

    /**
     * Ověřené akce celého letáku; stejná akce na více stranách se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku (týden po–ne)
     * @param  string  $sourceUrl  Odkaz akce (stránka s letáky)
     * @return list<OfferData>
     */
    public function offers(array $pages, array $validity, string $sourceUrl): array
    {
        $this->resetTileCount();
        $offers = [];
        foreach ($pages as $page) {
            foreach ($this->pageOffers($page, $validity, $sourceUrl) as $offer) {
                $offers[$offer->key()] ??= $offer;
            }
        }

        return array_values($offers);
    }

    /**
     * Ověřené akce jedné stránky.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return list<OfferData>
     */
    private function pageOffers(PdfPage $page, array $validity, string $sourceUrl): array
    {
        $words = $page->words();
        $anchors = [];
        $small = [];
        foreach ($this->prices($words) as [$price, $glyphHeight]) {
            if ($glyphHeight >= self::ANCHOR_MIN_HEIGHT) {
                $anchors[] = $price;
            } else {
                $small[] = $price;
            }
        }
        // Malá „cena“ může být i balení v popisu („0,75 l“) — z textu dlaždice se vynechají jen cenovky
        $tiles = $this->tiles($words, $anchors);
        $crossed = PdfLayout::nearest($anchors, $small, fn (PdfBox $price, PdfBox $box): ?float => $this->near($price, $box, self::CROSSED_MAX_DISTANCE));
        $percents = PdfLayout::nearest($anchors, $this->percents($words), fn (PdfBox $price, PdfBox $box): ?float => $this->near($price, $box, self::PERCENT_MAX_DISTANCE));
        $pageValidity = $this->pageValidity($page, $validity);

        $pairs = PdfLayout::greedyPairs(
            $tiles,
            $anchors,
            function (array $tile, PdfBox $price): ?array {
                $distance = $this->tileDistance($tile['box'], $price);

                return $distance !== null && $this->verify($price->value, $tile) ? [$distance, null] : null;
            },
        );

        $offers = [];
        foreach ($pairs as [$tileIndex, $priceIndex]) {
            $offer = $this->offer($tiles[$tileIndex], $anchors[$priceIndex], $crossed[$priceIndex] ?? null, $percents[$priceIndex] ?? null, $pageValidity, $page->number, $sourceUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }
        $this->countTiles(count($anchors), count($offers));

        return $offers;
    }

    /**
     * Natočené ceny: znaky (číslice, čárka, i víc najednou) jdoucí za sebou zleva doprava se svislým
     * překryvem, složené do „129,90“. Prvek ceny je obdélník všech znaků a k němu nejmenší výška
     * znaku (obdélník natočené ceny je vyšší než písmo).
     *
     * @param  list<PdfWord>  $words
     * @return list<array{PdfBox, float}>
     */
    private function prices(array $words): array
    {
        $glyphs = array_values(array_filter($words, fn (PdfWord $word): bool => $word->height() >= self::GLYPH_MIN_HEIGHT
            && preg_match(self::GLYPH_PATTERN, $word->text) === 1));
        usort($glyphs, fn (PdfWord $a, PdfWord $b): int => $a->xMin <=> $b->xMin);

        $used = [];
        $prices = [];
        foreach ($glyphs as $index => $first) {
            if (isset($used[$index])) {
                continue;
            }

            $chain = [$first];
            $used[$index] = true;
            $last = $first;
            while (($next = $this->nextGlyph($glyphs, $last, $used)) !== null) {
                $used[$next] = true;
                $last = $glyphs[$next];
                $chain[] = $last;
            }

            $text = (string) preg_replace(self::WHITESPACE_PATTERN, '', implode('', array_map(fn (PdfWord $glyph): string => $glyph->text, $chain)));
            if (preg_match(self::PRICE_PATTERN, $text) === 1) {
                $prices[] = [
                    new PdfBox(
                        $text,
                        $this->priceParser->parse($text),
                        min(array_map(fn (PdfWord $glyph): float => $glyph->xMin, $chain)),
                        min(array_map(fn (PdfWord $glyph): float => $glyph->yMin, $chain)),
                        max(array_map(fn (PdfWord $glyph): float => $glyph->xMax, $chain)),
                        max(array_map(fn (PdfWord $glyph): float => $glyph->yMax, $chain)),
                    ),
                    min(array_map(fn (PdfWord $glyph): float => $glyph->height(), $chain)),
                ];
            }
        }

        return $prices;
    }

    /**
     * Index dalšího znaku ceny za `$last`: hned vpravo, svisle se překrývá a má podobnou výšku.
     *
     * @param  list<PdfWord>  $glyphs  Seřazené zleva
     * @param  array<int, true>  $used
     */
    private function nextGlyph(array $glyphs, PdfWord $last, array $used): ?int
    {
        foreach ($glyphs as $index => $glyph) {
            $gap = $glyph->xMin - $last->xMax;
            $overlap = min($glyph->yMax, $last->yMax) - max($glyph->yMin, $last->yMin);
            $smaller = min($glyph->height(), $last->height());
            if (! isset($used[$index]) && abs($gap) <= self::GLYPH_MAX_GAP
                && $overlap >= $smaller * self::GLYPH_MIN_OVERLAP_RATIO
                && $smaller >= max($glyph->height(), $last->height()) * self::GLYPH_MIN_HEIGHT_RATIO) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Slevy v procentech: „25%“ jedním slovem, nebo znak „%“ a vlevo od něj číslice stejně velkým
     * písmem — natočené po jedné („2“ „5“ „%“) i najednou („21“ „%“). Číslice ceny vedle slevy
     * jsou větší a nepřidají se.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function percents(array $words): array
    {
        $percents = [];
        foreach ($words as $word) {
            if ($word->height() < self::PERCENT_MIN_HEIGHT) {
                continue;
            }
            if (preg_match(self::PERCENT_WORD_PATTERN, $word->text, $m) === 1) {
                $percents[] = new PdfBox($word->text, (int) $m[1], $word->xMin, $word->yMin, $word->xMax, $word->yMax);

                continue;
            }
            if ($word->text !== self::PERCENT_SIGN) {
                continue;
            }

            $digits = '';
            $box = new PdfBox($word->text, 0, $word->xMin, $word->yMin, $word->xMax, $word->yMax);
            $left = $word;
            while (($digit = $this->digitLeftOf($words, $left, $word->height())) !== null) {
                $digits = $digit->text.$digits;
                $box = $box->merge(new PdfBox($digit->text, 0, $digit->xMin, $digit->yMin, $digit->xMax, $digit->yMax));
                $left = $digit;
            }
            if (preg_match(self::PERCENT_NUMBER_PATTERN, $digits) === 1) {
                $percents[] = new PdfBox($digits.' %', (int) $digits, $box->xMin, $box->yMin, $box->xMax, $box->yMax);
            }
        }

        return $percents;
    }

    /**
     * Číslice hned vlevo od `$word` se svislým překryvem a výškou jako znak „%“; null, když tam není.
     *
     * @param  list<PdfWord>  $words
     */
    private function digitLeftOf(array $words, PdfWord $word, float $signHeight): ?PdfWord
    {
        foreach ($words as $digit) {
            $overlap = min($digit->yMax, $word->yMax) - max($digit->yMin, $word->yMin);
            if ($digit !== $word && preg_match(self::DIGITS_PATTERN, $digit->text) === 1
                && abs($word->xMin - $digit->xMax) <= self::PERCENT_GAP
                && $overlap >= min($digit->height(), $word->height()) * self::GLYPH_MIN_OVERLAP_RATIO
                && abs($digit->height() - $signHeight) <= $signHeight * self::PERCENT_HEIGHT_TOLERANCE) {
                return $digit;
            }
        }

        return null;
    }

    /**
     * Vzdálenost prvku od cenovky, nebo null, když je dál než limit.
     */
    private function near(PdfBox $price, PdfBox $box, float $limit): ?float
    {
        $distance = $price->distanceTo($box);

        return $distance <= $limit ? $distance : null;
    }

    /**
     * Vzdálenost cenovky od textu dlaždice, nebo null, když neleží pod ním ve stejném sloupci.
     */
    private function tileDistance(PdfBox $tile, PdfBox $price): ?float
    {
        $below = $price->yMin >= $tile->yMax - self::PRICE_BELOW_TOLERANCE;
        $column = $price->xMax >= $tile->xMin - self::TILE_SIDE && $price->xMin <= $tile->xMax + self::TILE_SIDE;
        $distance = $tile->distanceTo($price);

        return $below && $column && $distance <= self::TILE_MAX_DISTANCE ? $distance : null;
    }

    /**
     * Textové části dlaždic: řádky názvu a pod nimi zarovnané řádky popisu. Dlaždice bez ceny
     * za jednotku se vynechá — cenu by nebylo čím ověřit.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $anchors  Cenovky — jejich slova do textu dlaždice nepatří
     * @return list<Tile>
     */
    private function tiles(array $words, array $anchors): array
    {
        $text = array_values(array_filter($words, fn (PdfWord $word): bool => ! PdfLayout::isInside($word, $anchors)));
        $names = PdfLayout::rows(PdfLayout::byHeight($text, self::NAME_MIN_HEIGHT, self::NAME_MAX_HEIGHT), self::ROW_TOLERANCE, self::WORD_GAP);
        $details = PdfLayout::rows(PdfLayout::byHeight($text, self::DETAIL_MIN_HEIGHT, self::DETAIL_MAX_HEIGHT), self::ROW_TOLERANCE, self::WORD_GAP);

        $usedNames = [];
        $usedDetails = [];
        $tiles = [];
        foreach ($names as $index => $first) {
            if (isset($usedNames[$index])) {
                continue;
            }

            $usedNames[$index] = true;
            $nameLines = [$first, ...$this->below($names, $first, $first, $usedNames)];
            $detailLines = $this->below($details, $first, $nameLines[array_key_last($nameLines)], $usedDetails);
            $tile = [
                'name' => array_map(fn (PdfBox $line): string => $line->text, $nameLines),
                'details' => array_map(fn (PdfBox $line): string => $line->text, $detailLines),
                'box' => array_reduce([...$nameLines, ...$detailLines], fn (PdfBox $box, PdfBox $line): PdfBox => $box->merge($line), $first),
            ];
            if ($this->unitPrices($tile) !== []) {
                $tiles[] = $tile;
            }
        }

        return $tiles;
    }

    /**
     * Řádky pod `$last`, jeden pod druhým, zarovnané s prvním nebo předchozím řádkem dlaždice vlevo
     * nebo vpravo (popis zarovnaný doprava k poslednímu řádku názvu, „Jogurt“ / „bílý“ / „• 150 g“).
     *
     * @param  list<PdfBox>  $rows
     * @param  array<int, true>  $used
     * @return list<PdfBox>
     */
    private function below(array $rows, PdfBox $first, PdfBox $last, array &$used): array
    {
        $column = [];
        while (true) {
            $next = null;
            foreach ($rows as $index => $row) {
                $gap = $row->yMin - $last->yMax;
                $aligned = array_any([$first, $last], fn (PdfBox $line): bool => abs($row->xMin - $line->xMin) <= self::ALIGN_TOLERANCE
                    || abs($row->xMax - $line->xMax) <= self::ALIGN_TOLERANCE);
                if (! isset($used[$index]) && $aligned && $row->yMin > $last->yMin
                    && $gap >= -self::LINE_OVERLAP && $gap <= self::LINE_GAP
                    && ($next === null || $row->yMin < $rows[$next]->yMin)) {
                    $next = $index;
                }
            }
            if ($next === null) {
                return $column;
            }

            $used[$next] = true;
            $last = $rows[$next];
            $column[] = $last;
        }
    }

    /**
     * Ceny za jednotku z popisu dlaždice (i rozdělené na dva řádky „(1 l“ / „39,80)“).
     *
     * @param  Tile  $tile
     * @return list<UnitPrice>
     */
    private function unitPrices(array $tile): array
    {
        preg_match_all(self::UNIT_PRICE_PATTERN, implode(' ', $tile['details']), $matches, PREG_SET_ORDER);

        $unitPrices = [];
        foreach ($matches as $m) {
            $unit = TileUnits::unit($m[2]);
            if ($unit !== null) {
                $unitPrices[] = [
                    'unit' => $unit[0],
                    'quantity' => TileUnits::number($m[1]) * $unit[1],
                    'values' => array_map($this->priceParser->parse(...), preg_split(self::UNIT_VALUES_SEPARATOR, $m[3]) ?: []),
                ];
            }
        }

        return $unitPrices;
    }

    /**
     * Dá cena každou uvedenou cenu za jednotku pro některé z balení? Dvě ceny za jednotku jsou
     * k celkové hmotnosti a k pevnému podílu („820 g / PP 470 g“), nebo ke dvěma velikostem balení.
     *
     * @param  Tile  $tile
     */
    private function verify(int $price, array $tile): bool
    {
        // Balení bývá i velkým písmem v názvu („Pepsi / Mirinda / 2,25 l“)
        $packages = TileUnits::packages((string) preg_replace(self::UNIT_PRICE_PATTERN, ' ', implode(' ', [...$tile['name'], ...$tile['details']])));
        $unitPrices = $this->unitPrices($tile);

        return array_all($unitPrices, fn (array $unitPrice): bool => array_all(
            $unitPrice['values'],
            fn (int $value): bool => UnitPriceCheck::matchesAny($price, $value, $unitPrice['quantity'], false, $packages[$unitPrice['unit']] ?? []),
        ));
    }

    /**
     * Platnost strany z hlavičky „od pondělí 26. října do neděle 1. listopadu“, jinak platnost letáku.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function pageValidity(PdfPage $page, array $leaflet): array
    {
        $header = array_filter($page->words(), fn (PdfWord $word): bool => $word->yMax < $page->height * self::HEADER_HEIGHT_RATIO);
        $text = implode(' ', array_map(fn (PdfBox $row): string => $row->text, PdfLayout::rows(array_values($header), self::ROW_TOLERANCE, self::WORD_GAP)));
        preg_match_all(self::HEADER_DATE_PATTERN, $text, $matches, PREG_SET_ORDER);

        $dates = array_values(array_filter(array_map(fn (array $m): ?array => isset(self::MONTHS[mb_strtolower($m[2])]) ? [(int) $m[1], self::MONTHS[mb_strtolower($m[2])]] : null, $matches)));
        if (count($dates) !== 2) {
            return $leaflet;
        }

        return $this->dates->range($dates[0][0], $dates[0][1], null, $dates[1][0], $dates[1][1], null, $leaflet[1]) ?? $leaflet;
    }

    /**
     * Nabídka z ověřené dlaždice; null, když sleva v procentech nesedí na přeškrtnutou cenu.
     *
     * @param  Tile  $tile
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $tile, PdfBox $price, ?PdfBox $crossed, ?PdfBox $percent, array $validity, int $page, string $sourceUrl): ?OfferData
    {
        $original = $crossed !== null && $crossed->value > $price->value ? $crossed->value : null;
        if ($percent !== null && ($original === null || ! DiscountCheck::roundedWithin($price->value, $original, $percent->value, self::PERCENT_TOLERANCE))) {
            return null;
        }
        // Přeškrtnutá cena bez slevy v procentech patří jiné cenovce („super cena“ ji nemá)
        if ($percent === null) {
            $original = null;
        }

        [$packageText, $description] = $this->detailParts($tile);
        $nameLines = $tile['name'];
        // Balení jako poslední řádek názvu („Pepsi“ / „Mirinda“ / „2,25 l“) do názvu nepatří
        $last = $nameLines[count($nameLines) - 1] ?? '';
        if ($packageText === null && count($nameLines) > 1 && preg_match(self::PACKAGE_TEXT_PATTERN, $last, $m) === 1 && $m[0] === trim($last)) {
            $packageText = array_pop($nameLines);
        }
        $name = $this->cleanText((string) preg_replace(self::SLASH_PATTERN, '/', implode(' ', $nameLines))) ?? '';

        return new OfferData(
            externalId: $this->ids->for($name, $packageText, $price->value),
            name: $name,
            offerType: $original !== null ? OfferType::Discount : OfferType::PromoPrice,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: [
                'page' => $page,
                'name' => $tile['name'],
                'details' => $tile['details'],
                'price' => $price->value,
                'crossed' => $original,
                'percent' => $percent?->value,
            ],
            price: $price->value,
            originalPrice: $original,
            discountPercent: $original !== null ? $percent->value : null,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * Balení a popis z řádků popisu bez cen za jednotku. Údaje odděluje odrážka „•“ (řádky mezi
     * odrážkami jsou jeden údaj); první množství je balení („280 g“ z „• 280 g (100 g 9,61)“,
     * „670 g“ z „670 g / PP 360 g“), zbytek popis.
     *
     * @param  Tile  $tile
     * @return array{string|null, string|null}
     */
    private function detailParts(array $tile): array
    {
        $text = (string) preg_replace(self::UNIT_PRICE_PATTERN, ' ', implode(' ', $tile['details']));

        $package = null;
        $description = [];
        foreach (preg_split(self::BULLET_PATTERN, $text) ?: [] as $part) {
            if ($package === null && preg_match(self::PACKAGE_TEXT_PATTERN, $part, $m) === 1) {
                $package = $m[0];
                $part = trim(str_replace($m[0], '', $part), self::DESCRIPTION_TRIM);
            }
            $part = Text::clean($part);
            if ($part !== null) {
                $description[] = $part;
            }
        }

        return [$package, $this->cleanText(implode(self::DESCRIPTION_SEPARATOR, $description))];
    }

    /**
     * Text z PDF bez ligatur písma a se slovem rozděleným na konci řádku zase dohromady
     * („biﬁ do- kulturou“ → „bifidokulturou“).
     */
    private function cleanText(string $text): ?string
    {
        return Text::clean((string) preg_replace(self::HYPHENATION_PATTERN, '$1$2', strtr($text, self::LIGATURES)));
    }
}
