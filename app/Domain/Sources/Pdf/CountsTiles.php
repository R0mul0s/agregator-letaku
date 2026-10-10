<?php

/**
 * Počítání nalezených a ověřených cen v parseru letáku (R129) — parser na začátku letáku
 * počítání vynuluje, po každé straně přičte a zdroj si po zpracování vezme `lastStats()`
 * do dávky (SourceBatch::$tiles). Výběr akcí se tím nemění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

use App\Domain\Offers\Data\TileStats;

trait CountsTiles
{
    private int $tileCandidates = 0;

    private int $tilesVerified = 0;

    /**
     * Nalezené a ověřené ceny posledního zpracovaného letáku (u Penny strany).
     */
    public function lastStats(): TileStats
    {
        return new TileStats($this->tileCandidates, $this->tilesVerified);
    }

    /**
     * Vynuluje počítání před novým letákem.
     */
    private function resetTileCount(): void
    {
        $this->tileCandidates = 0;
        $this->tilesVerified = 0;
    }

    /**
     * Přičte ceny strany, ke kterým parser hledal dlaždici, a přijaté akce.
     */
    private function countTiles(int $candidates, int $verified): void
    {
        $this->tileCandidates += $candidates;
        $this->tilesVerified += $verified;
    }
}
