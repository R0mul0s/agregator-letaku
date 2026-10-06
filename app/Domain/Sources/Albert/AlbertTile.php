<?php

/**
 * Textová část dlaždice letáku Albertu — řádky názvu (větší písmo) a pod nimi řádky
 * popisu s odrážkami (balení, cena za jednotku, platnost, nejnižší cena za 30 dní).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

final readonly class AlbertTile
{
    /**
     * @param  list<string>  $nameLines
     * @param  list<string>  $detailLines
     */
    public function __construct(
        public array $nameLines,
        public array $detailLines,
        public AlbertBox $box,
    ) {}

    /**
     * Celý text popisu jako jeden řádek (cena za jednotku bývá rozdělená na dva řádky).
     */
    public function detailText(): string
    {
        return implode(' ', $this->detailLines);
    }
}
