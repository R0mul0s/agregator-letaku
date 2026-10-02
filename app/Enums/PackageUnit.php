<?php

/**
 * Jednotka množství v balení. Množství se ukládá v základní jednotce (g, ml, ks).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum PackageUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Piece = 'ks';

    /**
     * Kolik základních jednotek tvoří jednotku ceny za jednotku (1 kg = 1000 g, 1 l = 1000 ml, 1 ks).
     */
    public function unitPriceBase(): int
    {
        return match ($this) {
            self::Gram, self::Milliliter => 1000,
            self::Piece => 1,
        };
    }

    /**
     * Klíč textu jednotky ceny za jednotku (kg, l, ks) ve skupině ui.unit_price_units.
     */
    public function unitPriceKey(): string
    {
        return match ($this) {
            self::Gram => 'kg',
            self::Milliliter => 'l',
            self::Piece => 'ks',
        };
    }
}
