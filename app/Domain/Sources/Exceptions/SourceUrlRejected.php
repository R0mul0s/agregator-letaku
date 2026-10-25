<?php

/**
 * Adresa ke stažení není veřejná webová adresa přes https (R170). Adresy letáků přicházejí
 * i z dat obchodu (RSS COOP, API letáků) — podvržená adresa by jinak poslala server na
 * vnitřní síť hostingu (SSRF).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Exceptions;

use RuntimeException;

final class SourceUrlRejected extends RuntimeException
{
    /**
     * Výjimka s odmítnutou adresou.
     */
    public static function for(string $url): self
    {
        return new self("Odmítnutá adresa ke stažení: {$url}");
    }
}
