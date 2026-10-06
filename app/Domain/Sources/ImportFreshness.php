<?php

/**
 * Jak čerstvé jsou akce — konec posledního úspěšného stažení kteréhokoli obchodu. Patička
 * ukazuje „Akce aktualizované před 2 hodinami“ (R92); výpadek obchodu hlídá /health/imports.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Enums\ScrapeStatus;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;

final class ImportFreshness
{
    /**
     * Konec posledního úspěšného stažení (UTC), null = ještě žádné.
     */
    public function lastSucceededAt(): ?CarbonImmutable
    {
        $finishedAt = ScrapeRun::query()
            ->where('status', ScrapeStatus::Succeeded)
            ->max('finished_at');

        // Databáze ukládá čas v UTC (config/app.php)
        return is_string($finishedAt) ? CarbonImmutable::parse($finishedAt, 'UTC') : null;
    }
}
