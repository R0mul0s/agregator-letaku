<?php

/**
 * Akce s cenou z PDF letáku Globusu — text s polohou z `pdftotext -bbox-layout` (R86, R88), bez LLM.
 *
 * Používá se jen pro letáky, které ještě nezačaly: API vrací jen akce, které už platí (ZDROJE_DAT.md,
 * Globus). Dlaždice letáku:
 * - **název** písmem ~12,6 b., privátní značka „VÁŠ VÝBĚR“ na řádku nad ním („VÁŠ VÝBĚR“ / „Mléko čerstvé“);
 * - pod ním **popis** ~9,6 b. („plnotučné 3,5%“, „1 l“) a **cena za jednotku** ~7,2 b. bez „Kč“
 *   („100 g = 6,63“); s aplikací Můj Globus dvojí: „AC: 100 g = 6,60 KC: 100 g = 5,93“
 *   (AC = akční cena, KC = cena s kartou Můj Globus);
 * - nad názvem **cenovka**: sleva „-17 %“ a přeškrtnutá cena „27“ „90“ (~21 b.), pod nimi velká cena
 *   „22“ (~42 b.) a vedle malé haléře „90“ (~16 b.) jako samostatná slova, nebo „239“ „,-“;
 * - s aplikací je velká cena ta s kartou (písmo se stínem — každá číslice dvakrát, posunutá o ~6 b.)
 *   se slevou z přeškrtnuté ceny a pod ní menší běžná akční cena (~30 b.) s vlastní slevou
 *   a přeškrtnutou cenou (~16 b.).
 *
 * Cena se k dlaždici přiřadí jen **ověřená**: cena přepočtená na balení musí dát každou uvedenou cenu
 * za jednotku (tolerance jako leták Penny, R26) — u dlaždice s aplikací cena s kartou tu „KC“ a běžná
 * cena tu „AC“. Ze sedících dvojic cena–dlaždice vyhrávají nejbližší. Sleva v procentech musí sedět
 * na přeškrtnutou cenu. Co se ověřit nedá (balení 1 kg / 1 l bez ceny za jednotku, pult za 100 g,
 * restaurace, elektro), se neuloží — chybějící akce je lepší než akce se špatnou cenou.
 *
 * Platnost: z dlaždice, z hlavičky strany („Platí od 7. 10. do 2. 11. 2026.“, „platnost 7. 10. –
 * 20. 10. 2026“, „PLATNOST STRANY 7. 10. – 20. 10.“), jinak z letáku. ID akce je otisk názvu
 * a běžné ceny (`GlobusLeafletKey`), aby ji po začátku platnosti převzala akce z API (R88).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfTile;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\TileUnits;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

final class GlobusLeafletParser
{
    /** Výška slov názvu (body PDF; název i „VÁŠ VÝBĚR“ mají ~12,6). */
    private const NAME_MIN_HEIGHT = 11.5;

    private const NAME_MAX_HEIGHT = 13.5;

    /** Výška slov popisu (~9,6; štítky „V AKČNÍ NABÍDCE VÍCE DRUHŮ“ a slogany mají 9,9–10,2). */
    private const DETAIL_MIN_HEIGHT = 9.0;

    private const DETAIL_MAX_HEIGHT = 9.8;

    /** Výška slov ceny za jednotku (~7,2). */
    private const UNIT_MIN_HEIGHT = 6.5;

    private const UNIT_MAX_HEIGHT = 8.0;

    /** Nejmenší výška korun ceny, která může být akční cenou (velká ~42, běžná vedle ceny s kartou ~30). */
    private const ANCHOR_MIN_HEIGHT = 25.0;

    /** Cenu s kartou nese velké písmo (~42–45), běžná cena pod ní je menší. */
    private const HEADLINE_MIN_HEIGHT = 36.0;

    /**
     * Sleva na cenovce má ~16–21 b.; větší „AŽ - 26 %“ je sleva na celou značku, ne na dlaždici,
     * menší „25 % protein“ je popis.
     */
    private const PERCENT_MIN_HEIGHT = 12.0;

    private const PERCENT_MAX_HEIGHT = 25.0;

    /** Stín písma ceny s kartou: stejné slovo posunuté vpravo a svisle (body PDF). */
    private const SHADOW_MIN_DX = 4.0;

    private const SHADOW_MAX_DX = 7.5;

    private const SHADOW_MIN_DY = 1.0;

    private const SHADOW_MAX_DY = 3.0;

    private const SHADOW_HEIGHT_TOLERANCE = 3.0;

    /** Číslice pootočené ceny jsou samostatná slova, která se překrývají („1“ „4“ „5“ = 145). */
    private const GLYPH_MIN_GAP = -4.0;

    private const GLYPH_MAX_GAP = 1.5;

    private const GLYPH_ROW_TOLERANCE = 2.5;

    private const GLYPH_HEIGHT_RATIO = 0.9;

    /** Haléře ceny: hned vpravo od korun (mezera v bodech) a nahoře (podíl výšky korun). */
    private const FRACTION_MIN_GAP = -5.0;

    private const FRACTION_MAX_GAP = 4.0;

    private const FRACTION_TOP_RATIO = 0.4;

    private const FRACTION_TOP_TOLERANCE = 2.0;

    /**
     * Běžná cena u ceny s kartou: pod ní menším písmem, nebo stejně velká vedle či pod ní —
     * nejvýš takový podíl výšky ceny s kartou a taková vzdálenost.
     */
    private const REGULAR_PRICE_MAX_RATIO = 1.1;

    private const REGULAR_PRICE_MAX_DISTANCE = 40.0;

    /** Slova jednoho řádku: odchylka horního okraje a největší mezera mezi slovy. */
    private const ROW_TOLERANCE = 1.5;

    private const WORD_GAP = 6.0;

    /** Řádky jedné dlaždice: zarovnání vlevo (cena za jednotku je odsazená o ~3 b.), mezera a překryv. */
    private const ALIGN_TOLERANCE = 3.0;

    private const UNIT_ALIGN_TOLERANCE = 4.5;

    private const LINE_GAP = 4.0;

    private const LINE_OVERLAP = 6.0;

    /** Největší vzdálenost ceny od textu dlaždice. */
    private const TILE_MAX_DISTANCE = 120.0;

    /** Přeškrtnutá cena leží nad cenou (u běžné ceny vedle ní nahoře): o kolik výš, do stran, do ceny. */
    private const CROSSED_ABOVE = 20.0;

    private const CROSSED_SIDE = 25.0;

    private const CROSSED_OVERLAP_RATIO = 0.5;

    /** Sleva v procentech leží nad cenou nebo vedle ní nahoře. */
    private const PERCENT_ABOVE = 30.0;

    private const PERCENT_SIDE = 30.0;

    private const PERCENT_OVERLAP_RATIO = 0.6;

    /** Mezera mezi číslem a znakem „%“. */
    private const PERCENT_GAP = 4.0;

    /** Hlavička strany s platností je v horní části strany (podíl výšky). */
    private const HEADER_HEIGHT_RATIO = 0.25;

    /** Koruny ceny: číslice („22“, „1999“), u menších cen i s „,-“ v jednom slově („213,-“). */
    private const CROWNS_PATTERN = '/^(\d{1,4})(,-)?$/';

    private const FRACTION_PATTERN = '/^(\d{2}|,-)$/';

    private const DIGITS_PATTERN = '/^\d+$/';

    private const WHOLE_CROWNS = ',-';

    private const PERCENT_SIGN = '%';

    private const PERCENT_NUMBER_PATTERN = '/^[-–]?(\d{1,2})$/u';

    /**
     * Cena za jednotku: „100 g = 6,63“, „1 l = 57,-“, „1 dávka = 0,33“, u více velikostí balení
     * „100 g od 14,94“ a s aplikací „AC: 100 g = 6,60“ / „KC: 100 g = 5,93“.
     */
    private const UNIT_PRICE_PATTERN = '/(?:\b(AC|KC):\s*)?(\d+(?:,\d+)?)\s*(\p{L}+)\s*(=|od)\s*(\d+(?:,(?:\d{1,2}|-))?)/u';

    /** Cena za jednotku „od“ platí pro největší balení. */
    private const UNIT_PRICE_FROM = 'od';

    /** Cena za jednotku k ceně s kartou Můj Globus (KC); bez označení nebo AC je k akční ceně. */
    private const KIND_CARD = 'KC';

    /** Platnost: „od 7. 10. do 2. 11. 2026“, „7. 10. – 20. 10. 2026“, „30. 9. - 27. 10. 2026“. */
    private const VALIDITY_PATTERN = '/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?\.?\s*(?:do|–|-)\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?/u';

    /** Oddělovač řádků popisu v uloženém popisu. */
    private const DESCRIPTION_SEPARATOR = ', ';

    private const DOUBLE_SEPARATOR_PATTERN = '/,\s*,\s*/';

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LeafletDates $dates,
        private readonly GlobusLeafletKey $keys,
    ) {}

    /**
     * Ověřené akce celého letáku; stejná akce na více stranách se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @param  string  $sourceUrl  Odkaz akce (stránka akční nabídky)
     * @return list<OfferData>
     */
    public function offers(array $pages, array $validity, string $sourceUrl): array
    {
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
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @return list<OfferData>
     */
    private function pageOffers(PdfPage $page, array $validity, string $sourceUrl): array
    {
        $words = $this->mergeGlyphs($this->withoutShadows($page->words()));
        $prices = $this->prices($words);
        $anchors = array_values(array_filter($prices, fn (PdfBox $price): bool => $price->height() >= self::ANCHOR_MIN_HEIGHT));
        $small = array_values(array_filter($prices, fn (PdfBox $price): bool => $price->height() < self::ANCHOR_MIN_HEIGHT));
        $tiles = $this->tiles($words, $prices);
        $crossed = PdfLayout::nearest($anchors, $small, $this->crossedDistance(...));
        $percents = PdfLayout::nearest($anchors, $this->percents($words), $this->percentDistance(...));
        $pageValidity = $this->pageValidity($page, $validity);

        $offers = [];
        foreach ($this->matches($anchors, $tiles) as [$tile, $verified]) {
            $offer = $this->offer($tile, $verified, $anchors, $crossed, $percents, $pageValidity, $page->number, $sourceUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * Ověřené dvojice cena–dlaždice: ze všech sedících vyhrávají nejbližší, každá cena
     * i dlaždice nejvýš jednou (běžná cena pod cenou s kartou se tím také spotřebuje).
     *
     * @param  list<PdfBox>  $anchors  Ceny, které můžou být akční cenou
     * @param  list<PdfTile>  $tiles
     * @return list<array{PdfTile, array{headline: int, regular: int|null}}> Indexy cen: velká a běžná pod ní (u karty)
     */
    private function matches(array $anchors, array $tiles): array
    {
        $facts = array_map($this->tileFacts(...), $tiles);

        $pairs = PdfLayout::greedyPairs(
            $tiles,
            $anchors,
            function (PdfTile $tile, PdfBox $price, int $tileIndex, int $priceIndex) use ($facts, $anchors): ?array {
                $distance = $price->distanceTo($tile->box);
                $verified = $distance > self::TILE_MAX_DISTANCE ? null : $this->verify($priceIndex, $anchors, $facts[$tileIndex]);

                return $verified === null ? null : [$distance, $verified];
            },
            // Běžná cena pod cenou s kartou patří k téže dlaždici
            fn (array $verified): array => $verified['regular'] === null ? [] : [$verified['regular']],
        );

        return array_map(fn (array $pair): array => [$tiles[$pair[0]], $pair[2]], $pairs);
    }

    /**
     * Slova bez stínu písma: ceny s kartou mají každé slovo dvakrát, posunuté vpravo a svisle.
     * Zůstane levé z dvojice.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfWord>
     */
    private function withoutShadows(array $words): array
    {
        $shadows = [];
        foreach ($words as $index => $word) {
            foreach ($words as $other) {
                $dx = $word->xMin - $other->xMin;
                $dy = abs($word->yMin - $other->yMin);
                if ($other !== $word && $other->text === $word->text
                    && $dx >= self::SHADOW_MIN_DX && $dx <= self::SHADOW_MAX_DX
                    && $dy >= self::SHADOW_MIN_DY && $dy <= self::SHADOW_MAX_DY
                    && abs($word->height() - $other->height()) <= self::SHADOW_HEIGHT_TOLERANCE) {
                    $shadows[$index] = true;

                    break;
                }
            }
        }

        return array_values(array_diff_key($words, $shadows));
    }

    /**
     * Číslice pootočené ceny jako jedno slovo: „1“ „4“ „5“ těsně za sebou stejným písmem = „145“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfWord>
     */
    private function mergeGlyphs(array $words): array
    {
        usort($words, fn (PdfWord $a, PdfWord $b): int => $a->xMin <=> $b->xMin);

        $merged = [];
        foreach ($words as $word) {
            if (preg_match(self::DIGITS_PATTERN, $word->text) === 1) {
                foreach ($merged as $index => $previous) {
                    $gap = $word->xMin - $previous->xMax;
                    if (preg_match(self::DIGITS_PATTERN, $previous->text) === 1
                        && $gap >= self::GLYPH_MIN_GAP && $gap <= self::GLYPH_MAX_GAP
                        && abs($word->yMin - $previous->yMin) <= self::GLYPH_ROW_TOLERANCE
                        && min($word->height(), $previous->height()) >= max($word->height(), $previous->height()) * self::GLYPH_HEIGHT_RATIO) {
                        $merged[$index] = new PdfWord(
                            $previous->text.$word->text,
                            $previous->xMin, min($previous->yMin, $word->yMin), $word->xMax, max($previous->yMax, $word->yMax),
                        );

                        continue 2;
                    }
                }
            }
            $merged[] = $word;
        }

        return array_values($merged);
    }

    /**
     * Ceny na stránce: koruny a vedle nich haléře („22“ „90“), „239“ „,-“ nebo „213,-“ jedním slovem.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function prices(array $words): array
    {
        $fractions = array_values(array_filter($words, fn (PdfWord $word): bool => preg_match(self::FRACTION_PATTERN, $word->text) === 1));

        $prices = [];
        foreach ($words as $crowns) {
            if (preg_match(self::CROWNS_PATTERN, $crowns->text, $m) !== 1) {
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
                    $this->priceParser->parse($m[1].','.$halers),
                    $crowns->xMin, min($crowns->yMin, $fraction->yMin), max($crowns->xMax, $fraction->xMax), max($crowns->yMax, $fraction->yMax),
                );

                break;
            }
        }

        return $prices;
    }

    /**
     * Slevy v procentech na cenovce: číslo („-17“, „50“) a hned za ním „%“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function percents(array $words): array
    {
        return PdfLayout::percents($words, self::PERCENT_SIGN, self::PERCENT_NUMBER_PATTERN, self::ROW_TOLERANCE, self::PERCENT_GAP, [self::PERCENT_MIN_HEIGHT, self::PERCENT_MAX_HEIGHT]);
    }

    /**
     * Vzdálenost přeškrtnuté ceny od ceny, nebo null, když nad ní (vedle ní nahoře) neleží.
     */
    private function crossedDistance(PdfBox $price, PdfBox $crossed): ?float
    {
        return PdfLayout::aboveWithin($price, $crossed, self::CROSSED_ABOVE, self::CROSSED_OVERLAP_RATIO, self::CROSSED_SIDE);
    }

    /**
     * Vzdálenost slevy v procentech od ceny, nebo null, když nad ní (vedle ní nahoře) neleží.
     */
    private function percentDistance(PdfBox $price, PdfBox $percent): ?float
    {
        return PdfLayout::aboveOverlapping($price, $percent, self::PERCENT_ABOVE, self::PERCENT_OVERLAP_RATIO, self::PERCENT_SIDE);
    }

    /**
     * Textové části dlaždic: řádky názvu, pod nimi zarovnané řádky popisu a ceny za jednotku.
     * Dlaždice bez popisu i ceny za jednotku se vynechá — cenu by nebylo čím ověřit.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $prices  Ceny — jejich slova do textu dlaždice nepatří
     * @return list<PdfTile>
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
            if (isset($usedNames[$index]) || preg_match('/\p{L}/u', $first->text) !== 1) {
                continue;
            }

            $usedNames[$index] = true;
            $nameLines = [$first, ...$this->below($names, $first, $first, $usedNames, self::ALIGN_TOLERANCE)];
            $detailLines = $this->below($details, $first, $nameLines[array_key_last($nameLines)], $usedDetails, self::ALIGN_TOLERANCE);
            $last = $detailLines === [] ? $nameLines[array_key_last($nameLines)] : $detailLines[array_key_last($detailLines)];
            $unitLines = $this->below($units, $first, $last, $usedUnits, self::UNIT_ALIGN_TOLERANCE);
            if ($detailLines === [] && $unitLines === []) {
                continue;
            }

            $lines = [...$nameLines, ...$detailLines, ...$unitLines];
            $tiles[] = new PdfTile(
                array_map(fn (PdfBox $line): string => $line->text, $nameLines),
                array_map(fn (PdfBox $line): string => $line->text, [...$detailLines, ...$unitLines]),
                array_reduce($lines, fn (PdfBox $box, PdfBox $line): PdfBox => $box->merge($line), $first),
            );
        }

        return $tiles;
    }

    /**
     * Řádky pod `$last` zarovnané vlevo s prvním řádkem dlaždice, jeden pod druhým.
     *
     * @param  list<PdfBox>  $rows
     * @param  array<int, true>  $used  Řádky, které už patří jiné dlaždici
     * @return list<PdfBox>
     */
    private function below(array $rows, PdfBox $first, PdfBox $last, array &$used, float $alignTolerance): array
    {
        return PdfLayout::columnBelow($rows, $first, $last, $used, $alignTolerance, self::LINE_OVERLAP, self::LINE_GAP);
    }

    /**
     * Ceny za jednotku (s druhem AC / KC, jinak null) a balení z popisu dlaždice.
     *
     * @return array{unitPrices: list<array{kind: string|null, unit: string, quantity: float, from: bool, value: int}>, packages: array<string, list<float>>}
     */
    private function tileFacts(PdfTile $tile): array
    {
        $text = $tile->detailText();
        preg_match_all(self::UNIT_PRICE_PATTERN, $text, $matches, PREG_SET_ORDER);

        $unitPrices = [];
        foreach ($matches as $m) {
            $unit = TileUnits::unit($m[3]);
            if ($unit !== null) {
                $unitPrices[] = [
                    'kind' => $m[1] === '' ? null : $m[1],
                    'unit' => $unit[0],
                    'quantity' => TileUnits::number($m[2]) * $unit[1],
                    'from' => $m[4] === self::UNIT_PRICE_FROM,
                    'value' => $this->priceParser->parse(str_replace(self::WHOLE_CROWNS, ',00', $m[5])),
                ];
            }
        }

        return ['unitPrices' => $unitPrices, 'packages' => TileUnits::packages((string) preg_replace(self::UNIT_PRICE_PATTERN, ' ', $text))];
    }

    /**
     * Ověří velkou cenu proti dlaždici. S cenou s kartou (KC) je velká cena ta s kartou a běžná
     * akční cena (AC) se hledá pod ní; jinak musí velká cena dát každou cenu za jednotku.
     *
     * @param  list<PdfBox>  $anchors
     * @param  array{unitPrices: list<array{kind: string|null, unit: string, quantity: float, from: bool, value: int}>, packages: array<string, list<float>>}  $facts
     * @return array{headline: int, regular: int|null}|null Index velké ceny a běžné ceny pod ní (u karty)
     */
    private function verify(int $priceIndex, array $anchors, array $facts): ?array
    {
        $comparable = array_values(array_filter($facts['unitPrices'], fn (array $unitPrice): bool => ($facts['packages'][$unitPrice['unit']] ?? []) !== []));
        $card = array_values(array_filter($comparable, fn (array $unitPrice): bool => $unitPrice['kind'] === self::KIND_CARD));
        $regular = array_values(array_filter($comparable, fn (array $unitPrice): bool => $unitPrice['kind'] !== self::KIND_CARD));
        if ($regular === []) {
            return null;
        }

        $price = $anchors[$priceIndex];
        if ($card === []) {
            return $this->matchesAll($price->value, $regular, $facts['packages']) ? ['headline' => $priceIndex, 'regular' => null] : null;
        }

        if ($price->height() < self::HEADLINE_MIN_HEIGHT || ! $this->matchesAll($price->value, $card, $facts['packages'])) {
            return null;
        }

        foreach ($this->regularPrices($price, $anchors) as $index) {
            if ($anchors[$index]->value > $price->value && $this->matchesAll($anchors[$index]->value, $regular, $facts['packages'])) {
                return ['headline' => $priceIndex, 'regular' => $index];
            }
        }

        return null;
    }

    /**
     * Dá cena každou cenu za jednotku ze seznamu?
     *
     * @param  list<array{kind: string|null, unit: string, quantity: float, from: bool, value: int}>  $unitPrices
     * @param  array<string, list<float>>  $packages
     */
    private function matchesAll(int $price, array $unitPrices, array $packages): bool
    {
        foreach ($unitPrices as $unitPrice) {
            if (! UnitPriceCheck::matchesAny($price, $unitPrice['value'], $unitPrice['quantity'], $unitPrice['from'], $packages[$unitPrice['unit']])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kandidáti na běžnou akční cenu: jiná cena blízko ceny s kartou, nejbližší první.
     * Poloha se liší (pod ní menším písmem, pod ní stejně velká) — rozhoduje ověření cenou
     * za jednotku „AC“.
     *
     * @param  list<PdfBox>  $anchors
     * @return list<int> Indexy cen
     */
    private function regularPrices(PdfBox $price, array $anchors): array
    {
        $candidates = array_keys(array_filter($anchors, fn (PdfBox $candidate): bool => $candidate !== $price
            && $candidate->height() <= $price->height() * self::REGULAR_PRICE_MAX_RATIO
            && $price->distanceTo($candidate) <= self::REGULAR_PRICE_MAX_DISTANCE));
        usort($candidates, fn (int $a, int $b): int => $price->distanceTo($anchors[$a]) <=> $price->distanceTo($anchors[$b]));

        return $candidates;
    }

    /**
     * Platnost stránky: první údaj „od … do …“ v hlavičce strany, jinak platnost letáku.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function pageValidity(PdfPage $page, array $leaflet): array
    {
        $header = array_filter($page->lines, fn (PdfLine $line): bool => $line->yMin < $page->height * self::HEADER_HEIGHT_RATIO);
        usort($header, fn (PdfLine $a, PdfLine $b): int => $a->yMin <=> $b->yMin ?: $a->xMin <=> $b->xMin);

        return $this->validity(implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $header)), $leaflet) ?? $leaflet;
    }

    /**
     * První platnost „D. M. do D. M. RRRR“ v textu; bez roku nejbližší ke konci letáku (i přes
     * Nový rok, R113). Null bez platnosti nebo s koncem před začátkem.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    private function validity(string $text, array $leaflet): ?array
    {
        if (preg_match(self::VALIDITY_PATTERN, $text, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        return $this->dates->range(
            (int) $m[1], (int) $m[2], $m[3] === null ? null : (int) $m[3],
            (int) $m[4], (int) $m[5], $m[6] === null ? null : (int) $m[6],
            $leaflet[1],
        );
    }

    /**
     * Nabídka z ověřené dlaždice; null, když přeškrtnutá cena nebo sleva v procentech nesedí.
     * U dlaždice s kartou je `price` běžná akční cena a `loyalty_price` cena s kartou Můj Globus.
     *
     * @param  array{headline: int, regular: int|null}  $verified  Indexy cen
     * @param  list<PdfBox>  $anchors
     * @param  array<int, PdfBox>  $crossed  Index ceny => přeškrtnutá cena u ní
     * @param  array<int, PdfBox>  $percents  Index ceny => sleva v procentech u ní
     * @param  array{CarbonImmutable, CarbonImmutable}  $pageValidity
     */
    private function offer(PdfTile $tile, array $verified, array $anchors, array $crossed, array $percents, array $pageValidity, int $page, string $sourceUrl): ?OfferData
    {
        $headline = $verified['headline'];
        $regular = $verified['regular'] ?? $headline;
        $price = $anchors[$regular]->value;
        $loyalty = $verified['regular'] === null ? null : $anchors[$headline]->value;

        // Přeškrtnutá cena leží u běžné ceny (u karty mezi oběma cenami)
        $original = ($crossed[$regular] ?? $crossed[$headline] ?? null)?->value;
        if ($original !== null && $original <= $price) {
            return null;
        }
        // Každá sleva na cenovce musí sedět na přeškrtnutou cenu — bez ní ji nejde ověřit
        foreach ([$headline => $loyalty ?? $price, $regular => $price] as $index => $amount) {
            $percent = $percents[$index] ?? null;
            if ($percent !== null && ($original === null || ! DiscountCheck::truncatedOrRounded($original, $amount, $percent->value))) {
                return null;
            }
        }

        // Řádek názvu končící lomítkem pokračuje bez mezery („Hladká/“ „Polohrubá“)
        $name = Text::clean(str_replace('/ ', '/', implode(' ', $tile->nameLines))) ?? '';
        $packageLine = $this->packageLine($tile);
        $packageText = $packageLine === null ? null : Text::clean(trim((string) preg_replace(self::UNIT_PRICE_PATTERN, '', $packageLine), ' ,'));
        // Řádek končící čárkou pokračuje na dalším („nevaječné,“ „různé druhy“)
        $description = Text::clean(rtrim((string) preg_replace(self::DOUBLE_SEPARATOR_PATTERN, self::DESCRIPTION_SEPARATOR, implode(self::DESCRIPTION_SEPARATOR, array_filter(
            $tile->detailLines,
            fn (string $line): bool => $line !== $packageLine && preg_match(self::UNIT_PRICE_PATTERN, $line) !== 1,
        ))), self::DESCRIPTION_SEPARATOR));
        [$validFrom, $validTo] = $this->validity($tile->detailText(), $pageValidity) ?? $pageValidity;

        return new OfferData(
            externalId: $this->keys->for($name, $price),
            name: $name,
            offerType: $original !== null ? OfferType::Discount : OfferType::PromoPrice,
            validFrom: $validFrom,
            validTo: $validTo,
            raw: [
                'page' => $page,
                'name' => $tile->nameLines,
                'details' => $tile->detailLines,
                'price' => $price,
                'loyaltyPrice' => $loyalty,
                'crossed' => $original,
                'percent' => ($percents[$regular] ?? null)?->value,
                'loyaltyPercent' => $loyalty === null ? null : ($percents[$headline] ?? null)?->value,
            ],
            price: $price,
            originalPrice: $original,
            loyaltyPrice: $loyalty,
            loyaltyProgram: $loyalty === null ? null : LoyaltyProgram::MujGlobus,
            // Sleva běžné ceny z přeškrtnuté ceny, jako `discountPercentage` v API
            discountPercent: $original !== null ? ($percents[$regular] ?? null)?->value : null,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * Řádek s balením — první řádek popisu s množstvím v jednotce, ve které je uvedená cena
     * za jednotku („150 g“ u „100 g = 6,60“, ne „plnotučné 3,5%“).
     */
    private function packageLine(PdfTile $tile): ?string
    {
        $units = array_map(fn (array $unitPrice): string => $unitPrice['unit'], $this->tileFacts($tile)['unitPrices']);
        foreach ($tile->detailLines as $line) {
            if (TileUnits::hasPackageIn((string) preg_replace(self::UNIT_PRICE_PATTERN, ' ', $line), $units)) {
                return $line;
            }
        }

        return null;
    }
}
