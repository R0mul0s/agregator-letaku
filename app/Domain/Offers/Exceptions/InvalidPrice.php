<?php

/**
 * Text ceny od obchodu nejde převést na haléře — obchod nejspíš změnil formát.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Exceptions;

use RuntimeException;

final class InvalidPrice extends RuntimeException
{
    /**
     * Výjimka s textem, který nešel převést.
     */
    public static function fromText(string $text): self
    {
        return new self("Neplatná cena „{$text}“.");
    }
}
