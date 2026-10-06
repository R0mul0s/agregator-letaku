<?php

/**
 * Text s polohou z PDF letáku nejde získat — pdftotext chybí, skončil chybou nebo vrátil
 * nečitelný výstup (R86).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use RuntimeException;

final class PdfTextFailed extends RuntimeException
{
    /**
     * Výjimka s důvodem (chybový výstup pdftotext, chyba XML…).
     */
    public static function because(string $reason): self
    {
        return new self("Text PDF letáku: {$reason}");
    }
}
