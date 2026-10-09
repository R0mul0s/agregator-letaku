<?php

/**
 * Akce s cenou z PDF letáku Billy — text s polohou z `pdftotext -bbox-layout` (R86, R89), bez LLM.
 *
 * Používá se jen pro letáky, které ještě nezačaly: akce, které už platí, vrací API (ZDROJE_DAT.md,
 * Billa). Dlaždice letáku (strana 567 × 794 b.):
 * - **název** písmem ~10,9 b. (značka nebo druh nad ním i pod ním ~7,8 b.: „Olma“ / „Klasik jogurt bílý“),
 *   **popis** ~8,7 b. („volná, 1 kg“, „130 g“, „více druhů“) a **cena za jednotku** ~7,4 b.
 *   („100 g = 7,11 Kč“, s Klubem dvojí „100 g = 13 Kč s Klubem/ 18,38 Kč bez Klubu“); řádky jsou
 *   zarovnané vlevo nebo vpravo (text bývá vlevo od ceny);
 * - **cenovka**: sleva „-52%“ (~25 b.), pod ní velká cena „11,90“ (~22 b.) a pod ní vpravo malá
 *   přeškrtnutá cena „24,90/“ (~6 b.), u ceny s BILLA Klubem místo ní „běžná cena 23,90“;
 * - akce na množství: velká cena je cena kusu při koupi více kusů, v popisu „při koupi 1 ks 29,90“
 *   a u ceny štítek „PŘI KOUPI OD 3 KS“ (u „1+1“ „KUPTE 2“).
 *
 * Cena se přijme jen **ověřená**: cena přepočtená na balení musí dát každou cenu za jednotku dlaždice
 * (tolerance jako leták Penny, R26); u Klubu cena s Klubem tu „s Klubem“ a běžná cena tu „bez Klubu“.
 * Sleva v procentech musí sedět na přeškrtnutou (běžnou) cenu. Ze sedících dvojic cena–dlaždice
 * vyhrávají nejbližší. Neověřitelné dlaždice (zelenina „volná, 1 kg“, pult „cena za 100 g“, víc velikostí
 * „od 270 g“) se neuloží.
 *
 * Platnost: oddíl strany s datem nad dlaždicí („SUPER STŘEDA 7. 10.“, „OD 8. 10. DO 11. 10.“,
 * „PLATNOST OD 8. 10.“), hlavička přes celou stranu („ČTVRTEK–NEDĚLE 8. 10. – 11. 10. 2026“), jinak
 * platnost letáku. Strana za stranou s vlastní platností v hlavičce (dvoustrana víkendu) bez vlastní
 * hlavičky má platnost nejistou — její dlaždice se vynechají.
 *
 * Glyfy písma Billy: „ż“ je „ž“ („Petrżel“), „ŭ“ je „ů“ („Mŭj skyr“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfTile;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\TileUnits;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\OfferType;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;

final class BillaLeafletParser
{
    /** Glyfy písma Billy => správné znaky. */
    private const GLYPHS = ['ż' => 'ž', 'Ż' => 'Ž', 'ŭ' => 'ů', 'Ŭ' => 'Ů', 'ǜ' => '’'];

    /** Velká cena „11,90“ (~22 b., na titulní straně až ~70 b.). */
    private const PRICE_MIN_HEIGHT = 15.0;

    private const PRICE_PATTERN = '/^\d{1,4},\d{2}$/';

    /** Sleva „-52%“ (~25 b.) nad cenou; „-10“ a „%“ bývají i dvě slova. */
    private const PERCENT_MIN_HEIGHT = 15.0;

    private const PERCENT_PATTERN = '/^-(\d{1,2})%$/';

    private const PERCENT_NUMBER_PATTERN = '/^-(\d{1,2})$/';

    private const PERCENT_SIGN = '%';

    /** Mezera mezi číslem slevy a znakem „%“ (podíl výšky čísla). */
    private const PERCENT_GAP_RATIO = 0.3;

    /** Přeškrtnutá cena „24,90/“ (~6 b.). */
    private const CROSSED_PATTERN = '/^(\d{1,4},\d{2})\/$/';

    private const SMALL_MAX_HEIGHT = 15.0;

    /** Cena bez Klubu pod cenou s Klubem: „běžná cena 23,90“. */
    private const USUAL_LABEL_PATTERN = '/běžná\s+cena\s+(\d{1,4},\d{2})/u';

    /** Štítek akce na množství u ceny: „PŘI KOUPI OD 3 KS“, „KUPTE 2 ZAPLAŤTE 1“. */
    private const MULTIBUY_LABEL_PATTERN = '/\b(?:OD\s+(\d{1,2})\s*KS|KUPTE\s+(\d{1,2}))\b/u';

    /** Cena jednoho kusu u akce na množství v popisu: „při koupi 1 ks 29,90“. */
    private const SINGLE_PRICE_PATTERN = '/při\s+koupi\s+1\s*ks\s*(\d{1,4},\d{2})/u';

    /** Poloha přeškrtnuté a běžné ceny vůči velké ceně (podíly výšky velké ceny). */
    private const BELOW_MIN_RATIO = 0.5;

    private const BELOW_MAX_RATIO = 0.8;

    private const BELOW_SIDE_RATIO = 0.6;

    /** Poloha slevy nad velkou cenou (podíly výšky velké ceny). */
    private const ABOVE_MAX_RATIO = 1.0;

    private const ABOVE_OVERLAP_RATIO = 0.4;

    /** Štítek akce na množství nejvýš tak daleko od ceny (podíl výšky ceny). */
    private const MULTIBUY_LABEL_DISTANCE_RATIO = 1.5;

    /** Text dlaždice: výška písma (řádky názvu, popisu a ceny za jednotku). */
    private const TEXT_MIN_HEIGHT = 5.0;

    private const TEXT_MAX_HEIGHT = 40.0;

    /** Slova jednoho řádku: odchylka horního okraje a největší mezera (podíl výšky, nejméně body). */
    private const ROW_TOLERANCE = 1.5;

    private const ROW_OVERLAP_RATIO = 0.5;

    private const WORD_GAP_RATIO = 0.6;

    private const WORD_GAP_MIN = 4.0;

    /** Řádky jedné dlaždice: zarovnání vlevo nebo vpravo, překryv a mezera (podíl výšky řádku). */
    private const ALIGN_TOLERANCE = 3.0;

    private const LINE_OVERLAP = 3.0;

    private const LINE_GAP_RATIO = 0.6;

    private const LINE_GAP_MIN = 2.0;

    /** Řádek o tolik vyšší než popis po popisu začíná další dlaždici. */
    private const NEXT_TILE_HEIGHT_RATIO = 1.15;

    /** Popis začíná balením, druhem prodeje nebo cenou za jednotku („130 g“, „volná, 1 kg“, „více druhů“). */
    private const DETAIL_START_PATTERN = '/^(?:od\s+)?\d|^(?:balení|volná|volný|volné|cena\s+za|z\s+pultu|chlazen|mražen|skládan|více\s+druhů|různé\s+druhy|\d+\s*druh)/iu';

    /** Největší vzdálenost ceny od textu dlaždice (podíl výšky ceny). */
    private const TILE_MAX_DISTANCE_RATIO = 5.0;

    /**
     * Cena za jednotku: „100 g = 7,11 Kč“, „100 g = 13 Kč“, „100 g = od 24,91 Kč“, „1 ks = 2,63 Kč s Klubem/“;
     * za ní cena bez Klubu ve stejné jednotce „18,38 Kč bez Klubu“.
     */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:,\d+)?)\s*(\p{L}+)\s*=\s*(od\s+)?(\d+(?:,\d{1,2})?)\s*Kč(\s*s\s+Klubem)?/u';

    private const WITHOUT_CLUB_PATTERN = '/\/?\s*(od\s+)?(\d+(?:,\d{1,2})?)\s*Kč\s*bez\s+Klubu/u';

    /** Cena bez Klubu musí v textu začínat hned za cenou s Klubem — nejvýš tolik znaků (mezera). */
    private const WITHOUT_CLUB_MAX_OFFSET = 1;

    /** Cena s kupónem z aplikace („s kupónem/ 19,85 Kč bez kupónu“). */
    private const COUPON_PATTERN = '/kupón/iu';

    /** Druh ceny za jednotku: k ceně s Klubem, bez Klubu, nebo k akční ceně. */
    private const KIND_CLUB = 'club';

    private const KIND_WITHOUT_CLUB = 'without_club';

    /** Víc druhů nebo velikostí — akce platí na víc produktů katalogu. */
    private const VARIANTS_PATTERN = '/více\s+druhů|různé\s+druhy|\d+\s*druh|\bod\s+\d/iu';

    /**
     * Platnost oddílu: „8. 10. – 11. 10. 2026“, „OD 8. 10. DO 11. 10.“, „PLATNOST OD 8. 10.“, „7. 10.“.
     * Rozsah se dvěma daty, „OD …“ bez konce platí do konce letáku, jedno datum jen ten den.
     */
    private const RANGE_PATTERN = '/(\d{1,2})\.\s*(\d{1,2})\.\s*(?:(\d{4})\s*)?(?:–|-|DO|do)\s*(\d{1,2})\.\s*(\d{1,2})\./u';

    private const FROM_PATTERN = '/\bOD\s+(\d{1,2})\.\s*(\d{1,2})\.(?!\s*\d)/u';

    private const SINGLE_DATE_PATTERN = '/^(?:\p{Lu}[\p{Lu}\s]*\s)?(\d{1,2})\.\s*(\d{1,2})\.$/u';

    /** Hlavička přes celou stranu: v horní části strany a velkým písmem. */
    private const HEADER_TOP_RATIO = 0.3;

    private const HEADER_MIN_HEIGHT = 25.0;

    /** Hlavička bez data (nový oddíl s platností letáku): nahoře, velkým písmem, přes velkou část šířky. */
    private const PLAIN_HEADER_TOP_RATIO = 0.15;

    private const PLAIN_HEADER_MIN_HEIGHT = 20.0;

    private const PLAIN_HEADER_MIN_WIDTH_RATIO = 0.4;

    /** Nadpis oddílu nad datem: mezera (podíl výšky data) a nejmenší výška písma (podíl výšky data). */
    private const TITLE_GAP_RATIO = 1.5;

    private const TITLE_MIN_HEIGHT_RATIO = 0.7;

    /** Oddíl s datem musí ležet nad cenou (tolerance v bodech). */
    private const MARKER_ABOVE_TOLERANCE = 5.0;

    /** Oddíl ve sloupci platí nejvýš pro dlaždice tak daleko pod ním (podíl výšky strany). */
    private const MARKER_REACH_RATIO = 0.3;

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PriceFormatter $prices,
        private readonly LeafletDates $dates,
    ) {}

    /**
     * Ověřené dlaždice celého letáku; stejná dlaždice na více stranách se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @return list<BillaLeafletItem>
     */
    public function items(array $pages, array $validity): array
    {
        $items = [];
        $previousHeader = null;
        foreach ($pages as $page) {
            $page = $this->withoutGlyphs($page);
            $markers = $this->markers($page, $validity);
            $header = $this->pageHeader($markers);
            // Strana za stranou s vlastní platností v hlavičce (víkendová dvoustrana) bez vlastní hlavičky
            $uncertain = $header === null && $previousHeader !== null && ! $this->plainHeader($page);

            foreach ($this->pageItems($page, $markers, $validity, $uncertain) as $item) {
                $key = $item->name.'|'.$item->packageText.'|'.$item->price.'|'.$item->loyaltyPrice;
                $items[$key] ??= $item;
            }
            $previousHeader = $header !== null && $header['validity'] !== $validity ? $header : null;
        }

        return array_values($items);
    }

    /**
     * Ověřené dlaždice jedné strany.
     *
     * @param  list<array{box: PdfBox, validity: array{CarbonImmutable, CarbonImmutable}, header: bool}>  $markers
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @param  bool  $uncertain  Platnost strany bez oddílu nad dlaždicí není jistá — takové dlaždice vynechat
     * @return list<BillaLeafletItem>
     */
    private function pageItems(PdfPage $page, array $markers, array $validity, bool $uncertain): array
    {
        $words = $page->words();
        $prices = $this->bigPrices($words);
        $percents = $this->percents($words);
        $crossed = $this->crossedPrices($words);
        $smallRows = $this->rows(array_values(array_filter($words, fn (PdfWord $word): bool => $word->height() < self::SMALL_MAX_HEIGHT)));
        $usual = $this->usualPrices($smallRows);
        $labels = $this->multibuyLabels($smallRows);
        $tiles = $this->tiles($words, [...$prices, ...$percents, ...$crossed, ...$usual]);

        $references = PdfLayout::nearest($prices, [...$crossed, ...$usual], $this->belowDistance(...));
        $percentByPrice = PdfLayout::nearest($prices, $percents, $this->aboveDistance(...));

        $items = [];
        foreach ($this->matches($prices, $tiles, $references) as [$tile, $priceIndex]) {
            $itemValidity = $this->validityFor($tile, $prices[$priceIndex], $markers, $validity, $uncertain, $page->height);
            if ($itemValidity === null) {
                continue;
            }

            $item = $this->item($tile, $prices[$priceIndex], $references[$priceIndex] ?? null, $percentByPrice[$priceIndex] ?? null, $labels, $itemValidity, $page->number);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Ověřené dvojice dlaždice–cena: ze všech sedících vyhrávají nejbližší, každá cena i dlaždice nejvýš jednou.
     *
     * @param  list<PdfBox>  $prices
     * @param  list<PdfTile>  $tiles
     * @param  array<int, PdfBox>  $references  Index ceny => přeškrtnutá nebo běžná cena u ní
     * @return list<array{PdfTile, int}> Dlaždice a index její ceny
     */
    private function matches(array $prices, array $tiles, array $references): array
    {
        $facts = array_map($this->tileFacts(...), $tiles);

        $pairs = PdfLayout::greedyPairs($tiles, $prices, function (PdfTile $tile, PdfBox $price, int $tileIndex, int $priceIndex) use ($facts, $references): ?array {
            $distance = $price->distanceTo($tile->box);

            return $distance <= $price->height() * self::TILE_MAX_DISTANCE_RATIO
                && $this->verify($price->value, ($references[$priceIndex] ?? null)?->value, $facts[$tileIndex])
                ? [$distance, null]
                : null;
        });

        return array_map(fn (array $pair): array => [$tiles[$pair[0]], $pair[1]], $pairs);
    }

    /**
     * Strana s opravenými glyfy písma Billy („Petrżel“ → „Petržel“).
     */
    private function withoutGlyphs(PdfPage $page): PdfPage
    {
        $lines = array_map(fn (PdfLine $line): PdfLine => new PdfLine(
            array_map(fn (PdfWord $word): PdfWord => new PdfWord(strtr($word->text, self::GLYPHS), $word->xMin, $word->yMin, $word->xMax, $word->yMax), $line->words),
            $line->block, $line->xMin, $line->yMin, $line->xMax, $line->yMax,
        ), $page->lines);

        return new PdfPage($page->number, $page->width, $page->height, $lines);
    }

    /**
     * Velké ceny „11,90“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function bigPrices(array $words): array
    {
        $prices = [];
        foreach ($words as $word) {
            if ($word->height() >= self::PRICE_MIN_HEIGHT && preg_match(self::PRICE_PATTERN, $word->text) === 1) {
                $prices[] = $this->box($word, $this->priceParser->parse($word->text));
            }
        }

        return $prices;
    }

    /**
     * Slevy v procentech „-52%“, i jako dvě slova „-10“ „%“.
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
            if (preg_match(self::PERCENT_PATTERN, $word->text, $m) === 1) {
                $percents[] = $this->box($word, (int) $m[1]);

                continue;
            }
            if (preg_match(self::PERCENT_NUMBER_PATTERN, $word->text, $m) !== 1) {
                continue;
            }
            foreach ($words as $sign) {
                $gap = $sign->xMin - $word->xMax;
                if ($sign->text === self::PERCENT_SIGN && $gap >= -self::ROW_TOLERANCE && $gap <= $word->height() * self::PERCENT_GAP_RATIO
                    && $sign->yMax > $word->yMin && $sign->yMin < $word->yMax) {
                    $percents[] = new PdfBox($word->text.self::PERCENT_SIGN, (int) $m[1], $word->xMin, min($word->yMin, $sign->yMin), $sign->xMax, max($word->yMax, $sign->yMax));

                    break;
                }
            }
        }

        return $percents;
    }

    /**
     * Přeškrtnuté ceny „24,90/“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function crossedPrices(array $words): array
    {
        $crossed = [];
        foreach ($words as $word) {
            if ($word->height() < self::SMALL_MAX_HEIGHT && preg_match(self::CROSSED_PATTERN, $word->text, $m) === 1) {
                $crossed[] = $this->box($word, $this->priceParser->parse($m[1]));
            }
        }

        return $crossed;
    }

    /**
     * Ceny bez Klubu „běžná cena 23,90“ (řádek pod cenou s Klubem).
     *
     * @param  list<PdfBox>  $rows
     * @return list<PdfBox>
     */
    private function usualPrices(array $rows): array
    {
        $usual = [];
        foreach ($rows as $row) {
            if (preg_match(self::USUAL_LABEL_PATTERN, $row->text, $m) === 1) {
                $usual[] = new PdfBox($row->text, $this->priceParser->parse($m[1]), $row->xMin, $row->yMin, $row->xMax, $row->yMax);
            }
        }

        return $usual;
    }

    /**
     * Štítky akce na množství s počtem kusů („OD 3 KS“, „KUPTE 2“).
     *
     * @param  list<PdfBox>  $rows
     * @return list<PdfBox>
     */
    private function multibuyLabels(array $rows): array
    {
        $labels = [];
        foreach ($rows as $row) {
            if (preg_match(self::MULTIBUY_LABEL_PATTERN, $row->text, $m) === 1) {
                $labels[] = new PdfBox($row->text, (int) ($m[1] !== '' ? $m[1] : $m[2]), $row->xMin, $row->yMin, $row->xMax, $row->yMax);
            }
        }

        return $labels;
    }

    /**
     * Vzdálenost přeškrtnuté nebo běžné ceny od velké ceny, nebo null, když pod ní neleží.
     */
    private function belowDistance(PdfBox $price, PdfBox $reference): ?float
    {
        $height = $price->height();
        $fits = $reference->yMin >= $price->yMin + $height * self::BELOW_MIN_RATIO
            && $reference->yMin <= $price->yMax + $height * self::BELOW_MAX_RATIO
            && $reference->xMin >= $price->xMin - $height * self::BELOW_SIDE_RATIO
            && $reference->xMax <= $price->xMax + $height * self::BELOW_SIDE_RATIO;

        return $fits ? $price->distanceTo($reference) : null;
    }

    /**
     * Vzdálenost slevy v procentech od ceny, nebo null, když nad ní neleží.
     */
    private function aboveDistance(PdfBox $price, PdfBox $percent): ?float
    {
        $height = $price->height();
        $fits = $percent->yMax <= $price->yMin + $height * self::ABOVE_OVERLAP_RATIO
            && $percent->yMax >= $price->yMin - $height * self::ABOVE_MAX_RATIO
            && $percent->xMin < $price->xMax && $percent->xMax > $price->xMin;

        return $fits ? $price->distanceTo($percent) : null;
    }

    /**
     * Textové části dlaždic: sloupec řádků zarovnaných vlevo nebo vpravo, nahoře název, pod ním popis
     * (balení, druhy, cena za jednotku). Dlaždice bez názvu nebo bez popisu se vynechá.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $exclude  Ceny a štítky — jejich slova do textu dlaždice nepatří
     * @return list<PdfTile>
     */
    private function tiles(array $words, array $exclude): array
    {
        $text = array_values(array_filter(
            PdfLayout::byHeight($words, self::TEXT_MIN_HEIGHT, self::TEXT_MAX_HEIGHT),
            fn (PdfWord $word): bool => ! PdfLayout::isInside($word, $exclude, self::ROW_TOLERANCE),
        ));
        $rows = $this->rows($text);

        $used = [];
        $tiles = [];
        foreach ($rows as $index => $first) {
            if (isset($used[$index]) || preg_match('/\p{L}/u', $first->text) !== 1 || preg_match(self::DETAIL_START_PATTERN, $first->text) === 1) {
                continue;
            }

            $column = $this->column($rows, $index, $used);
            $nameLines = [];
            $detailLines = [];
            foreach ($column as $row) {
                if ($detailLines === [] && preg_match(self::DETAIL_START_PATTERN, $row->text) !== 1) {
                    $nameLines[] = $row;
                } else {
                    $detailLines[] = $row;
                }
            }
            if ($detailLines === []) {
                continue;
            }

            foreach (array_keys($column) as $rowIndex) {
                $used[$rowIndex] = true;
            }
            $tiles[] = new PdfTile(
                array_map(fn (PdfBox $row): string => $row->text, $nameLines),
                array_map(fn (PdfBox $row): string => $row->text, $detailLines),
                array_reduce($column, fn (PdfBox $box, PdfBox $row): PdfBox => $box->merge($row), $first),
            );
        }

        return $tiles;
    }

    /**
     * Sloupec řádků od `$start` dolů: každý další těsně pod předchozím a zarovnaný vlevo nebo vpravo
     * s prvním. Řádek výrazně vyšší než popis po popisu už patří další dlaždici.
     *
     * @param  list<PdfBox>  $rows  Seřazené shora dolů
     * @param  array<int, true>  $used  Řádky, které už patří jiné dlaždici
     * @return array<int, PdfBox> Index řádku => řádek
     */
    private function column(array $rows, int $start, array $used): array
    {
        $first = $rows[$start];
        $column = [$start => $first];
        $last = $first;
        $detailHeight = null;
        for ($index = $start + 1; $index < count($rows); $index++) {
            $row = $rows[$index];
            $gap = $row->yMin - $last->yMax;
            if ($gap > max(self::LINE_GAP_MIN, $last->height() * self::LINE_GAP_RATIO)) {
                // Řádky jsou seřazené podle horního okraje — další už mohou být jen níž
                if ($row->yMin - $last->yMax > $last->height() * self::TILE_MAX_DISTANCE_RATIO) {
                    break;
                }

                continue;
            }
            $aligned = abs($row->xMin - $first->xMin) <= self::ALIGN_TOLERANCE || abs($row->xMax - $first->xMax) <= self::ALIGN_TOLERANCE;
            if (isset($used[$index]) || ! $aligned || $gap < -self::LINE_OVERLAP) {
                continue;
            }
            $isDetail = preg_match(self::DETAIL_START_PATTERN, $row->text) === 1;
            if ($detailHeight !== null && ! $isDetail && $row->height() > $detailHeight * self::NEXT_TILE_HEIGHT_RATIO) {
                break;
            }
            if ($detailHeight !== null || $isDetail) {
                $detailHeight = max($detailHeight ?? 0.0, $row->height());
            }

            $column[$index] = $row;
            $last = $row;
        }

        return $column;
    }

    /**
     * Slova složená do řádků: překrývají se svisle aspoň z poloviny menšího z nich („běžná cena“
     * menším písmem a „23,90“ větším) a mezera mezi nimi je malá. Řádky pdftotext se nepoužijí —
     * slučují slova sousedních dlaždic. Seřazené shora dolů.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function rows(array $words): array
    {
        usort($words, fn (PdfWord $a, PdfWord $b): int => $a->xMin <=> $b->xMin);

        /** @var list<PdfBox> $rows */
        $rows = [];
        foreach ($words as $word) {
            $box = $this->box($word, 0);
            foreach ($rows as $index => $row) {
                $gap = $word->xMin - $row->xMax;
                $overlap = min($row->yMax, $word->yMax) - max($row->yMin, $word->yMin);
                if ($overlap >= min($row->height(), $word->height()) * self::ROW_OVERLAP_RATIO
                    && $gap >= -self::ROW_TOLERANCE && $gap <= max(self::WORD_GAP_MIN, $word->height() * self::WORD_GAP_RATIO)) {
                    $rows[$index] = $row->merge($box);

                    continue 2;
                }
            }
            $rows[] = $box;
        }

        usort($rows, fn (PdfBox $a, PdfBox $b): int => $a->yMin <=> $b->yMin ?: $a->xMin <=> $b->xMin);

        return $rows;
    }

    /**
     * Ceny za jednotku (druh: s Klubem, bez Klubu, jinak null), cena kusu u akce na množství a balení z popisu.
     *
     * @return array{unitPrices: list<array{kind: string|null, unit: string, quantity: float, from: bool, value: int}>, single: int|null, packages: array<string, list<float>>}
     */
    private function tileFacts(PdfTile $tile): array
    {
        $text = $tile->detailText();
        preg_match_all(self::UNIT_PRICE_PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $unitPrices = [];
        foreach ($matches as $m) {
            $unit = TileUnits::unit($m[2][0]);
            if ($unit === null) {
                continue;
            }
            $quantity = TileUnits::number($m[1][0]) * $unit[1];
            $club = ($m[5][0] ?? '') !== '';
            $unitPrices[] = ['kind' => $club ? self::KIND_CLUB : null, 'unit' => $unit[0], 'quantity' => $quantity, 'from' => $m[3][0] !== '', 'value' => $this->priceParser->parse($m[4][0])];

            // Cena bez Klubu hned za cenou s Klubem — ve stejné jednotce
            $after = substr($text, $m[0][1] + strlen($m[0][0]));
            if ($club && preg_match(self::WITHOUT_CLUB_PATTERN, $after, $w, PREG_OFFSET_CAPTURE) === 1 && $w[0][1] <= self::WITHOUT_CLUB_MAX_OFFSET) {
                $unitPrices[] = ['kind' => self::KIND_WITHOUT_CLUB, 'unit' => $unit[0], 'quantity' => $quantity, 'from' => $w[1][0] !== '', 'value' => $this->priceParser->parse($w[2][0])];
            }
        }

        $single = preg_match(self::SINGLE_PRICE_PATTERN, $text, $s) === 1 ? $this->priceParser->parse($s[1]) : null;

        return ['unitPrices' => $unitPrices, 'single' => $single, 'packages' => TileUnits::packages($this->packageText($tile))];
    }

    /**
     * Text balení z popisu — bez cen za jednotku, ceny bez Klubu a ceny kusu u akce na množství.
     */
    private function packageText(PdfTile $tile): string
    {
        $text = (string) preg_replace([self::UNIT_PRICE_PATTERN, self::WITHOUT_CLUB_PATTERN, self::SINGLE_PRICE_PATTERN], ' ', $tile->detailText());

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Ověří velkou cenu proti dlaždici:
     * - s cenou kusu v popisu (akce na množství) musí každou cenu za jednotku dát velká cena nebo cena kusu
     *   a každá z nich aspoň jednu;
     * - s cenou za jednotku „s Klubem“ ji musí dát velká cena a cenu „bez Klubu“ cena pod ní („běžná cena“);
     * - jinak musí velká cena dát každou cenu za jednotku.
     *
     * @param  int|null  $reference  Přeškrtnutá nebo běžná cena u velké ceny
     * @param  array{unitPrices: list<array{kind: string|null, unit: string, quantity: float, from: bool, value: int}>, single: int|null, packages: array<string, list<float>>}  $facts
     */
    private function verify(int $price, ?int $reference, array $facts): bool
    {
        $unitPrices = array_values(array_filter($facts['unitPrices'], fn (array $unitPrice): bool => ($facts['packages'][$unitPrice['unit']] ?? []) !== []));
        if ($unitPrices === [] || count($unitPrices) !== count($facts['unitPrices'])) {
            return false;
        }

        if ($facts['single'] !== null) {
            $byPrice = false;
            $bySingle = false;
            foreach ($unitPrices as $unitPrice) {
                $matchesPrice = $this->unitPriceMatches($price, $unitPrice, $facts['packages']);
                $matchesSingle = $this->unitPriceMatches($facts['single'], $unitPrice, $facts['packages']);
                if (! $matchesPrice && ! $matchesSingle) {
                    return false;
                }
                $byPrice = $byPrice || $matchesPrice;
                $bySingle = $bySingle || $matchesSingle;
            }

            return $byPrice && $bySingle;
        }

        foreach ($unitPrices as $unitPrice) {
            $amount = $unitPrice['kind'] === self::KIND_WITHOUT_CLUB ? $reference : $price;
            if ($amount === null || ! $this->unitPriceMatches($amount, $unitPrice, $facts['packages'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cena přepočtená na balení odpovídá ceně za jednotku? U „od“ (víc velikostí balení) se počítá
     * s největším balením, jinak stačí kterékoli z uvedených.
     *
     * @param  array{kind: string|null, unit: string, quantity: float, from: bool, value: int}  $unitPrice
     * @param  array<string, list<float>>  $packages
     */
    private function unitPriceMatches(int $price, array $unitPrice, array $packages): bool
    {
        return UnitPriceCheck::matchesAny($price, $unitPrice['value'], $unitPrice['quantity'], $unitPrice['from'], $packages[$unitPrice['unit']] ?? []);
    }

    /**
     * Platnost dlaždice: nejbližší oddíl s datem nad ní (ve stejném sloupci, nebo hlavička přes
     * celou stranu), jinak platnost letáku. Null, když platnost není jistá.
     *
     * @param  list<array{box: PdfBox, validity: array{CarbonImmutable, CarbonImmutable}, header: bool}>  $markers
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function validityFor(PdfTile $tile, PdfBox $price, array $markers, array $leaflet, bool $uncertain, float $pageHeight): ?array
    {
        $top = min($tile->box->yMin, $price->yMin);
        $left = min($tile->box->xMin, $price->xMin);
        $right = max($tile->box->xMax, $price->xMax);

        $best = null;
        foreach ($markers as $marker) {
            // Oddíl ve sloupci sahá jen kus pod sebe („SUPER PÁTEK 9. 10.“ je rámeček s jednou dlaždicí)
            $above = $marker['box']->yMax <= $top + self::MARKER_ABOVE_TOLERANCE
                && ($marker['header'] || $top - $marker['box']->yMax <= $pageHeight * self::MARKER_REACH_RATIO);
            $column = $marker['header'] || ($marker['box']->xMin < $right && $marker['box']->xMax > $left);
            if ($above && $column && ($best === null || $marker['box']->yMax > $best['box']->yMax)) {
                $best = $marker;
            }
        }

        if ($best !== null) {
            return $best['validity'];
        }

        return $uncertain ? null : $leaflet;
    }

    /**
     * Oddíly strany s platností: řádky s datem („7. 10.“, „OD 8. 10. DO 11. 10.“, „PLATNOST OD 8. 10.“).
     * Hlavička je nahoře a velkým písmem — platí pro celou stranu pod ní.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return list<array{box: PdfBox, validity: array{CarbonImmutable, CarbonImmutable}, header: bool}>
     */
    private function markers(PdfPage $page, array $leaflet): array
    {
        $markers = [];
        $lines = $page->lines;
        foreach ($lines as $index => $line) {
            $text = $line->text();
            // „PLATNOST“ / „OD 8. 10.“ bývají dva řádky
            $previous = $lines[$index - 1] ?? null;
            $validity = $this->lineValidity($text, $previous?->text(), $leaflet);
            if ($validity === null) {
                continue;
            }

            $markers[] = [
                'box' => $this->withTitle(new PdfBox($text, 0, $line->xMin, $line->yMin, $line->xMax, $line->yMax), $lines),
                'validity' => $validity,
                'header' => $line->yMin < $page->height * self::HEADER_TOP_RATIO && $line->height() >= self::HEADER_MIN_HEIGHT,
            ];
        }

        return $markers;
    }

    /**
     * Oddíl s datem i s nadpisem nad ním („PŘIPRAVTE SE NA VÍKEND UŽ VE ČTVRTEK“ nad „OD 8. 10. DO 11. 10.“) —
     * nadpis bývá širší než datum a určuje sloupec, pro který oddíl platí.
     *
     * @param  list<PdfLine>  $lines
     */
    private function withTitle(PdfBox $marker, array $lines): PdfBox
    {
        $height = $marker->height();
        $box = $marker;
        do {
            $grown = false;
            foreach ($lines as $line) {
                if ($line->yMin < $box->yMin && $line->yMax >= $box->yMin - $height * self::TITLE_GAP_RATIO && $line->yMax <= $marker->yMax
                    && $line->height() >= $height * self::TITLE_MIN_HEIGHT_RATIO
                    && $line->xMin < $box->xMax && $line->xMax > $box->xMin) {
                    $box = new PdfBox($marker->text, 0, min($box->xMin, $line->xMin), $line->yMin, max($box->xMax, $line->xMax), $box->yMax);
                    $grown = true;
                }
            }
        } while ($grown);

        return $box;
    }

    /**
     * Platnost z řádku oddílu, nebo null. Datum bez roku je nejbližší ke konci letáku
     * (i přes Nový rok, R113).
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function lineValidity(string $text, ?string $previous, array $leaflet): ?array
    {
        if (preg_match(self::RANGE_PATTERN, $text, $m) === 1) {
            return $this->dates->range((int) $m[1], (int) $m[2], $m[3] !== '' ? (int) $m[3] : null, (int) $m[4], (int) $m[5], null, $leaflet[1]);
        }
        if (preg_match(self::FROM_PATTERN, $text, $m) === 1) {
            return $this->range($this->dates->near((int) $m[1], (int) $m[2], $leaflet[1]), $leaflet[1]);
        }
        if (preg_match(self::SINGLE_DATE_PATTERN, trim($text), $m) === 1) {
            $date = $this->dates->near((int) $m[1], (int) $m[2], $leaflet[1]);

            return $this->range($date, $date);
        }

        return null;
    }

    /**
     * Platnost od–do, nebo null, když datum neexistuje nebo konec je před začátkem.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function range(?CarbonImmutable $from, ?CarbonImmutable $to): ?array
    {
        return $from === null || $to === null || $from->greaterThan($to) ? null : [$from, $to];
    }

    /**
     * Hlavička strany s platností (přes celou stranu), nebo null.
     *
     * @param  list<array{box: PdfBox, validity: array{CarbonImmutable, CarbonImmutable}, header: bool}>  $markers
     * @return array{box: PdfBox, validity: array{CarbonImmutable, CarbonImmutable}, header: bool}|null
     */
    private function pageHeader(array $markers): ?array
    {
        foreach ($markers as $marker) {
            if ($marker['header']) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * Má strana nahoře vlastní velký nadpis bez data (nový oddíl s platností letáku)?
     */
    private function plainHeader(PdfPage $page): bool
    {
        foreach ($page->lines as $line) {
            if ($line->yMin < $page->height * self::PLAIN_HEADER_TOP_RATIO && $line->height() >= self::PLAIN_HEADER_MIN_HEIGHT
                && $line->xMax - $line->xMin >= $page->width * self::PLAIN_HEADER_MIN_WIDTH_RATIO) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ověřená dlaždice jako položka; null, když sleva v procentech nesedí nebo chybí údaj akce.
     *
     * @param  PdfBox|null  $reference  Přeškrtnutá cena („24,90/“) nebo cena bez Klubu („běžná cena 23,90“)
     * @param  list<PdfBox>  $labels  Štítky akce na množství na straně
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function item(PdfTile $tile, PdfBox $price, ?PdfBox $reference, ?PdfBox $percent, array $labels, array $validity, int $page): ?BillaLeafletItem
    {
        // Cena s kupónem z aplikace (strana kupónů) není akce obchodu — API ji nemá
        if (preg_match(self::COUPON_PATTERN, $tile->detailText()) === 1) {
            return null;
        }

        $facts = $this->tileFacts($tile);
        $isClub = $reference !== null && preg_match(self::USUAL_LABEL_PATTERN, $reference->text) === 1
            || in_array(self::KIND_CLUB, array_column($facts['unitPrices'], 'kind'), true);

        $loyaltyPrice = null;
        $originalPrice = null;
        $promotionText = null;
        if ($facts['single'] !== null) {
            $quantity = $this->multibuyQuantity($price, $labels);
            if ($quantity === null || $facts['single'] <= $price->value) {
                return null;
            }
            $offerType = OfferType::Multibuy;
            $amount = $facts['single'];
            $usual = $facts['single'];
            $promotionText = sprintf(config()->string('letaky.sources.billa.pdf_multibuy_text'), $quantity).': '.$this->prices->format($price->value);
        } elseif ($this->multibuyQuantity($price, $labels) !== null) {
            // „KUPTE 3 ZAPLAŤTE 2“ bez ceny jednoho kusu v popisu: velká cena je cena kusu při koupi
            // více kusů (13,27 Kč), běžnou cenu kusu leták neuvádí
            return null;
        } elseif ($isClub) {
            // Velká cena je cena s Klubem, „běžná cena“ pod ní cena bez Klubu (R19, jako akce jen s Klubem v API)
            if ($reference === null || $reference->value <= $price->value) {
                return null;
            }
            $offerType = OfferType::LoyaltyOnly;
            $amount = $reference->value;
            $usual = $reference->value;
            $loyaltyPrice = $price->value;
        } elseif ($reference !== null) {
            if ($reference->value <= $price->value) {
                return null;
            }
            $offerType = OfferType::Discount;
            $amount = $price->value;
            $usual = $reference->value;
            $originalPrice = $reference->value;
        } else {
            // „NAŠE CENA“, „SUPER CENA“ — akční cena bez původní ceny (R8)
            $offerType = OfferType::PromoPrice;
            $amount = $price->value;
            $usual = null;
        }

        // Sleva na cenovce musí sedět na běžnou cenu — bez ní ji nejde ověřit
        if ($percent !== null && ($usual === null || ! DiscountCheck::truncatedOrRounded($usual, $loyaltyPrice ?? ($facts['single'] !== null ? $price->value : $amount), $percent->value))) {
            return null;
        }

        $name = Text::clean(implode(' ', $tile->nameLines));
        if ($name === null) {
            return null;
        }
        $packageText = Text::clean(trim((string) preg_replace('/\s*více\s+druhů|\s*\d+\s*druhy?/iu', '', $this->packageText($tile)), ' ,'));

        return new BillaLeafletItem(
            name: $name,
            packageText: $packageText,
            packages: $this->packageList($facts['packages']),
            variants: preg_match(self::VARIANTS_PATTERN, $tile->detailText()) === 1,
            offerType: $offerType,
            price: $amount,
            usualPrice: $usual,
            originalPrice: $originalPrice,
            loyaltyPrice: $loyaltyPrice,
            discountPercent: $offerType === OfferType::Discount ? $percent?->value : null,
            promotionText: $promotionText,
            validFrom: $validity[0],
            validTo: $validity[1],
            page: $page,
            raw: [
                'page' => $page,
                'name' => $tile->nameLines,
                'details' => $tile->detailLines,
                'price' => $price->value,
                'reference' => $reference?->text,
                'percent' => $percent?->value,
            ],
        );
    }

    /**
     * Balení jako seznam pro porovnání s katalogem.
     *
     * @param  array<string, list<float>>  $packages
     * @return list<array{unit: string, quantity: float}>
     */
    private function packageList(array $packages): array
    {
        $list = [];
        foreach ($packages as $unit => $quantities) {
            foreach ($quantities as $quantity) {
                $list[] = ['unit' => $unit, 'quantity' => $quantity];
            }
        }

        return $list;
    }

    /**
     * Počet kusů akce na množství ze štítku u ceny („OD 3 KS“), nebo null.
     *
     * @param  list<PdfBox>  $labels
     */
    private function multibuyQuantity(PdfBox $price, array $labels): ?int
    {
        $best = null;
        foreach ($labels as $label) {
            $distance = $price->distanceTo($label);
            if ($distance <= $price->height() * self::MULTIBUY_LABEL_DISTANCE_RATIO && ($best === null || $distance < $best[0])) {
                $best = [$distance, $label->value];
            }
        }

        return $best === null || $best[1] < 2 ? null : $best[1];
    }

    /**
     * Prvek stránky ze slova.
     */
    private function box(PdfWord $word, int $value): PdfBox
    {
        return new PdfBox($word->text, $value, $word->xMin, $word->yMin, $word->xMax, $word->yMax);
    }
}
