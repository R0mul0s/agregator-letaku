<?php

/**
 * Ověření štítku slevy v procentech proti přeškrtnuté a akční ceně (R85–R89) — dlaždice se
 * nesouladem štítku nepřijme. Obchody procento zaokrouhlují různě; společné pro parsery
 * letáků (R106).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final class DiscountCheck
{
    /** Převod podílu na procenta. */
    private const PERCENT = 100;

    /**
     * Sedí procento, když ho obchod uřezává nebo zaokrouhluje (Albert, Globus: 31,90 → 19,90
     * = 37,6 % → „- 37 %“; Billa zaokrouhluje)? Původní cena bez hodnoty nesedí nikdy.
     */
    public static function truncatedOrRounded(int $original, int $price, int $percent): bool
    {
        if ($original <= 0) {
            return false;
        }

        $exact = ($original - $price) * self::PERCENT / $original;

        return $percent === (int) floor($exact) || $percent === (int) round($exact);
    }

    /**
     * Sedí zaokrouhlené procento s tolerancí (Lidl, Penny)? Původní cena musí být vyšší.
     */
    public static function roundedWithin(int $price, int $original, int $percent, int $tolerance): bool
    {
        return $original > $price
            && abs((int) round(($original - $price) / $original * self::PERCENT) - $percent) <= $tolerance;
    }
}
