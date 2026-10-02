<?php

/**
 * Odpověď obchodu nemá očekávaný tvar — neveřejné rozhraní se změnilo (začni v docs/ZDROJE_DAT.md).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class SourceResponseChanged extends RuntimeException
{
    /**
     * Výjimka s popisem, co v odpovědi chybí.
     */
    public static function because(Chain $chain, string $reason): self
    {
        return new self("{$chain->label()}: neočekávaná odpověď — {$reason}");
    }
}
