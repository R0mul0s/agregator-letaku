<?php

/**
 * Řádek textové vrstvy PDF — slova zleva doprava a blok, do kterého ho pdftotext zařadil
 * (blok = odstavec, např. název produktu nebo popis s balením).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

final readonly class PdfLine
{
    /**
     * @param  list<PdfWord>  $words
     * @param  int  $block  Pořadí bloku na stránce (od 0)
     */
    public function __construct(
        public array $words,
        public int $block,
        public float $xMin,
        public float $yMin,
        public float $xMax,
        public float $yMax,
    ) {}

    /**
     * Text řádku — slova oddělená mezerou.
     */
    public function text(): string
    {
        return implode(' ', array_map(fn (PdfWord $word): string => $word->text, $this->words));
    }

    /**
     * Výška řádku — odpovídá velikosti písma.
     */
    public function height(): float
    {
        return $this->yMax - $this->yMin;
    }
}
