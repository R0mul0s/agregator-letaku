<?php

/**
 * Jednotky a balení v popisu dlaždice PDF letáku (R86–R89, R113) — ověření ceny za jednotku
 * porovnává balení s cenou za jednotku ve stejné jednotce. Bylo zkopírované v parserech Albertu,
 * Globusu a Billy a kopie se rozešly (rozsah „750–1000 ml“ jen u Albertu a Globusu, „330/285 g“
 * a metry jen u Billy).
 *
 * Jen pro ověření dlaždice — balení ukládané k akci čte PackageParser.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final class TileUnits
{
    /**
     * Balení: „150 ml“, „3× 50 g“, „6 x 0,5 l“, rozsah „750–1000 ml“ i víc údajů „330/285 g“
     * (sedět může kterýkoli), „126 dávek“. Skupiny: násobek, množství, slovo jednotky.
     */
    private const PACKAGE_PATTERN = '/(?:(\d+)\s*[×x]\s*)?(\d+(?:,\d+)?(?:\s*(?:[–-]|\/)\s*\d+(?:,\d+)?)*)\s*(\p{L}+)/u';

    /** Oddělovač více množství v jednom balení („750–1000“, „330/285“). */
    private const AMOUNT_SEPARATOR_PATTERN = '/\s*(?:[–-]|\/)\s*/u';

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
        'm' => ['m', 1],
    ];

    private const UNIT_STEM_LENGTH = 3;

    /**
     * Jednotka pro porovnání a násobek; null u slova, které jednotkou není.
     *
     * @return array{string, int}|null
     */
    public static function unit(string $word): ?array
    {
        $word = mb_strtolower($word);
        if (isset(self::UNITS[$word])) {
            return self::UNITS[$word];
        }

        return mb_strlen($word) >= self::UNIT_STEM_LENGTH ? [mb_substr($word, 0, self::UNIT_STEM_LENGTH), 1] : null;
    }

    /**
     * Číslo s desetinnou čárkou z textu letáku.
     */
    public static function number(string $text): float
    {
        return (float) str_replace(',', '.', $text);
    }

    /**
     * Balení v textu v jednotkách pro porovnání: jednotka => množství („3× 35 g“ = 105 g,
     * „330/285 g“ i „750–1000 ml“ = obě množství). Text nemá obsahovat ceny za jednotku.
     *
     * @return array<string, list<float>>
     */
    public static function packages(string $text): array
    {
        preg_match_all(self::PACKAGE_PATTERN, $text, $matches, PREG_SET_ORDER);

        $packages = [];
        foreach ($matches as $m) {
            $unit = self::unit($m[3]);
            if ($unit === null) {
                continue;
            }
            $multiplier = $m[1] === '' ? 1 : (int) $m[1];
            foreach (preg_split(self::AMOUNT_SEPARATOR_PATTERN, $m[2]) ?: [] as $amount) {
                $packages[$unit[0]][] = self::number($amount) * $multiplier * $unit[1];
            }
        }

        return $packages;
    }

    /**
     * Je v textu balení v některé z jednotek? („2 role“ u ceny za „1 role“, ne „2vrstvé“.)
     *
     * @param  list<string>  $units  Jednotky pro porovnání
     */
    public static function hasPackageIn(string $text, array $units): bool
    {
        return array_intersect(array_keys(self::packages($text)), $units) !== [];
    }
}
