<?php

/**
 * Stažení by označilo jako stažené obchodem podezřele velkou část neskončených akcí (R54) —
 * zdroj nejspíš vrátil jen část nabídky (rozbitý parser, chybějící leták, výpadek stránky).
 * Nabídky se uloží, ale chybějící akce zůstanou, dokud neskončí nebo se zdroj nespraví.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class SuspiciousWithdrawal extends RuntimeException
{
    /** Převod podílu na procenta ve zprávě. */
    private const PERCENT = 100;

    /**
     * Výjimka pro obchod s počtem chybějících a všech neskončených akcí.
     */
    public static function for(Chain $chain, int $missing, int $current, float $maxShare): self
    {
        $limit = (int) round($maxShare * self::PERCENT);

        return new self("{$chain->label()}: v novém stažení chybí {$missing} z {$current} neskončených akcí (víc než {$limit} %), jako stažené se neoznačily.");
    }
}
