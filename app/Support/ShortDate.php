<?php

/**
 * Místní datum platnosti jako krátký text „8. 10.“ pro e-maily a upozornění v telefonu
 * (R42, R76). Bez dne v týdnu — nezávisí na datech ICU, vývojový kontejner má jen angličtinu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final class ShortDate
{
    /** Den a měsíc s nezlomitelnou mezerou — datum se nerozdělí na dva řádky. */
    private const FORMAT = 'j.'.PriceFormatter::NO_BREAK_SPACE.'n.';

    /**
     * „8. 10.“
     */
    public function format(CarbonImmutable $date): string
    {
        return $date->format(self::FORMAT);
    }
}
