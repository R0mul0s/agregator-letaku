<?php

/**
 * Obchod zatím nemá zdroj daného druhu (config/letaky.php, sources).
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
     * Výjimka pro obchod a druh zdroje (offers, stores).
     */
    public static function for(Chain $chain, string $kind): self
    {
        return new self("{$chain->label()}: zdroj „{$kind}“ zatím není.");
    }
}
