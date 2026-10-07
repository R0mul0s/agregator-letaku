<?php

/**
 * Ověření ceny z letáku cenou za jednotku (R26, R85–R89): cena přepočtená na jednotku musí sedět
 * na cenu za jednotku, kterou obchod uvádí u dlaždice. Společné pro všechny parsery letáků —
 * dřív měl každý vlastní kopii se stejnou tolerancí (R106).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final class UnitPriceCheck
{
    /** Tolerance zaokrouhlení: nejméně tolik haléřů… */
    private const TOLERANCE_HALERS = 2;

    /** …nebo tolik z uvedené ceny za jednotku (obchody zaokrouhlují různě). */
    private const TOLERANCE_RATIO = 0.015;

    /**
     * Sedí cena balení na uvedenou cenu za jednotku? Balení bez množství nesedí nikdy
     * (dřív by dělení nulou shodilo celé stažení obchodu).
     *
     * @param  int  $price  Cena balení v haléřích
     * @param  float  $packageQuantity  Množství v balení (500 pro „500 g“)
     * @param  float  $unitQuantity  Množství jednotky ceny za jednotku (1000 pro „1 kg“)
     * @param  int  $stated  Uvedená cena za jednotku v haléřích
     */
    public static function matches(int $price, float $packageQuantity, float $unitQuantity, int $stated): bool
    {
        if ($packageQuantity <= 0) {
            return false;
        }

        $expected = (int) round($price * $unitQuantity / $packageQuantity);

        return abs($expected - $stated) <= max(self::TOLERANCE_HALERS, $stated * self::TOLERANCE_RATIO);
    }

    /**
     * Sedí cena na některé z uvedených balení? U „od“ (víc velikostí balení) se počítá jen
     * s největším balením — „od“ znamená nejnižší cenu za jednotku.
     *
     * @param  list<float>  $packageQuantities
     */
    public static function matchesAny(int $price, int $stated, float $unitQuantity, bool $from, array $packageQuantities): bool
    {
        if ($packageQuantities === []) {
            return false;
        }

        foreach ($from ? [max($packageQuantities)] : $packageQuantities as $quantity) {
            if (self::matches($price, $quantity, $unitQuantity, $stated)) {
                return true;
            }
        }

        return false;
    }
}
