<?php

/**
 * Hlídání stahování akcí pro monitoring (UptimeRobot): 200, když každý obchod se zdrojem
 * má úspěšné stažení novější než limit, jinak 503. Veřejná URL bez tokenu — neprozradí
 * nic než stav stahování.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Sources\SourceRegistry;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class HealthImportsController extends Controller
{
    /**
     * Na každém řádku jeden obchod: „Tesco — OK, naposledy …“ nebo „Tesco — VÝPADEK: …“.
     */
    public function __invoke(SourceRegistry $sources): Response
    {
        $limit = CarbonImmutable::now()->subHours(config()->integer('letaky.health.max_import_age_hours'));

        $lines = [];
        $healthy = true;
        foreach ($sources->chainsWithOffers() as $chain) {
            $finishedAt = ScrapeRun::lastFinishedAt(chain: $chain);
            $ok = $finishedAt !== null && $finishedAt->greaterThan($limit);
            $healthy = $healthy && $ok;

            $lines[] = __($ok ? 'app.health.ok' : 'app.health.outage', [
                'chain' => $chain->label(),
                'at' => $finishedAt?->setTimezone(config()->string('letaky.display_timezone'))->format('j. n. H:i') ?? __('app.health.never'),
            ]);
        }

        return response(implode("\n", $lines)."\n", $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
