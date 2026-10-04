<?php

/**
 * Stažení obchodu už běží (R57) — druhé souběžné by si s prvním navzájem označilo akce
 * jako stažené obchodem (R16). Cron URL zavolaná dvakrát nebo ruční spuštění během cronu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class ImportAlreadyRunning extends RuntimeException
{
    /**
     * Výjimka pro obchod.
     */
    public static function for(Chain $chain): self
    {
        return new self("{$chain->label()}: stažení už běží, souběžné se nespustí.");
    }
}
