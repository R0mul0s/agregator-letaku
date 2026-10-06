<?php

/**
 * Stránka textové vrstvy PDF — rozměry a řádky v pořadí, jak je pdftotext přečetl.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final readonly class PdfPage
{
    /**
     * @param  int  $number  Číslo stránky (od 1)
     * @param  list<PdfLine>  $lines
     */
    public function __construct(
        public int $number,
        public float $width,
        public float $height,
        public array $lines,
    ) {}

    /**
     * Všechna slova stránky.
     *
     * @return list<PdfWord>
     */
    public function words(): array
    {
        return array_merge(...array_map(fn (PdfLine $line): array => $line->words, $this->lines));
    }
}
