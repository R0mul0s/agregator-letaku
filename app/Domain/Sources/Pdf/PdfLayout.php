<?php

/**
 * Geometrie stránky PDF letáku společná pro parsery (R86–R89, R106): skládání slov do řádků
 * a přiřazení nejbližších prvků (přeškrtnutá cena, sleva) k cenám. Tolerance jsou podle
 * rozvržení letáku, předává je parser — tady je jen postup, který byl v parserech zkopírovaný.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final class PdfLayout
{
    /**
     * Ke každé ceně nejbližší prvek (přeškrtnutou cenu, slevu), který k ní podle polohy
     * může patřit; každý prvek nejvýš k jedné ceně. Páry se berou od nejbližšího.
     *
     * @template TPrice
     * @template TCandidate
     *
     * @param  array<int, TPrice>  $prices
     * @param  array<int, TCandidate>  $candidates
     * @param  callable(TPrice, TCandidate): ?float  $distance  Null = k ceně nepatří
     * @return array<int, TCandidate> Index ceny => prvek
     */
    public static function nearest(array $prices, array $candidates, callable $distance): array
    {
        $result = [];
        $pairs = self::greedyPairs($prices, $candidates, function (mixed $price, mixed $candidate) use ($distance): ?array {
            $value = $distance($price, $candidate);

            return $value === null ? null : [$value, null];
        });
        foreach ($pairs as [$priceIndex, $candidateIndex]) {
            $result[$priceIndex] = $candidates[$candidateIndex];
        }

        return $result;
    }

    /**
     * Dvojice prvků dvou seznamů (ceny a dlaždice, ceny a přeškrtnuté ceny): ze všech
     * přípustných vyhrávají nejbližší a každý prvek obou seznamů je nejvýš v jedné dvojici.
     * Shodné vzdálenosti rozhoduje pořadí prvků (první seznam vně, druhý uvnitř).
     *
     * @template TFirst
     * @template TSecond
     * @template TPayload
     *
     * @param  array<int, TFirst>  $first
     * @param  array<int, TSecond>  $second
     * @param  callable(TFirst, TSecond, int, int): (array{float, TPayload}|null)  $score  Vzdálenost a data dvojice, null = k sobě nepatří
     * @param  (callable(TPayload): list<int>)|null  $alsoUses  Další prvky druhého seznamu, které dvojice spotřebuje (běžná cena pod cenou s kartou)
     * @return list<array{int, int, TPayload}> Index v prvním, index ve druhém a data, od nejbližší dvojice
     */
    public static function greedyPairs(array $first, array $second, callable $score, ?callable $alsoUses = null): array
    {
        $candidates = [];
        foreach ($first as $firstIndex => $a) {
            foreach ($second as $secondIndex => $b) {
                $scored = $score($a, $b, $firstIndex, $secondIndex);
                if ($scored !== null) {
                    $candidates[] = [$scored[0], $firstIndex, $secondIndex, $scored[1]];
                }
            }
        }
        usort($candidates, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $usedFirst = [];
        $usedSecond = [];
        $pairs = [];
        foreach ($candidates as [, $firstIndex, $secondIndex, $payload]) {
            $consumes = [$secondIndex, ...($alsoUses === null ? [] : $alsoUses($payload))];
            if (isset($usedFirst[$firstIndex]) || array_any($consumes, fn (int $index): bool => isset($usedSecond[$index]))) {
                continue;
            }
            $usedFirst[$firstIndex] = true;
            foreach ($consumes as $index) {
                $usedSecond[$index] = true;
            }
            $pairs[] = [$firstIndex, $secondIndex, $payload];
        }

        return $pairs;
    }

    /**
     * Jednoznačné dvojice cena–dlaždice: dlaždice je ceně nejbližší ze všech dlaždic, cena
     * dlaždici ze všech cen a na obou stranách je druhý nejbližší soused výrazně dál. Pro
     * dlaždice, jejichž cenu nejde ověřit cenou za jednotku (balení 1 kg / 1 ks, R107) — cena
     * mezi dvěma dlaždicemi zůstane bez dvojice.
     *
     * @template TPrice
     * @template TTile
     *
     * @param  array<int, TPrice>  $prices
     * @param  array<int, TTile>  $tiles
     * @param  callable(TPrice, TTile): ?float  $distance  Null = dlaždice k ceně podle polohy nepatří
     * @param  float  $ambiguityRatio  Nejbližší soused musí být blíž než tento podíl vzdálenosti druhého
     * @return array<int, int> Index ceny => index dlaždice
     */
    public static function mutualNearest(array $prices, array $tiles, callable $distance, float $ambiguityRatio): array
    {
        $byPrice = [];
        $byTile = [];
        foreach ($prices as $priceIndex => $price) {
            foreach ($tiles as $tileIndex => $tile) {
                $value = $distance($price, $tile);
                if ($value !== null) {
                    $byPrice[$priceIndex][$tileIndex] = $value;
                    $byTile[$tileIndex][$priceIndex] = $value;
                }
            }
        }

        $pairs = [];
        foreach ($byPrice as $priceIndex => $distances) {
            $tileIndex = self::clearlyNearest($distances, $ambiguityRatio);
            if ($tileIndex !== null && self::clearlyNearest($byTile[$tileIndex], $ambiguityRatio) === $priceIndex) {
                $pairs[$priceIndex] = $tileIndex;
            }
        }

        return $pairs;
    }

    /**
     * Klíč nejbližšího souseda, když je výrazně blíž než druhý; jinak null.
     *
     * @param  non-empty-array<int, float>  $distances
     */
    private static function clearlyNearest(array $distances, float $ambiguityRatio): ?int
    {
        asort($distances);
        $keys = array_keys($distances);
        $second = $keys[1] ?? null;

        return $second === null || $distances[$keys[0]] < $distances[$second] * $ambiguityRatio ? $keys[0] : null;
    }

    /**
     * Slova složená do řádků: stejný horní okraj a malá mezera mezi slovy. Řádky pdftotext
     * se nepoužijí — slučují slova sousedních dlaždic („- 32 % Vepřová“).
     *
     * @param  list<PdfWord>  $words
     * @param  float  $rowTolerance  Odchylka horního okraje slov v jednom řádku (i překryv slov)
     * @param  float  $wordGap  Největší mezera mezi slovy řádku
     * @return list<PdfBox>
     */
    public static function rows(array $words, float $rowTolerance, float $wordGap): array
    {
        usort($words, fn (PdfWord $a, PdfWord $b): int => $a->xMin <=> $b->xMin);

        /** @var list<PdfBox> $rows */
        $rows = [];
        foreach ($words as $word) {
            $box = new PdfBox($word->text, 0, $word->xMin, $word->yMin, $word->xMax, $word->yMax);
            foreach ($rows as $index => $row) {
                $gap = $word->xMin - $row->xMax;
                if (abs($row->yMin - $word->yMin) <= $rowTolerance && $gap >= -$rowTolerance && $gap <= $wordGap) {
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
     * Vzdálenost prvku nad cenou, který se vejde do jejího sloupce (přeškrtnutá cena); null,
     * když tam neleží. Spodní hrana prvku smí zasahovat do ceny o podíl její výšky.
     */
    public static function aboveWithin(PdfBox $price, PdfBox $box, float $above, float $overlapRatio, float $side): ?float
    {
        $fits = $box->yMax >= $price->yMin - $above
            && $box->yMax <= $price->yMin + $price->height() * $overlapRatio
            && $box->xMin >= $price->xMin - $side
            && $box->xMax <= $price->xMax + $side;

        return $fits ? $price->distanceTo($box) : null;
    }

    /**
     * Vzdálenost prvku nad cenou nebo vedle ní nahoře, který se se sloupcem ceny překrývá
     * (sleva v procentech); null, když tam neleží.
     */
    public static function aboveOverlapping(PdfBox $price, PdfBox $box, float $above, float $overlapRatio, float $side): ?float
    {
        $fits = $box->yMax >= $price->yMin - $above
            && $box->yMax <= $price->yMin + $price->height() * $overlapRatio
            && $box->xMin <= $price->xMax + $side
            && $box->xMax >= $price->xMin - $side;

        return $fits ? $price->distanceTo($box) : null;
    }

    /**
     * Slova s výškou písma v rozmezí (název, popis, cena za jednotku).
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfWord>
     */
    public static function byHeight(array $words, float $min, float $max): array
    {
        return array_values(array_filter($words, fn (PdfWord $word): bool => $word->height() >= $min && $word->height() <= $max));
    }

    /**
     * Leží slovo uvnitř některého z prvků (cena, štítek) — s tolerancí na každé straně?
     *
     * @param  list<PdfBox>  $boxes
     */
    public static function isInside(PdfWord $word, array $boxes, float $tolerance = 0.0): bool
    {
        foreach ($boxes as $box) {
            if ($word->xMin >= $box->xMin - $tolerance && $word->xMax <= $box->xMax + $tolerance
                && $word->yMin >= $box->yMin - $tolerance && $word->yMax <= $box->yMax + $tolerance) {
                return true;
            }
        }

        return false;
    }

    /**
     * Řádky pod `$last` zarovnané vlevo s prvním řádkem dlaždice, jeden pod druhým (řádky
     * názvu a popisu dlaždice). Vybrané řádky označí v `$used`, ať nepatří dvěma dlaždicím.
     *
     * @param  list<PdfBox>  $rows
     * @param  array<int, true>  $used  Řádky, které už patří jiné dlaždici
     * @param  float  $alignTolerance  Odchylka levého okraje od prvního řádku
     * @param  float  $lineOverlap  Jak moc se řádek smí překrývat s předchozím
     * @param  float  $lineGap  Největší mezera mezi řádky
     * @return list<PdfBox>
     */
    public static function columnBelow(array $rows, PdfBox $first, PdfBox $last, array &$used, float $alignTolerance, float $lineOverlap, float $lineGap): array
    {
        $column = [];
        while (true) {
            $next = null;
            foreach ($rows as $index => $row) {
                $gap = $row->yMin - $last->yMax;
                if (! isset($used[$index]) && abs($row->xMin - $first->xMin) <= $alignTolerance
                    && $row->yMin > $last->yMin && $gap >= -$lineOverlap && $gap <= $lineGap
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
     * Slevy v procentech: číslo (případně „-“ před ním) a hned za ním znak procenta.
     *
     * @param  list<PdfWord>  $words
     * @param  string  $numberPattern  Číslo slevy v první skupině („/^[-–]?(\d{1,2})$/u“)
     * @param  float  $rowTolerance  Překryv čísla a znaku
     * @param  float  $maxGap  Největší mezera mezi číslem a znakem
     * @param  array{float, float}|null  $height  Rozmezí výšky čísla (cenovka, ne popis); null = libovolná
     * @return list<PdfBox>
     */
    public static function percents(array $words, string $sign, string $numberPattern, float $rowTolerance, float $maxGap, ?array $height = null): array
    {
        $percents = [];
        foreach ($words as $signWord) {
            if ($signWord->text !== $sign) {
                continue;
            }

            foreach ($words as $number) {
                $gap = $signWord->xMin - $number->xMax;
                if ($gap >= -$rowTolerance && $gap <= $maxGap && $number->yMax > $signWord->yMin && $number->yMin < $signWord->yMax
                    && ($height === null || ($number->height() >= $height[0] && $number->height() <= $height[1]))
                    && preg_match($numberPattern, $number->text, $m) === 1) {
                    $percents[] = new PdfBox($number->text.' %', (int) $m[1], $number->xMin, min($number->yMin, $signWord->yMin), $signWord->xMax, max($number->yMax, $signWord->yMax));

                    break;
                }
            }
        }

        return $percents;
    }
}
