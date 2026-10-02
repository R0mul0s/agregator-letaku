<?php

/**
 * Kus textu z vektorové vrstvy stránky letáku — slovo nebo číslo s polohou.
 *
 * Souřadnice jsou body stránky, y roste směrem nahoru (horní okraj ~840).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Penny;

final readonly class SvgToken
{
    public function __construct(
        public float $x,
        public float $y,
        public float $size,
        public string $fill,
        public string $text,
    ) {}
}
