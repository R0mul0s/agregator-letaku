<?php

/**
 * Stav jednoho stažení nabídky obchodu (tabulka scrape_runs).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum ScrapeStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    // Nabídky uložené, chybějící akce se ale neoznačily jako stažené — zdroj vrátil
    // podezřele málo (R54). Pro hlídání stahování (/health/imports) to není úspěch.
    case Partial = 'partial';
    case Failed = 'failed';
}
