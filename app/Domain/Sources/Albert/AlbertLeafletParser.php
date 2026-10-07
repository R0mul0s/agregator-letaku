<?php

/**
 * Akce s cenou z PDF letáku Albertu — text s polohou z `pdftotext -bbox-layout` (R86), bez LLM.
 *
 * Dlaždice letáku (ZDROJE_DAT.md, Albert):
 * - **název** písmem ~14,6 b. („Fa“ / „Deodorant sprej“), pod ním **popis** ~10,5 b. s odrážkami:
 *   balení, cena za jednotku („100 ml = 33,27 Kč“, „1 ks od 4,99 Kč“), „vybrané druhy“,
 *   „platí do …“ a nejnižší cena za 30 dní („▼ 44,90 Kč“ — není to akční cena);
 * - **akční cena** ~31–50 b.: koruny a haléře jako dvě slova („49“ „90“, haléře menší a nahoře)
 *   nebo „599,“ „-“; **přeškrtnutá / běžná cena** jedním slovem („69,90“, „169,-“, „1199,-/“);
 *   **sleva** „- 50 %“;
 * - s aplikací Můj Albert je velká cena ta s aplikací, menší „BEZ APLIKACE 39 90“ pod ní je běžná
 *   a cena za jednotku je dvojí („1 l = 53,20 Kč bez Aplikace / 46,54 Kč Aplikace“).
 *
 * Cena bývá nad názvem, pod ním i vedle něj, proto se k dlaždici přiřadí jen **ověřená**: cena
 * přepočtená na balení musí dát uvedenou cenu za jednotku (jako leták Penny, R26). Ze sedících
 * dvojic cena–dlaždice vyhrávají nejbližší, každá cena i dlaždice patří nejvýš jedné. Je-li
 * u ceny přeškrtnutá cena i sleva v procentech, musí procento sedět. Co se ověřit nedá
 * (dlaždice bez ceny za jednotku, „cena za 100 g“, akce na více kusů), se neuloží — zůstane
 * zmínkou ze stránky (R36). Chybějící akce je lepší než akce se špatnou cenou.
 *
 * Platnost: „platí do …“ v dlaždici, hvězdička u názvu s poznámkou „*Tato nabídka platí od …“,
 * oddíl „PLATÍ POUZE PÁ–NE“ s daty velkým písmem, „Nabídka na této straně platí od … do …“,
 * jinak platnost letáku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
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
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

final class AlbertLeafletParser
{
    /** Výška slov názvu (body PDF; název má ~14,6). */
    private const NAME_MIN_HEIGHT = 13.0;

    private const NAME_MAX_HEIGHT = 16.5;

    /** Výška slov popisu (~10,5). */
    private const DETAIL_MIN_HEIGHT = 9.5;

    private const DETAIL_MAX_HEIGHT = 11.5;

    /** Nejmenší výška korun akční ceny (31–50; „+ 1“ a body kreditu mají ~25 bez haléřů). */
    private const PRICE_MIN_HEIGHT = 24.0;

    /** Cena bez aplikace je pod cenou s aplikací a nejvýš takový podíl její výšky. */
    private const REGULAR_PRICE_MAX_RATIO = 0.75;

    private const REGULAR_PRICE_OVERLAP = 10.0;

    private const REGULAR_PRICE_BELOW = 25.0;

    /** Haléře ceny: hned vpravo od korun (mezera v bodech) a nahoře (podíl výšky korun). */
    private const FRACTION_MIN_GAP = -5.0;

    private const FRACTION_MAX_GAP = 4.0;

    private const FRACTION_TOP_RATIO = 0.4;

    private const FRACTION_TOP_TOLERANCE = 2.0;

    /** Slova jednoho řádku: odchylka horního okraje a největší mezera mezi slovy. */
    private const ROW_TOLERANCE = 1.5;

    private const WORD_GAP = 6.0;

    /** Řádky jedné dlaždice: zarovnání vlevo, mezera a překryv sousedních řádků. */
    private const ALIGN_TOLERANCE = 3.0;

    private const LINE_GAP = 4.0;

    private const LINE_OVERLAP = 6.0;

    /** Největší vzdálenost ceny od textu dlaždice. */
    private const TILE_MAX_DISTANCE = 120.0;

    /** Přeškrtnutá cena leží nad cenou: o kolik výš, jak daleko do stran, jak hluboko do ceny. */
    private const CROSSED_ABOVE = 20.0;

    private const CROSSED_SIDE = 25.0;

    private const CROSSED_OVERLAP_RATIO = 0.5;

    /** Sleva v procentech leží nad cenou nebo vedle ní nahoře. */
    private const PERCENT_ABOVE = 30.0;

    private const PERCENT_SIDE = 30.0;

    private const PERCENT_OVERLAP_RATIO = 0.6;

    /** Mezera mezi číslem a znakem „%“. */
    private const PERCENT_GAP = 4.0;

    /** Data oddílu „PLATÍ POUZE PÁ–NE“ jsou velkým písmem (~26). */
    private const SECTION_DATE_MIN_HEIGHT = 20.0;

    private const SECTION_DATE_COUNT = 2;

    /** Koruny ceny („49“) nebo koruny s čárkou před pomlčkou („599,“). */
    private const CROWNS_PATTERN = '/^(\d{1,4})(,?)$/';

    private const FRACTION_PATTERN = '/^(\d{2}|-)$/';

    /** Přeškrtnutá nebo běžná cena jedním slovem: „69,90“, „169,-“, „1199,-/“. */
    private const CROSSED_PATTERN = '/^(\d{1,4}),(\d{2}|-)\/?$/';

    private const CURRENCY = 'Kč';

    private const PERCENT_SIGN = '%';

    private const PERCENT_NUMBER_PATTERN = '/^[-–]?(\d{1,2})$/u';

    /**
     * Cena za jednotku: „100 ml = 33,27 Kč“, „1 ks od 4,99 Kč“, „1 dávka = 1,11 Kč“ a s aplikací
     * „100 g = 29,09 Kč bez Aplikace / 24,92 Kč Aplikace“.
     */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:,\d+)?)\s*(\p{L}+)\s*(=|od)\s*(\d+(?:,\d{1,2})?)\s*Kč(?:\s*bez\s+Aplikace\s*\/\s*(?:od\s+)?(\d+(?:,\d{1,2})?)\s*Kč\s*Aplikace)?/iu';

    /** Částka v Kč — po odebrání ceny za jednotku zbude nejnižší cena za 30 dní, záloha… */
    private const AMOUNT_PATTERN = '/\d+(?:,\d{1,2})?\s*Kč/u';

    /** Balení: „150 ml“, „3× 50 g“, „750–1000 ml“, „126 dávek“. */
    private const PACKAGE_PATTERN = '/(?:(\d+)\s*[×x]\s*)?(\d+(?:,\d+)?)(?:\s*[–-]\s*(\d+(?:,\d+)?))?\s*(\p{L}+)/u';

    /**
     * Jednotky balení => [jednotka pro porovnání, násobek]. Jiné slovo (dávka, role, praní)
     * se porovná podle prvních písmen (UNIT_STEM_LENGTH): „126 dávek“ ↔ „1 dávka“.
     *
     * @var array<string, array{string, int}>
     */
    private const UNITS = [
        'g' => ['g', 1], 'kg' => ['g', 1000],
        'ml' => ['ml', 1], 'l' => ['ml', 1000],
        'ks' => ['ks', 1], 'kus' => ['ks', 1], 'kusy' => ['ks', 1], 'kusů' => ['ks', 1],
    ];

    private const UNIT_STEM_LENGTH = 3;

    /** Nejnižší cena za 30 dní („▼ 44,90 Kč“) — samotná částka v odrážce popisu. */
    private const LOWEST_PRICE_PATTERN = '/^\d+(?:,\d{1,2})?\s*Kč$/u';

    /** Částka na konci odrážky („vybrané druhy 69,90 Kč“ = chybějící odrážka před ▼). */
    private const TRAILING_AMOUNT_PATTERN = '/^(.*\S)\s+\d+(?:,\d{1,2})?\s*Kč$/u';

    /** Před částkou, která k odrážce patří („= 25,80 Kč“, „od 4,99 Kč“, „záloha na láhev 3 Kč“). */
    private const AMOUNT_CONTEXT_PATTERN = '/(=|\/|\bod|\bza|láhev)$/iu';

    /** Cena za kus jen při koupi více kusů: „cena za 1 bal. 479 Kč“ vedle „CENA ZA 1 bal. PŘI KOUPI 2 bal.“. */
    private const MULTIBUY_PATTERN = '/\bcena za 1\s*(?:ks|bal)\b/iu';

    private const BULLET_PATTERN = '/[•▼]/u';

    private const NAME_STAR = '*';

    /** Platnost v dlaždici. */
    private const TILE_VALID_TO_PATTERN = '/platí\s+do\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u';

    private const TILE_VALID_FROM_PATTERN = '/platí\s+od\s+(?:\p{L}+\s+)?(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?/u';

    /** Platnost celé stránky v patičce. */
    private const PAGE_VALIDITY_PATTERN = '/Nabídka na této straně platí od (\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?\s*do (\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u';

    /** Poznámka k produktům s hvězdičkou: „*Tato nabídka platí od čtvrtka 1. 10. 2026.“ */
    private const FOOTNOTE_VALIDITY_PATTERN = '/(\*+)\s*Tato nabídka platí od (?:\p{L}+\s+)?(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})?(?:\s*do (\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4}))?/u';

    /** Datum oddílu velkým písmem („2. 10. 2026“, mezery uvnitř vynechané). */
    private const SECTION_DATE_PATTERN = '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/';

    /** Externí ID — leták nemá kód zboží, ID je otisk názvu, balení a cen. */
    private const EXTERNAL_ID_PREFIX = 'letak-';

    private const EXTERNAL_ID_LENGTH = 16;

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Ověřené akce celého letáku; stejná akce na více stranách (titulní strana opakuje
     * další, některé strany jsou v letáku dvakrát) se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity  Platnost letáku
     * @param  string  $pageUrl  Adresa stránky letáku s číslem stránky jako %d
     * @return list<OfferData>
     */
    public function offers(array $pages, array $validity, string $pageUrl): array
    {
        $offers = [];
        foreach ($pages as $page) {
            foreach ($this->pageOffers($page, $validity, sprintf($pageUrl, $page->number)) as $offer) {
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
    private function pageOffers(PdfPage $page, array $validity, string $pageUrl): array
    {
        $words = $page->words();
        $prices = $this->prices($words);
        $big = array_values(array_filter($prices, fn (PdfBox $price): bool => $price->height() >= self::PRICE_MIN_HEIGHT));
        $crossedPrices = $this->crossedPrices($words);
        $tiles = $this->tiles($words, [...$prices, ...$crossedPrices]);
        $crossed = PdfLayout::nearest($big, $crossedPrices, $this->crossedDistance(...));
        $percents = PdfLayout::nearest($big, $this->percents($words), $this->percentDistance(...));
        $context = $this->validityContext($page, $validity);

        $offers = [];
        foreach ($this->matches($big, $tiles, $prices, $crossed) as [$priceIndex, $tile, $verified]) {
            $offer = $this->offer($tile, $verified, $crossed[$priceIndex] ?? null, $percents[$priceIndex] ?? null, $context, $page->number, $pageUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * Ověřené dvojice cena–dlaždice: ze všech sedících vyhrávají nejbližší, každá cena
     * i dlaždice nejvýš jednou.
     *
     * @param  list<PdfBox>  $big  Akční ceny
     * @param  list<PdfTile>  $tiles
     * @param  list<PdfBox>  $prices  Všechny ceny (i menší ceny bez aplikace)
     * @param  array<int, PdfBox>  $crossed  Index akční ceny => přeškrtnutá cena u ní
     * @return list<array{int, PdfTile, array{price: int, loyalty: int|null}}>
     */
    private function matches(array $big, array $tiles, array $prices, array $crossed): array
    {
        $facts = array_map($this->tileFacts(...), $tiles);

        $pairs = [];
        foreach ($big as $priceIndex => $price) {
            foreach ($tiles as $tileIndex => $tile) {
                $distance = $price->distanceTo($tile->box);
                if ($distance > self::TILE_MAX_DISTANCE) {
                    continue;
                }

                $verified = $this->verify($price, $facts[$tileIndex], $prices, $crossed[$priceIndex] ?? null);
                if ($verified !== null) {
                    $pairs[] = [$distance, $priceIndex, $tileIndex, $verified];
                }
            }
        }
        usort($pairs, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $usedPrices = [];
        $usedTiles = [];
        $matches = [];
        foreach ($pairs as [, $priceIndex, $tileIndex, $verified]) {
            if (isset($usedPrices[$priceIndex]) || isset($usedTiles[$tileIndex])) {
                continue;
            }
            $usedPrices[$priceIndex] = true;
            $usedTiles[$tileIndex] = true;
            $matches[] = [$priceIndex, $tiles[$tileIndex], $verified];
        }

        return $matches;
    }

    /**
     * Ceny na stránce: koruny a vedle nich haléře („49“ „90“) nebo „599,“ „-“.
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

            foreach ($fractions as $fraction) {
                $gap = $fraction->xMin - $crowns->xMax;
                $top = $fraction->yMin - $crowns->yMin;
                if ($fraction === $crowns || $gap < self::FRACTION_MIN_GAP || $gap > self::FRACTION_MAX_GAP
                    || $top < -self::FRACTION_TOP_TOLERANCE || $top > $crowns->height() * self::FRACTION_TOP_RATIO) {
                    continue;
                }

                // „599,“ + „-“ = celé koruny; „49“ + „90“ = haléře menším písmem
                $halers = match (true) {
                    $m[2] === ',' && $fraction->text === '-' => '00',
                    $m[2] === '' && $fraction->text !== '-' && $fraction->height() < $crowns->height() => $fraction->text,
                    default => null,
                };
                if ($halers !== null) {
                    $prices[] = new PdfBox(
                        $crowns->text.' '.$fraction->text,
                        $this->priceParser->parse($m[1].','.$halers),
                        $crowns->xMin, min($crowns->yMin, $fraction->yMin), max($crowns->xMax, $fraction->xMax), max($crowns->yMax, $fraction->yMax),
                    );

                    break;
                }
            }
        }

        return $prices;
    }

    /**
     * Přeškrtnuté a běžné ceny jedním slovem („69,90“, „169,-“, „1199,-/“) — bez dalšího
     * slova za sebou („19,90 Kč“ je nejnižší cena za 30 dní, „0,75 l“ balení).
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function crossedPrices(array $words): array
    {
        $crossed = [];
        foreach ($words as $word) {
            if (preg_match(self::CROSSED_PATTERN, $word->text, $m) === 1 && ! $this->followedByWord($word, $words)) {
                $crossed[] = new PdfBox(
                    $word->text,
                    $this->priceParser->parse($m[1].','.($m[2] === '-' ? '00' : $m[2])),
                    $word->xMin, $word->yMin, $word->xMax, $word->yMax,
                );
            }
        }

        return $crossed;
    }

    /**
     * Slevy v procentech: číslo (případně „-“ před ním) a hned za ním „%“.
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function percents(array $words): array
    {
        $percents = [];
        foreach ($words as $sign) {
            if ($sign->text !== self::PERCENT_SIGN) {
                continue;
            }

            foreach ($words as $number) {
                $gap = $sign->xMin - $number->xMax;
                if ($gap >= -self::ROW_TOLERANCE && $gap <= self::PERCENT_GAP && $number->yMax > $sign->yMin && $number->yMin < $sign->yMax
                    && preg_match(self::PERCENT_NUMBER_PATTERN, $number->text, $m) === 1) {
                    $percents[] = new PdfBox($number->text.' %', (int) $m[1], $number->xMin, min($number->yMin, $sign->yMin), $sign->xMax, max($number->yMax, $sign->yMax));

                    break;
                }
            }
        }

        return $percents;
    }

    /**
     * Následuje za slovem na stejném řádku další slovo („Kč“, „l“)?
     *
     * @param  list<PdfWord>  $words
     */
    private function followedByWord(PdfWord $word, array $words): bool
    {
        foreach ($words as $next) {
            $gap = $next->xMin - $word->xMax;
            if ($next !== $word && $gap >= -self::ROW_TOLERANCE && $gap <= self::WORD_GAP && abs($next->yMin - $word->yMin) <= self::ROW_TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vzdálenost přeškrtnuté ceny od ceny, nebo null, když nad ní neleží.
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
     * Textové části dlaždic: řádky názvu a pod nimi zarovnané řádky popisu. Dlaždice bez
     * popisu se vynechá — cenu by nebylo čím ověřit.
     *
     * @param  list<PdfWord>  $words
     * @param  list<PdfBox>  $prices  Ceny a přeškrtnuté ceny — jejich slova do textu dlaždice nepatří
     * @return list<PdfTile>
     */
    private function tiles(array $words, array $prices): array
    {
        $text = array_values(array_filter($words, fn (PdfWord $word): bool => ! $this->isPricePart($word, $prices)));
        $names = PdfLayout::rows(array_values(array_filter($text, fn (PdfWord $word): bool => $word->height() >= self::NAME_MIN_HEIGHT && $word->height() <= self::NAME_MAX_HEIGHT)), self::ROW_TOLERANCE, self::WORD_GAP);
        $details = PdfLayout::rows(array_values(array_filter($text, fn (PdfWord $word): bool => $word->height() >= self::DETAIL_MIN_HEIGHT && $word->height() <= self::DETAIL_MAX_HEIGHT)), self::ROW_TOLERANCE, self::WORD_GAP);

        $usedNames = [];
        $usedDetails = [];
        $tiles = [];
        foreach ($names as $index => $first) {
            if (isset($usedNames[$index]) || preg_match('/\p{L}/u', $first->text) !== 1) {
                continue;
            }

            $usedNames[$index] = true;
            $nameLines = [$first, ...$this->below($names, $first, $first, $usedNames)];
            $detailLines = $this->below($details, $first, $nameLines[array_key_last($nameLines)], $usedDetails);
            if ($detailLines === []) {
                continue;
            }

            $box = array_reduce($detailLines, fn (PdfBox $box, PdfBox $line): PdfBox => $box->merge($line), $first);
            $tiles[] = new PdfTile(
                array_map(fn (PdfBox $line): string => $line->text, $nameLines),
                array_map(fn (PdfBox $line): string => $line->text, $detailLines),
                array_reduce($nameLines, fn (PdfBox $box, PdfBox $line): PdfBox => $box->merge($line), $box),
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
    private function below(array $rows, PdfBox $first, PdfBox $last, array &$used): array
    {
        $column = [];
        while (true) {
            $next = null;
            foreach ($rows as $index => $row) {
                $gap = $row->yMin - $last->yMax;
                if (! isset($used[$index]) && abs($row->xMin - $first->xMin) <= self::ALIGN_TOLERANCE
                    && $row->yMin > $last->yMin && $gap >= -self::LINE_OVERLAP && $gap <= self::LINE_GAP
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
     * Je slovo částí některé ceny (koruny, haléře, přeškrtnutá cena)?
     *
     * @param  list<PdfBox>  $prices
     */
    private function isPricePart(PdfWord $word, array $prices): bool
    {
        foreach ($prices as $price) {
            if ($word->xMin >= $price->xMin && $word->xMax <= $price->xMax && $word->yMin >= $price->yMin && $word->yMax <= $price->yMax) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ceny za jednotku a balení z popisu dlaždice.
     *
     * @return array{unitPrices: list<array{unit: string, quantity: float, from: bool, value: int, appValue: int|null}>, packages: array<string, list<float>>}
     */
    private function tileFacts(PdfTile $tile): array
    {
        $text = $tile->detailText();
        preg_match_all(self::UNIT_PRICE_PATTERN, $text, $matches, PREG_SET_ORDER);

        $unitPrices = [];
        foreach ($matches as $m) {
            $unit = $this->unit($m[2]);
            if ($unit !== null) {
                $unitPrices[] = [
                    'unit' => $unit[0],
                    'quantity' => $this->number($m[1]) * $unit[1],
                    'from' => mb_strtolower($m[3]) === 'od',
                    'value' => $this->priceParser->parse($m[4]),
                    'appValue' => ($m[5] ?? '') === '' ? null : $this->priceParser->parse($m[5]),
                ];
            }
        }

        $rest = (string) preg_replace([self::UNIT_PRICE_PATTERN, self::AMOUNT_PATTERN], ' ', $text);
        preg_match_all(self::PACKAGE_PATTERN, $rest, $matches, PREG_SET_ORDER);

        $packages = [];
        foreach ($matches as $m) {
            $unit = $this->unit($m[4]);
            if ($unit === null) {
                continue;
            }
            $multiplier = $m[1] === '' ? 1 : (int) $m[1];
            foreach (array_filter([$m[2], $m[3]]) as $amount) {
                $packages[$unit[0]][] = $this->number($amount) * $multiplier * $unit[1];
            }
        }

        return ['unitPrices' => $unitPrices, 'packages' => $packages];
    }

    /**
     * Jednotka pro porovnání a násobek; null u slova, které jednotkou není.
     *
     * @return array{string, int}|null
     */
    private function unit(string $word): ?array
    {
        $word = mb_strtolower($word);
        if (isset(self::UNITS[$word])) {
            return self::UNITS[$word];
        }

        return mb_strlen($word) >= self::UNIT_STEM_LENGTH && $word !== mb_strtolower(self::CURRENCY)
            ? [mb_substr($word, 0, self::UNIT_STEM_LENGTH), 1]
            : null;
    }

    /**
     * Číslo s desetinnou čárkou z textu letáku.
     */
    private function number(string $text): float
    {
        return (float) str_replace(',', '.', $text);
    }

    /**
     * Ověří cenu proti dlaždici: každá cena za jednotku, ke které je v popisu balení,
     * musí sedět. U dlaždice s aplikací je velká cena ta s aplikací a cena bez aplikace
     * se hledá pod ní, jinak je to přeškrtnutá cena u ní.
     *
     * @param  array{unitPrices: list<array{unit: string, quantity: float, from: bool, value: int, appValue: int|null}>, packages: array<string, list<float>>}  $facts
     * @param  list<PdfBox>  $prices
     * @param  PdfBox|null  $crossed  Přeškrtnutá cena u ceny
     * @return array{price: int, loyalty: int|null}|null
     */
    private function verify(PdfBox $price, array $facts, array $prices, ?PdfBox $crossed): ?array
    {
        $comparable = array_values(array_filter($facts['unitPrices'], fn (array $unitPrice): bool => ($facts['packages'][$unitPrice['unit']] ?? []) !== []));
        if ($comparable === []) {
            return null;
        }

        $withApp = array_filter($comparable, fn (array $unitPrice): bool => $unitPrice['appValue'] !== null);
        $matchesAll = function (int $amount, bool $app) use ($comparable, $facts): bool {
            foreach ($comparable as $unitPrice) {
                $stated = $app ? $unitPrice['appValue'] : $unitPrice['value'];
                if ($stated !== null && ! UnitPriceCheck::matchesAny($amount, $stated, $unitPrice['quantity'], $unitPrice['from'], $facts['packages'][$unitPrice['unit']])) {
                    return false;
                }
            }

            return true;
        };

        if ($withApp === []) {
            return $matchesAll($price->value, false) ? ['price' => $price->value, 'loyalty' => null] : null;
        }

        if (! $matchesAll($price->value, true)) {
            return null;
        }

        // Bez menší ceny „BEZ APLIKACE“ je cenou bez aplikace přeškrtnutá cena (Mlynářské pečivo)
        foreach ([...$this->regularPrices($price, $prices), ...($crossed === null ? [] : [$crossed])] as $regular) {
            if ($regular->value > $price->value && $matchesAll($regular->value, false)) {
                return ['price' => $regular->value, 'loyalty' => $price->value];
            }
        }

        return null;
    }

    /**
     * Kandidáti na cenu bez aplikace: menší cena těsně pod cenou s aplikací, nejbližší první.
     *
     * @param  list<PdfBox>  $prices
     * @return list<PdfBox>
     */
    private function regularPrices(PdfBox $price, array $prices): array
    {
        $candidates = array_values(array_filter($prices, fn (PdfBox $candidate): bool => $candidate->height() <= $price->height() * self::REGULAR_PRICE_MAX_RATIO
            && $candidate->yMin >= $price->yMax - self::REGULAR_PRICE_OVERLAP
            && $candidate->yMin <= $price->yMax + self::REGULAR_PRICE_BELOW
            && $candidate->xMin < $price->xMax + self::REGULAR_PRICE_OVERLAP
            && $candidate->xMax > $price->xMin - self::REGULAR_PRICE_OVERLAP));
        usort($candidates, fn (PdfBox $a, PdfBox $b): int => $price->distanceTo($a) <=> $price->distanceTo($b));

        return $candidates;
    }

    /**
     * Platnosti stránky, ze kterých se skládá platnost dlaždice.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $leaflet
     * @return array{page: array{CarbonImmutable, CarbonImmutable}, section: array{float, array{CarbonImmutable, CarbonImmutable}}|null, footnotes: array<int, array{CarbonImmutable, CarbonImmutable|null}>}
     */
    private function validityContext(PdfPage $page, array $leaflet): array
    {
        $text = implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $page->lines));
        $year = (int) $leaflet[1]->format('Y');

        $pageValidity = $leaflet;
        if (preg_match(self::PAGE_VALIDITY_PATTERN, $text, $m) === 1) {
            $to = $this->date((int) $m[4], (int) $m[5], (int) $m[6]);
            $from = $this->date((int) $m[1], (int) $m[2], $m[3] === '' ? (int) $m[6] : (int) $m[3]);
            if ($from !== null && $to !== null) {
                $pageValidity = [$from, $to];
            }
        }

        $footnotes = [];
        preg_match_all(self::FOOTNOTE_VALIDITY_PATTERN, $text, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $m) {
            $toYear = $m[7] === null ? null : (int) $m[7];
            $from = $this->date((int) $m[2], (int) $m[3], $m[4] === null ? ($toYear ?? $year) : (int) $m[4]);
            if ($from !== null) {
                $footnotes[strlen((string) $m[1])] = [$from, $toYear === null ? null : $this->date((int) $m[5], (int) $m[6], $toYear)];
            }
        }

        return ['page' => $pageValidity, 'section' => $this->sectionValidity($page), 'footnotes' => $footnotes];
    }

    /**
     * Oddíl „PLATÍ POUZE PÁ–NE“ se dvěma daty velkým písmem — platí pro dlaždice pod ním.
     *
     * @return array{float, array{CarbonImmutable, CarbonImmutable}}|null Spodní okraj dat a platnost
     */
    private function sectionValidity(PdfPage $page): ?array
    {
        $dates = [];
        foreach ($page->lines as $line) {
            if ($line->height() >= self::SECTION_DATE_MIN_HEIGHT && preg_match(self::SECTION_DATE_PATTERN, str_replace(' ', '', $line->text()), $m) === 1) {
                $date = $this->date((int) $m[1], (int) $m[2], (int) $m[3]);
                if ($date !== null) {
                    $dates[] = [$line->yMax, $date];
                }
            }
        }
        if (count($dates) !== self::SECTION_DATE_COUNT) {
            return null;
        }

        usort($dates, fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return [max($dates[0][0], $dates[1][0]), [$dates[0][1], $dates[1][1]]];
    }

    /**
     * Platnost dlaždice: text dlaždice, poznámka s hvězdičkou, oddíl, stránka, leták.
     *
     * @param  array{page: array{CarbonImmutable, CarbonImmutable}, section: array{float, array{CarbonImmutable, CarbonImmutable}}|null, footnotes: array<int, array{CarbonImmutable, CarbonImmutable|null}>}  $context
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function tileValidity(PdfTile $tile, int $stars, array $context): array
    {
        [$from, $to] = $context['page'];
        if ($context['section'] !== null && $tile->box->yMin > $context['section'][0]) {
            [$from, $to] = $context['section'][1];
        }
        if (isset($context['footnotes'][$stars])) {
            $from = $context['footnotes'][$stars][0];
            $to = $context['footnotes'][$stars][1] ?? $to;
        }

        $text = $tile->detailText();
        if (preg_match(self::TILE_VALID_TO_PATTERN, $text, $m) === 1) {
            $to = $this->date((int) $m[1], (int) $m[2], (int) $m[3]) ?? $to;
        }
        if (preg_match(self::TILE_VALID_FROM_PATTERN, $text, $m) === 1) {
            $from = $this->date((int) $m[1], (int) $m[2], ($m[3] ?? '') === '' ? (int) $to->format('Y') : (int) $m[3]) ?? $from;
        }

        return $from->greaterThan($to) ? $context['page'] : [$from, $to];
    }

    /**
     * Místní datum, nebo null, když den v roce neexistuje.
     */
    private function date(int $day, int $month, int $year): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? $this->calendar->date(sprintf('%04d-%02d-%02d', $year, $month, $day)) : null;
    }

    /**
     * Nabídka z ověřené dlaždice; null, když přeškrtnutá cena nebo sleva v procentech nesedí
     * nebo jde o akci na více kusů.
     *
     * @param  array{price: int, loyalty: int|null}  $verified
     * @param  array{page: array{CarbonImmutable, CarbonImmutable}, section: array{float, array{CarbonImmutable, CarbonImmutable}}|null, footnotes: array<int, array{CarbonImmutable, CarbonImmutable|null}>}  $context
     */
    private function offer(PdfTile $tile, array $verified, ?PdfBox $crossed, ?PdfBox $percent, array $context, int $page, string $pageUrl): ?OfferData
    {
        $price = $verified['price'];
        $loyalty = $verified['loyalty'];
        // Sleva na cenovce se počítá z velké ceny — s aplikací z ceny s aplikací
        $headline = $loyalty ?? $price;

        // Akce na více kusů: velká cena platí jen při koupi 2 a více kusů — neověřitelné, vynechat
        if (preg_match(self::MULTIBUY_PATTERN, $tile->detailText()) === 1) {
            return null;
        }
        if ($crossed !== null && ($crossed->value <= $headline || ($percent !== null && ! DiscountCheck::truncatedOrRounded($crossed->value, $headline, $percent->value)))) {
            return null;
        }
        $original = $crossed !== null && $crossed->value > $price ? $crossed->value : null;

        // Řádek názvu končící lomítkem pokračuje bez mezery („Šampon/“ „Kondicionér“)
        $nameText = Text::clean(str_replace('/ ', '/', implode(' ', $tile->nameLines))) ?? '';
        $stars = strlen($nameText) - strlen(rtrim($nameText, self::NAME_STAR));
        $name = Text::clean(rtrim($nameText, self::NAME_STAR)) ?? '';
        $details = $this->details($tile);
        $units = array_map(fn (array $unitPrice): string => $unitPrice['unit'], $this->tileFacts($tile)['unitPrices']);
        $packageText = $this->packageText($details, $units);
        $description = Text::clean(implode(' • ', $details));
        [$validFrom, $validTo] = $this->tileValidity($tile, $stars, $context);

        $offerType = match (true) {
            $original !== null => OfferType::Discount,
            $loyalty !== null => OfferType::LoyaltyOnly,
            default => OfferType::PromoPrice,
        };

        return new OfferData(
            externalId: self::EXTERNAL_ID_PREFIX.substr(sha1(mb_strtolower($name).'|'.$packageText.'|'.$price.'|'.$loyalty), 0, self::EXTERNAL_ID_LENGTH),
            name: $name,
            offerType: $offerType,
            validFrom: $validFrom,
            validTo: $validTo,
            raw: [
                'page' => $page,
                'name' => $tile->nameLines,
                'details' => $tile->detailLines,
                'price' => $price,
                'loyaltyPrice' => $loyalty,
                'crossed' => $crossed?->value,
                'percent' => $percent?->value,
            ],
            price: $price,
            originalPrice: $original,
            loyaltyPrice: $loyalty,
            loyaltyProgram: $loyalty === null ? null : LoyaltyProgram::MujAlbert,
            // Procento na cenovce s aplikací patří k ceně s aplikací — u běžné ceny by klamalo
            discountPercent: $original !== null && $loyalty === null ? $percent?->value : null,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceUrl: $pageUrl,
        );
    }

    /**
     * Odrážky popisu bez nejnižší ceny za 30 dní.
     *
     * @return list<string>
     */
    private function details(PdfTile $tile): array
    {
        $details = [];
        foreach (preg_split(self::BULLET_PATTERN, $tile->detailText()) ?: [] as $item) {
            $item = Text::clean($item);
            if ($item === null || preg_match(self::LOWEST_PRICE_PATTERN, $item) === 1) {
                continue;
            }
            if (preg_match(self::TRAILING_AMOUNT_PATTERN, $item, $m) === 1 && preg_match(self::AMOUNT_CONTEXT_PATTERN, $m[1]) !== 1) {
                $item = $m[1];
            }
            $details[] = $item;
        }

        return $details;
    }

    /**
     * Odrážka s balením — první, ve které je množství v jednotce, ve které je uvedená cena
     * za jednotku („2 role“ u „1 role = 32,45 Kč“, ne „2vrstvé“), a není to cena za jednotku.
     *
     * @param  list<string>  $details
     * @param  list<string>  $units  Jednotky cen za jednotku dlaždice
     */
    private function packageText(array $details, array $units): ?string
    {
        foreach ($details as $item) {
            $rest = (string) preg_replace([self::UNIT_PRICE_PATTERN, self::AMOUNT_PATTERN], ' ', $item);
            preg_match_all(self::PACKAGE_PATTERN, $rest, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                if (in_array($this->unit($m[4])[0] ?? null, $units, true)) {
                    return $item;
                }
            }
        }

        return null;
    }
}
