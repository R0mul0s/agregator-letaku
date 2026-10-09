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
        $pairs = [];
        foreach ($prices as $priceIndex => $price) {
            foreach ($candidates as $candidateIndex => $candidate) {
                $value = $distance($price, $candidate);
                if ($value !== null) {
                    $pairs[] = [$value, $priceIndex, $candidateIndex];
                }
            }
        }
        usort($pairs, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $result = [];
        $used = [];
        foreach ($pairs as [, $priceIndex, $candidateIndex]) {
            if (! isset($result[$priceIndex]) && ! isset($used[$candidateIndex])) {
                $result[$priceIndex] = $candidates[$candidateIndex];
                $used[$candidateIndex] = true;
            }
        }

        return $result;
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
}
