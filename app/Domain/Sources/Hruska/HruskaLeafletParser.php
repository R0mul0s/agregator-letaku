<?php

/**
 * Akce s cenou z PDF letáku Hrušky — text s polohou z `pdftotext -bbox-layout` (R86, R165), bez LLM.
 *
 * Leták nemá web s akcemi ani API, PDF je jediný zdroj (ZDROJE_DAT.md, Hruška). Dlaždice:
 * - **cenovka** „NAŠE CENA“ s velkými korunami „24“ (~34,7 b.) a vedle nahoře haléři „90“ (~20,2 b.);
 *   u akce s Hruška klubem „KLUBOVÁ CENA“ s korunami ~37 b. a pod ní menší běžná cena „22“ „90“ (~23,5 b.);
 * - pod cenou (zřídka vedle ní) **název** ~11,1 b., u masa nad ním značka ~10,9 b. („Krásno“);
 * - pod názvem **popis** ~8,5 b. („vybrané druhy | 0,5 l | sklo“, „100 g“) a **cena za jednotku** ~7,3 b.
 *   („1 kg = 249,00 Kč“, s klubem „1 kg = 219,00 Kč s klubem/ 229,00 Kč bez klubu“). Text dlaždice je
 *   zarovnaný vlevo, nebo vpravo k obrázku.
 *
 * Leták nemá přeškrtnuté ceny ani slevy v procentech — všechny akce jsou akční cena bez slevy (R8).
 * Cena se k dlaždici přiřadí jen **ověřená** cenou za jednotku (R26), u klubové akce obě ceny. Dvě ceny
 * za jednotku bez „s klubem“ („1 kg = 134,50 Kč/149,50 Kč“) jsou cena jednoho dne a zbytku týdne —
 * taková dlaždice se neuloží. Ovoce, zelenina a pult s balením „1 kg“ / „1 ks“ cenu za jednotku nemají:
 * přijmou se jen s cenovkou těsně nad textem, jednoznačně nejbližší z obou stran (jako Albert R107).
 *
 * Platnost: z hlavičky první strany („Platnost: 21. 10.–27. 10. 2026 | 43/26“). Rámeček „POUZE STŘEDA“
 * platí jeden den a jeho dlaždice se vynechají (název dne je rozsekaný na kusy, datum nečitelné);
 * sloupce s názvy dnů velkým písmem („PÁTEK SOBOTA NEDĚLE“, „PONDĚLÍ ÚTERÝ“) platí jen v ty dny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Hruska;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\CountsTiles;
use App\Domain\Sources\Pdf\LeafletOfferId;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\TileUnits;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type UnitPrice array{unit: string, quantity: float, from: bool, value: int, regular: int|null}
 * @phpstan-type Tile array{brand: string|null, name: list<string>, details: list<string>, units: list<string>, singleUnit: bool, box: PdfBox}
 */
final class HruskaLeafletParser
{
    use CountsTiles;

    /** Výška korun ceny (body PDF): běžná cenovka ~34,7, klubová ~37,1, běžná cena pod klubovou ~23,5. */
    private const PRICE_MIN_HEIGHT = 20.0;

    /** Cena, ke které se hledá dlaždice — cenovka „NAŠE CENA“ nebo „KLUBOVÁ CENA“, ne běžná cena pod klubovou. */
    private const ANCHOR_MIN_HEIGHT = 30.0;

    /** Klubová cena má větší koruny než běžná cenovka. */
    private const CLUB_MIN_HEIGHT = 36.0;

    /** Běžná cena pod klubovou: menší a nejvýš tak daleko (body PDF). */
    private const REGULAR_MAX_HEIGHT = 30.0;

    private const REGULAR_MAX_DISTANCE = 60.0;

    /** Haléře ceny: vpravo od korun (mezera v bodech) a nahoře (podíl výšky korun). */
    private const FRACTION_MIN_GAP = -4.0;

    private const FRACTION_MAX_GAP = 4.0;

    private const FRACTION_TOP_RATIO = 0.4;

    private const FRACTION_TOP_TOLERANCE = 2.0;

    /** Výška řádků názvu (~11,1) a značky nad ním (~10,9 — menší než název). */
    private const NAME_MIN_HEIGHT = 10.7;

    private const NAME_MAX_HEIGHT = 11.3;

    private const BRAND_MAX_HEIGHT = 11.0;

    /** Výška popisu s balením (~8,5; „PŘI KOUPI“ na nálepkách má 7,7, „od 200 g“ 9,3). */
    private const DETAIL_MIN_HEIGHT = 8.3;

    private const DETAIL_MAX_HEIGHT = 8.7;

    /** Výška ceny za jednotku (~7,3; štítky „NAŠE CENA“ a „BEZ LEPKU“ mají 7,4). */
    private const UNIT_MIN_HEIGHT = 7.2;

    private const UNIT_MAX_HEIGHT = 7.35;

    /** Slova jednoho řádku: odchylka horního okraje a největší mezera mezi slovy. */
    private const ROW_TOLERANCE = 1.5;

    private const WORD_GAP = 6.0;

    /** Řádky jedné dlaždice: zarovnání vlevo nebo vpravo, překryv a mezera mezi řádky. */
    private const ALIGN_TOLERANCE = 3.0;

    private const LINE_OVERLAP = 4.5;

    private const LINE_GAP = 5.0;

    /** Největší vzdálenost cenovky od textu dlaždice. */
    private const TILE_MAX_DISTANCE = 60.0;

    /**
     * Balení dlaždice bez ceny za jednotku, kterou jde přijmout podle polohy (ovoce, zelenina, pult,
     * mléko): „1 kg“ nebo „1 l“ — cena je cena za jednotku.
     *
     * @var list<array<string, list<float>>>
     */
    private const SINGLE_UNIT_PACKAGES = [['g' => [1000.0]], ['ml' => [1000.0]]];

    /** Dlaždice „1 ks“ i s hmotností („1 ks | 170 g“) — cena je za kus. */
    private const SINGLE_PIECE = [1.0];

    /** Cenovka dlaždice bez ceny za jednotku musí ležet těsně nad jejím textem (tolerance překryvu v bodech). */
    private const SINGLE_UNIT_MAX_DISTANCE = 25.0;

    private const SINGLE_UNIT_ABOVE_TOLERANCE = 5.0;

    /** Nejbližší cenovka i dlaždice musí být blíž než tento podíl vzdálenosti druhé nejbližší (R107). */
    private const SINGLE_UNIT_AMBIGUITY_RATIO = 0.5;

    /** Rámeček „POUZE …“ s cenou jednoho dne: slovo „POUZE“ a dlaždice vpravo pod ním do této výšky. */
    private const SINGLE_DAY_WORD = 'POUZE';

    private const SINGLE_DAY_MIN_HEIGHT = 12.0;

    private const SINGLE_DAY_BOX_HEIGHT = 150.0;

    private const SINGLE_DAY_BOX_LEFT = 15.0;

    /** Názvy dnů v záhlaví sloupce („SUPER“ pátek–neděle, „EXTRA“ pondělí–úterý) jsou velkým písmem. */
    private const WEEKDAY_MIN_HEIGHT = 15.0;

    /** Názvy dnů jednoho záhlaví: odchylka horního okraje a mezera mezi nimi. */
    private const WEEKDAY_ROW_TOLERANCE = 15.0;

    private const WEEKDAY_GAP = 40.0;

    /**
     * Den v týdnu podle ISO (1 = pondělí).
     *
     * @var array<string, int>
     */
    private const WEEKDAYS = ['PONDĚLÍ' => 1, 'ÚTERÝ' => 2, 'STŘEDA' => 3, 'ČTVRTEK' => 4, 'PÁTEK' => 5, 'SOBOTA' => 6, 'NEDĚLE' => 7];

    /** Koruny ceny; celé koruny i jedním slovem („399,-“). */
    private const CROWNS_PATTERN = '/^(\d{1,4})(,-)?$/';

    /** Haléře; celé koruny „,-“ za korunami („209“ „,-“). */
    private const FRACTION_PATTERN = '/^(\d{2}|,-)$/';

    private const WHOLE_CROWNS = ',-';

    /** Hlavička první strany: „Platnost: 21. 10.–27. 10. 2026 | 43/26“. */
    private const VALIDITY_PATTERN = '/Platnost:\s*(\d{1,2})\.\s*(\d{1,2})\.\s*[–-]\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u';

    /**
     * Cena za jednotku: „1 kg = 249,00 Kč“, „1 l= 35,80 Kč“, „1 kg = od 749,50 Kč“, s klubem
     * „1 kg = 219,00 Kč s klubem/ 229,00 Kč bez klubu“ a cena dne „1 kg = 134,50 Kč/149,50 Kč“.
     * Skupiny: množství, jednotka, „od“, cena, „s klubem“, druhá cena.
     */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:,\d+)?)\s*(\p{L}+)\s*=\s*(od\s+)?(\d+,\d{2})\s*Kč(\s*s\s+klubem)?(?:\s*\/\s*(?:od\s+)?(\d+,\d{2})\s*Kč(?:\s*bez\s+klubu)?)?/u';

    /** Balení s přídavkem zdarma „150 + 50 g zdarma“ — cena za jednotku je ze součtu. */
    private const PACKAGE_SUM_PATTERN = '/(\d+)\s*\+\s*(\d+)\s*(\p{L}+)/u';

    /** Balení po kusech „5×75 g“ — cena za jednotku bývá za kus. */
    private const PACKAGE_PIECES_PATTERN = '/(\d+)\s*[×x]\s*\d/u';

    private const FREE_WORD = 'zdarma';

    private const PIECES_UNIT = 'ks';

    /** Oddělovač částí názvu a popisu v letáku („Milena | Koko | Sojové řezy“). */
    private const PART_SEPARATOR_PATTERN = '/\s*\|\s*/u';

    /** Hvězdička za názvem odkazuje na poznámku o pultovém zboží, do názvu nepatří. */
    private const FOOTNOTE_PATTERN = '/\*+/u';

    private const NAME_SEPARATOR = ', ';

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LeafletDates $dates,
        private readonly LeafletOfferId $ids,
    ) {}

    /**
     * Platnost letáku z hlavičky první strany; null, když tam není.
     *
     * @param  list<PdfPage>  $pages
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function validity(array $pages): ?array
    {
        $text = implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $pages[0]->lines ?? []));
        if (preg_match(self::VALIDITY_PATTERN, $text, $m) !== 1) {
            return null;
        }

        return $this->dates->range((int) $m[1], (int) $m[2], null, (int) $m[3], (int) $m[4], (int) $m[5]);
    }

    /**
     * Ověřené akce celého letáku; stejná akce na více stranách se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @param  string  $sourceUrl  Odkaz akce (stránka s letákem)
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
        $prices = $this->prices($words);
        $anchors = array_values(array_filter($prices, fn (PdfBox $price): bool => $price->height() >= self::ANCHOR_MIN_HEIGHT));
        $tiles = $this->tiles($words, $prices);
        $singleDay = $this->singleDayBoxes($words);
        $weekdayColumns = $this->weekdayColumns($words, $singleDay);

        $pairs = PdfLayout::greedyPairs(
            $tiles,
            $anchors,
            function (array $tile, PdfBox $price) use ($prices): ?array {
                $distance = $price->distanceTo($tile['box']);
                $verified = $distance > self::TILE_MAX_DISTANCE ? null : $this->verify($price, $prices, $this->unitPrices($tile), $this->packagesOf($tile));

                return $verified === null ? null : [$distance, $verified];
            },
        );
        $pairs = [...$pairs, ...$this->singleUnitPairs($tiles, $anchors, $prices, $pairs)];

        $offers = [];
        foreach ($pairs as [$tileIndex, , $verified]) {
            $tile = $tiles[$tileIndex];
            if ($this->isInside($tile['box'], $singleDay)) {
                continue;
            }
            $tileValidity = $this->columnValidity($tile['box'], $weekdayColumns, $validity);
            if ($tileValidity !== null) {
                $offers[] = $this->offer($tile, $verified, $tileValidity, $page->number, $sourceUrl);
            }
        }
        $this->countTiles(count($anchors), count($offers));

        return $offers;
    }

    /**
     * Ceny na stránce: koruny a vedle nich nahoře menší haléře („24“ „90“, „209“ „,-“).
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function prices(array $words): array
    {
        $fractions = array_values(array_filter($words, fn (PdfWord $word): bool => preg_match(self::FRACTION_PATTERN, $word->text) === 1));

        $prices = [];
        foreach ($words as $crowns) {
            if ($crowns->height() < self::PRICE_MIN_HEIGHT || preg_match(self::CROWNS_PATTERN, $crowns->text, $m) !== 1) {
                continue;
            }
            if (($m[2] ?? '') === self::WHOLE_CROWNS) {
                $prices[] = new PdfBox($crowns->text, $this->priceParser->parse($m[1].',00'), $crowns->xMin, $crowns->yMin, $crowns->xMax, $crowns->yMax);

                continue;
            }

            foreach ($fractions as $fraction) {
                $gap = $fraction->xMin - $crowns->xMax;
                $top = $fraction->yMin - $crowns->yMin;
                if ($fraction === $crowns || $fraction->height() >= $crowns->height()
                    || $gap < self::FRACTION_MIN_GAP || $gap > self::FRACTION_MAX_GAP
                    || $top < -self::FRACTION_TOP_TOLERANCE || $top > $crowns->height() * self::FRACTION_TOP_RATIO) {
                    continue;
                }

                $halers = $fraction->text === self::WHOLE_CROWNS ? '00' : $fraction->text;
                $prices[] = new PdfBox(
                    $crowns->text.' '.$fraction->text,
                    $this->priceParser->parse($crowns->text.','.$halers),
                    $crowns->xMin, $crowns->yMin, max($crowns->xMax, $fraction->xMax), max($crowns->yMax, $fraction->yMax),
                );

                break;
            }
        }

        return $prices;
    }

    /**
     * Textové části dlaždic: řádky názvu (se značkou), pod nimi zarovnané řádky popisu a ceny
     * za jednotku. Dlaždice bez ceny za jednotku se vynechá — cenu by nebylo čím ověřit.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $prices  Ceny — jejich slova do textu dlaždice nepatří
     * @return list<Tile>
     */
    private function tiles(array $words, array $prices): array
    {
        $text = array_values(array_filter($words, fn (PdfWord $word): bool => ! PdfLayout::isInside($word, $prices)));
        $names = PdfLayout::rows(PdfLayout::byHeight($text, self::NAME_MIN_HEIGHT, self::NAME_MAX_HEIGHT), self::ROW_TOLERANCE, self::WORD_GAP);
        $details = PdfLayout::rows(PdfLayout::byHeight($text, self::DETAIL_MIN_HEIGHT, self::DETAIL_MAX_HEIGHT), self::ROW_TOLERANCE, self::WORD_GAP);
        $units = PdfLayout::rows(PdfLayout::byHeight($text, self::UNIT_MIN_HEIGHT, self::UNIT_MAX_HEIGHT), self::ROW_TOLERANCE, self::WORD_GAP);

        $usedNames = [];
        $usedDetails = [];
        $usedUnits = [];
        $tiles = [];
        foreach ($names as $index => $first) {
            if (isset($usedNames[$index])) {
                continue;
            }

            $usedNames[$index] = true;
            $nameLines = [$first, ...$this->below($names, $first, $first, $usedNames)];
            $last = $nameLines[array_key_last($nameLines)];
            $detailLines = $this->below($details, $first, $last, $usedDetails);
            $unitLines = $this->below($units, $first, $detailLines === [] ? $last : $detailLines[array_key_last($detailLines)], $usedUnits);
            $detailText = implode(' ', array_map(fn (PdfBox $line): string => $line->text, $detailLines));
            $packages = TileUnits::packages($detailText);
            $singleUnit = $unitLines === [] && (in_array($packages, self::SINGLE_UNIT_PACKAGES, true) || ($packages['ks'] ?? null) === self::SINGLE_PIECE);
            if ($unitLines === [] && ! $singleUnit) {
                continue;
            }

            // Značka nad názvem má menší písmo („Krásno“ nad „Amálka šunka“)
            $brand = count($nameLines) > 1 && $first->height() < self::BRAND_MAX_HEIGHT ? array_shift($nameLines) : null;
            $tiles[] = [
                'brand' => $brand?->text,
                'name' => array_map(fn (PdfBox $line): string => $line->text, $nameLines),
                'details' => array_map(fn (PdfBox $line): string => $line->text, $detailLines),
                'units' => array_map(fn (PdfBox $line): string => $line->text, $unitLines),
                'singleUnit' => $singleUnit,
                'box' => array_reduce([...$nameLines, ...$detailLines, ...$unitLines], fn (PdfBox $box, PdfBox $line): PdfBox => $box->merge($line), $first),
            ];
        }

        return $tiles;
    }

    /**
     * Řádky pod `$last`, jeden pod druhým, zarovnané s prvním řádkem dlaždice vlevo nebo vpravo
     * (text u obrázku vpravo je zarovnaný doprava). Vybrané řádky označí v `$used`.
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
                $aligned = abs($row->xMin - $first->xMin) <= self::ALIGN_TOLERANCE || abs($row->xMax - $first->xMax) <= self::ALIGN_TOLERANCE;
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
     * Ceny za jednotku dlaždice. Cena dne („134,50 Kč/149,50 Kč“ bez „s klubem“) dlaždici
     * znehodnotí — vrátí prázdný seznam.
     *
     * @param  Tile  $tile
     * @return list<UnitPrice>
     */
    private function unitPrices(array $tile): array
    {
        preg_match_all(self::UNIT_PRICE_PATTERN, implode(' ', $tile['units']), $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $unitPrices = [];
        foreach ($matches as $m) {
            $club = $m[5] !== null;
            // Dvě ceny bez „s klubem“ jsou cena dne; klubová cena bez běžné nejde ověřit celá
            if (($m[6] !== null) !== $club) {
                return [];
            }
            $unit = TileUnits::unit((string) $m[2]);
            if ($unit === null) {
                continue;
            }

            $unitPrices[] = [
                'unit' => $unit[0],
                'quantity' => TileUnits::number((string) $m[1]) * $unit[1],
                'from' => $m[3] !== null,
                'value' => $this->priceParser->parse((string) $m[4]),
                // U klubové ceny je druhá cena za jednotku k běžné ceně
                'regular' => $club && $m[6] !== null ? $this->priceParser->parse($m[6]) : null,
            ];
        }

        return $unitPrices;
    }

    /**
     * Balení z popisu dlaždice v jednotkách pro porovnání; „150 + 50 g“ i jako 200 g a „5×75 g“
     * i jako 5 ks.
     *
     * @param  Tile  $tile
     * @return array<string, list<float>>
     */
    private function packagesOf(array $tile): array
    {
        $text = implode(' ', $tile['details']);
        if (preg_match(self::PACKAGE_SUM_PATTERN, $text, $m) === 1) {
            // „5 + 1 zdarma“ je šest kusů
            $text .= ' '.((int) $m[1] + (int) $m[2]).' '.(mb_strtolower($m[3]) === self::FREE_WORD ? self::PIECES_UNIT : $m[3]);
        }
        if (preg_match(self::PACKAGE_PIECES_PATTERN, $text, $m) === 1) {
            $text .= ' '.$m[1].' '.self::PIECES_UNIT;
        }

        return TileUnits::packages($text);
    }

    /**
     * Dvojice cenovka–dlaždice bez ceny za jednotku („1 kg“, „1 ks“ — ovoce, zelenina, pult): cenovka
     * těsně nad textem dlaždice a obě jednoznačně nejbližší (jako Albert R107). Cenovky a dlaždice
     * z ověřených dvojic už nesoutěží. U klubové cenovky musí být vedle běžná cena.
     *
     * @param  list<Tile>  $tiles
     * @param  list<PdfBox>  $anchors
     * @param  list<PdfBox>  $prices
     * @param  list<array{int, int, array{price: int, loyalty: int|null}}>  $verified  Dvojice ověřené cenou za jednotku
     * @return list<array{int, int, array{price: int, loyalty: int|null}}>
     */
    private function singleUnitPairs(array $tiles, array $anchors, array $prices, array $verified): array
    {
        $freeTiles = array_diff_key(array_filter($tiles, fn (array $tile): bool => $tile['singleUnit']), array_flip(array_column($verified, 0)));
        $freeAnchors = array_diff_key($anchors, array_flip(array_column($verified, 1)));

        $pairs = [];
        $nearest = PdfLayout::mutualNearestRepeated($freeAnchors, $freeTiles, function (PdfBox $price, array $tile): ?float {
            $distance = $price->distanceTo($tile['box']);

            return $price->yMax <= $tile['box']->yMin + self::SINGLE_UNIT_ABOVE_TOLERANCE && $distance <= self::SINGLE_UNIT_MAX_DISTANCE ? $distance : null;
        }, self::SINGLE_UNIT_AMBIGUITY_RATIO);
        foreach ($nearest as $anchorIndex => $tileIndex) {
            $anchor = $anchors[$anchorIndex];
            if ($anchor->height() < self::CLUB_MIN_HEIGHT) {
                $pairs[] = [$tileIndex, $anchorIndex, ['price' => $anchor->value, 'loyalty' => null]];

                continue;
            }

            $regular = array_find($this->regularPrices($anchor, $prices), fn (PdfBox $price): bool => $price->value > $anchor->value);
            if ($regular !== null) {
                $pairs[] = [$tileIndex, $anchorIndex, ['price' => $regular->value, 'loyalty' => $anchor->value]];
            }
        }

        return $pairs;
    }

    /**
     * Ověří cenovku proti dlaždici. U klubové akce je cenovka cena s klubem a k ní patří menší
     * běžná cena blízko ní; obě musí dát svou cenu za jednotku.
     *
     * @param  list<PdfBox>  $prices  Všechny ceny stránky (běžná cena pod klubovou)
     * @param  list<UnitPrice>  $unitPrices
     * @param  array<string, list<float>>  $packages
     * @return array{price: int, loyalty: int|null}|null
     */
    private function verify(PdfBox $anchor, array $prices, array $unitPrices, array $packages): ?array
    {
        if ($unitPrices === []) {
            return null;
        }

        foreach ($unitPrices as $unitPrice) {
            if (! UnitPriceCheck::matchesAny($anchor->value, $unitPrice['value'], $unitPrice['quantity'], $unitPrice['from'], $packages[$unitPrice['unit']] ?? [])) {
                return null;
            }
        }

        $club = array_values(array_filter($unitPrices, fn (array $unitPrice): bool => $unitPrice['regular'] !== null));
        if ($club === []) {
            return ['price' => $anchor->value, 'loyalty' => null];
        }
        if ($anchor->height() < self::CLUB_MIN_HEIGHT) {
            return null;
        }

        foreach ($this->regularPrices($anchor, $prices) as $regular) {
            if ($regular->value > $anchor->value && array_all($club, fn (array $unitPrice): bool => UnitPriceCheck::matchesAny(
                $regular->value, (int) $unitPrice['regular'], $unitPrice['quantity'], $unitPrice['from'], $packages[$unitPrice['unit']] ?? [],
            ))) {
                return ['price' => $regular->value, 'loyalty' => $anchor->value];
            }
        }

        return null;
    }

    /**
     * Kandidáti na běžnou cenu u klubové ceny: menší ceny blízko ní, nejbližší první.
     *
     * @param  list<PdfBox>  $prices
     * @return list<PdfBox>
     */
    private function regularPrices(PdfBox $club, array $prices): array
    {
        $candidates = array_values(array_filter($prices, fn (PdfBox $price): bool => $price->height() < self::REGULAR_MAX_HEIGHT
            && $club->distanceTo($price) <= self::REGULAR_MAX_DISTANCE));
        usort($candidates, fn (PdfBox $a, PdfBox $b): int => $club->distanceTo($a) <=> $club->distanceTo($b));

        return $candidates;
    }

    /**
     * Rámečky „POUZE STŘEDA“ s cenou jednoho dne — oblast vpravo pod slovem „POUZE“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function singleDayBoxes(array $words): array
    {
        $boxes = [];
        foreach ($words as $word) {
            if ($word->text === self::SINGLE_DAY_WORD && $word->height() >= self::SINGLE_DAY_MIN_HEIGHT) {
                $boxes[] = new PdfBox($word->text, 0, $word->xMin - self::SINGLE_DAY_BOX_LEFT, $word->yMin, PHP_FLOAT_MAX, $word->yMin + self::SINGLE_DAY_BOX_HEIGHT);
            }
        }

        return $boxes;
    }

    /**
     * Leží levý horní roh dlaždice v některém rámečku?
     *
     * @param  list<PdfBox>  $boxes
     */
    private function isInside(PdfBox $tile, array $boxes): bool
    {
        return array_any($boxes, fn (PdfBox $box): bool => $tile->xMin >= $box->xMin && $tile->xMax <= $box->xMax
            && $tile->yMin >= $box->yMin && $tile->yMin <= $box->yMax);
    }

    /**
     * Záhlaví sloupců s názvy dnů velkým písmem („PÁTEK SOBOTA NEDĚLE“) — prvek s rozsahem
     * záhlaví a dny v týdnu (ISO). Názvy dnů v rámečku „POUZE“ se nepočítají.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $singleDay
     * @return list<array{PdfBox, list<int>}>
     */
    private function weekdayColumns(array $words, array $singleDay): array
    {
        $columns = [];
        foreach ($words as $word) {
            $weekday = self::WEEKDAYS[$word->text] ?? null;
            $box = new PdfBox($word->text, 0, $word->xMin, $word->yMin, $word->xMax, $word->yMax);
            if ($weekday === null || $word->height() < self::WEEKDAY_MIN_HEIGHT || $this->isInside($box, $singleDay)) {
                continue;
            }

            foreach ($columns as $index => [$column, $days]) {
                $gap = max($box->xMin - $column->xMax, $column->xMin - $box->xMax);
                if (abs($column->yMin - $box->yMin) <= self::WEEKDAY_ROW_TOLERANCE && $gap <= self::WEEKDAY_GAP) {
                    $columns[$index] = [$column->merge($box), [...$days, $weekday]];

                    continue 2;
                }
            }
            $columns[] = [$box, [$weekday]];
        }

        return array_values($columns);
    }

    /**
     * Platnost dlaždice: pod záhlavím s názvy dnů jen ty dny (dlaždice patří ke sloupci s nejbližším
     * středem), jinak platnost letáku. Null, když dny sloupce v letáku nejsou.
     *
     * @param  list<array{PdfBox, list<int>}>  $columns
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function columnValidity(PdfBox $tile, array $columns, array $validity): ?array
    {
        $center = ($tile->xMin + $tile->xMax) / 2;
        $nearest = null;
        foreach ($columns as [$column, $days]) {
            $distance = abs(($column->xMin + $column->xMax) / 2 - $center);
            if ($column->yMax <= $tile->yMin && ($nearest === null || $distance < $nearest[0])) {
                $nearest = [$distance, $days];
            }
        }
        if ($nearest === null) {
            return $validity;
        }

        $dates = [];
        for ($day = $validity[0]; $day->lte($validity[1]); $day = $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $nearest[1], true)) {
                $dates[] = $day;
            }
        }

        return $dates === [] ? null : [$dates[0], $dates[array_key_last($dates)]];
    }

    /**
     * Nabídka z ověřené dlaždice. U klubové akce je `price` běžná akční cena a `loyalty_price`
     * cena s Hruška klubem.
     *
     * @param  Tile  $tile
     * @param  array{price: int, loyalty: int|null}  $verified
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $tile, array $verified, array $validity, int $page, string $sourceUrl): OfferData
    {
        $brand = Text::clean($tile['brand']);
        $name = Text::join($brand, $this->clean(implode(' ', $tile['name']))) ?? '';
        [$packageText, $description] = $this->detailParts($tile);

        return new OfferData(
            externalId: $this->ids->for($name, $packageText, $verified['price']),
            name: $name,
            offerType: OfferType::PromoPrice,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: [
                'page' => $page,
                'brand' => $tile['brand'],
                'name' => $tile['name'],
                'details' => $tile['details'],
                'units' => $tile['units'],
                'price' => $verified['price'],
                'loyaltyPrice' => $verified['loyalty'],
            ],
            price: $verified['price'],
            loyaltyPrice: $verified['loyalty'],
            loyaltyProgram: $verified['loyalty'] === null ? null : LoyaltyProgram::HruskaKlub,
            brand: $brand,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * Balení a popis z řádků popisu: část s množstvím je balení („0,5 l“ z „vybrané druhy | 0,5 l | sklo“),
     * ostatní části popis.
     *
     * @param  Tile  $tile
     * @return array{string|null, string|null}
     */
    private function detailParts(array $tile): array
    {
        $parts = preg_split(self::PART_SEPARATOR_PATTERN, implode(' | ', $tile['details'])) ?: [];

        $package = null;
        $description = [];
        foreach ($parts as $part) {
            $part = Text::clean($part);
            if ($part === null) {
                continue;
            }
            if ($package === null && TileUnits::packages($part) !== []) {
                $package = $part;
            } else {
                $description[] = $part;
            }
        }

        return [$package, Text::clean(implode(self::NAME_SEPARATOR, $description))];
    }

    /**
     * Název bez hvězdičky poznámky; části oddělené „|“ čárkou („Jaternice, jelítka“).
     */
    private function clean(string $name): ?string
    {
        $name = (string) preg_replace(self::FOOTNOTE_PATTERN, '', $name);

        return Text::clean(implode(self::NAME_SEPARATOR, array_filter(
            preg_split(self::PART_SEPARATOR_PATTERN, $name) ?: [],
            fn (string $part): bool => trim($part) !== '',
        )));
    }
}
