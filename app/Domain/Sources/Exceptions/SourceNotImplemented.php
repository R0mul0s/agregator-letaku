<?php

/**
 * Obchod zatím nemá zdroj nabídek (config/letaky.php, sources).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class SourceNotImplemented extends RuntimeException
{
    /**
     * Výjimka pro obchod.
     */
    public static function for(Chain $chain): self
    {
        return new self("{$chain->label()}: zdroj nabídek zatím není.");
    }
}
