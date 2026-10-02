<?php

/**
 * Zdroji chybí nastavení (např. API klíč Tesco v .env).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class SourceNotConfigured extends RuntimeException
{
    /**
     * Výjimka s názvem chybějící proměnné prostředí.
     */
    public static function missing(Chain $chain, string $variable): self
    {
        return new self("{$chain->label()}: chybí nastavení {$variable} v .env.");
    }
}
