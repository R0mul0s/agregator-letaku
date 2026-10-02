<?php

/**
 * Cena za jednotku (Kč/kg, Kč/l, Kč/ks) z ceny balení a množství — pro porovnání různých balení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\PackageUnit;

final class UnitPrice
{
    /**
     * Cena za jednotku v haléřích; null, když chybí cena nebo množství.
     */
    public static function of(?int $price, ?float $quantity, ?PackageUnit $unit): ?int
    {
        if ($price === null || $quantity === null || $quantity <= 0 || $unit === null) {
            return null;
        }

        return (int) round($price * $unit->unitPriceBase() / $quantity);
    }
}
