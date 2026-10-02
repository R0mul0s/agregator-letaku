<?php

/**
 * Cena v haléřích jako text „39,90 Kč“ (R7) — stejně jako formatPrice() na webu. Pro e-maily
 * a texty akcí ze zdrojů (Billa: „cena 1ks při koupi 3ks: 9,93 Kč“). Nezávisí na datech ICU
 * (vývojový kontejner má jen angličtinu a Intl by vrátil „CZK 39.90“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support;

final class PriceFormatter
{
    /** Haléřů v koruně (ceny jsou v haléřích, R7). */
    private const HALERS_PER_CROWN = 100;

    private const PRICE_DECIMALS = 2;

    private const DECIMAL_SEPARATOR = ',';

    private const CURRENCY = 'Kč';

    /** Nezlomitelná mezera — částka a měna ani tisíce se nerozdělí na dva řádky. */
    public const NO_BREAK_SPACE = "\u{00A0}";

    /**
     * „1 299,90 Kč“ s nezlomitelnými mezerami.
     */
    public function format(int $halers): string
    {
        return number_format($halers / self::HALERS_PER_CROWN, self::PRICE_DECIMALS, self::DECIMAL_SEPARATOR, self::NO_BREAK_SPACE)
            .self::NO_BREAK_SPACE.self::CURRENCY;
    }
}
