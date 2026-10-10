<?php

/**
 * Hlídání ostatních úloh cronu pro monitoring (UptimeRobot, R115): seznamy akcí prodejen
 * (R49, podle `stores.offer_keys_fetched_at`), každý kanál upozornění zvlášť, denní úklid
 * a kategorie (podle `task_heartbeats`). 200, když má každá úloha úspěšný běh novější než
 * svůj limit, jinak 503. Veřejná URL bez tokenu, bez textu chyb. Výpadek adminům ohlásí
 * i centrum upozornění (RecordHealthAlerts, R126).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HealthChecks;
use Illuminate\Http\Response;

class HealthTasksController extends Controller
{
    /**
     * Na každém řádku jedna úloha: „E-mailové souhrny — OK, naposledy …“ nebo „… — VÝPADEK: …“.
     */
    public function __invoke(HealthChecks $checks): Response
    {
        return $checks->tasks()->response();
    }
}
