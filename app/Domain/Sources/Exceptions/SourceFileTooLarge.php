<?php

/**
 * Stahovaný soubor je větší než `letaky.http.download_max_bytes` (R186) — podvržený
 * „leták“ by jinak zaplnil disk hostingu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-16
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use RuntimeException;

final class SourceFileTooLarge extends RuntimeException
{
    /**
     * Výjimka s adresou, velikostí a limitem v bajtech.
     */
    public static function for(string $url, int $bytes, int $maxBytes): self
    {
        return new self("Soubor ke stažení je příliš velký ({$bytes} B, limit {$maxBytes} B): {$url}");
    }
}
