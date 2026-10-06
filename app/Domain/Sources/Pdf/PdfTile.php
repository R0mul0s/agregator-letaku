<?php

/**
 * Textová část dlaždice PDF letáku — řádky názvu (větší písmo) a pod nimi řádky
 * popisu (balení, cena za jednotku, platnost; Albert, Globus).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final readonly class PdfTile
{
    /**
     * @param  list<string>  $nameLines
     * @param  list<string>  $detailLines
     */
    public function __construct(
        public array $nameLines,
        public array $detailLines,
        public PdfBox $box,
    ) {}

    /**
     * Celý text popisu jako jeden řádek (cena za jednotku bývá rozdělená na dva řádky).
     */
    public function detailText(): string
    {
        return implode(' ', $this->detailLines);
    }
}
