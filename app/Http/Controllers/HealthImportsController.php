<?php

/**
 * Hlídání stahování akcí pro monitoring (UptimeRobot): 200, když každý obchod se zdrojem
 * má úspěšné stažení novější než limit, jinak 503. Veřejná URL bez tokenu — neprozradí
 * nic než stav stahování. Ostatní úlohy cronu hlídá `/health/tasks` (R115). Výpadek
 * adminům ohlásí i centrum upozornění (RecordHealthAlerts, R126).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HealthChecks;
use Illuminate\Http\Response;

class HealthImportsController extends Controller
{
    /**
     * Na každém řádku jeden obchod: „Tesco — OK, naposledy …“ nebo „Tesco — VÝPADEK: …“.
     */
    public function __invoke(HealthChecks $checks): Response
    {
        return $checks->imports()->response();
    }
}
