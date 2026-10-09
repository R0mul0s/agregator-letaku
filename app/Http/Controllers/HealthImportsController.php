<?php

/**
 * Hlídání stahování akcí pro monitoring (UptimeRobot): 200, když každý obchod se zdrojem
 * má úspěšné stažení novější než limit, jinak 503. Veřejná URL bez tokenu — neprozradí
 * nic než stav stahování. Ostatní úlohy cronu hlídá `/health/tasks` (R115).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Sources\SourceRegistry;
use App\Models\ScrapeRun;
use App\Support\HealthReport;
use Illuminate\Http\Response;

class HealthImportsController extends Controller
{
    /**
     * Na každém řádku jeden obchod: „Tesco — OK, naposledy …“ nebo „Tesco — VÝPADEK: …“.
     */
    public function __invoke(SourceRegistry $sources): Response
    {
        $report = new HealthReport;
        foreach ($sources->chainsWithOffers() as $chain) {
            $report->check(
                $chain->label(),
                ScrapeRun::lastFinishedAt(chain: $chain),
                config()->integer('letaky.health.max_import_age_hours'),
                'app.health.outage',
            );
        }

        return $report->response();
    }
}
