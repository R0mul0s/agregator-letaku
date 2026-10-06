<?php

/**
 * Prvek stránky PDF letáku Albertu s polohou — cena, přeškrtnutá cena, sleva v procentech
 * nebo řádek textu dlaždice (AlbertLeafletParser).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

final readonly class AlbertBox
{
    /**
     * @param  int  $value  Haléře u ceny, procenta u slevy, jinak 0
     */
    public function __construct(
        public string $text,
        public int $value,
        public float $xMin,
        public float $yMin,
        public float $xMax,
        public float $yMax,
    ) {}

    /**
     * Výška prvku — odpovídá velikosti písma.
     */
    public function height(): float
    {
        return $this->yMax - $this->yMin;
    }

    /**
     * Nejkratší vzdálenost dvou obdélníků (0, když se překrývají).
     */
    public function distanceTo(self $other): float
    {
        $dx = max(0.0, $other->xMin - $this->xMax, $this->xMin - $other->xMax);
        $dy = max(0.0, $other->yMin - $this->yMax, $this->yMin - $other->yMax);

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Obdélník, který obsáhne oba prvky; text se spojí mezerou.
     */
    public function merge(self $other): self
    {
        return new self(
            $this->text.' '.$other->text,
            $this->value,
            min($this->xMin, $other->xMin),
            min($this->yMin, $other->yMin),
            max($this->xMax, $other->xMax),
            max($this->yMax, $other->yMax),
        );
    }
}
