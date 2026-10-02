<?php

/**
 * Zdroj obchodu nevrátil žádnou nabídku — obchod akce má vždy, jde tedy o chybu zdroje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class SourceReturnedNoOffers extends RuntimeException
{
    /**
     * Výjimka pro obchod.
     */
    public static function for(Chain $chain): self
    {
        return new self("{$chain->label()}: zdroj nevrátil žádnou nabídku.");
    }
}
