<?php

/**
 * Slovo z textové vrstvy PDF s polohou na stránce (body PDF, počátek vlevo nahoře).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final readonly class PdfWord
{
    public function __construct(
        public string $text,
        public float $xMin,
        public float $yMin,
        public float $xMax,
        public float $yMax,
    ) {}

    /**
     * Výška slova — odpovídá velikosti písma (cena je řádově větší než popis).
     */
    public function height(): float
    {
        return $this->yMax - $this->yMin;
    }
}
