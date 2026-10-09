<?php

/**
 * Hlídání ostatních úloh cronu pro monitoring (UptimeRobot, R115): seznamy akcí prodejen
 * (R49, podle `stores.offer_keys_fetched_at`), každý kanál upozornění zvlášť, denní úklid
 * a kategorie (podle `task_heartbeats`). 200, když má každá úloha úspěšný běh novější než
 * svůj limit, jinak 503. Veřejná URL bez tokenu, bez textu chyb.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Sources\SourceRegistry;
use App\Enums\CronTask;
use App\Models\Store;
use App\Models\TaskHeartbeat;
use App\Support\HealthReport;
use Illuminate\Http\Response;

class HealthTasksController extends Controller
{
    /**
     * Na každém řádku jedna úloha: „E-mailové souhrny — OK, naposledy …“ nebo „… — VÝPADEK: …“.
     */
    public function __invoke(SourceRegistry $sources): Response
    {
        $report = new HealthReport;

        foreach ($sources->chainsWithStores() as $chain) {
            $report->check(
                __('app.health.store_lists', ['chain' => $chain->label()]),
                Store::lastListFetchedAt($chain),
                config()->integer('letaky.health.max_store_lists_age_hours'),
                'app.health.outage',
            );
        }

        $heartbeats = TaskHeartbeat::query()->get()->keyBy(fn (TaskHeartbeat $heartbeat): string => $heartbeat->task->value);
        foreach (CronTask::cases() as $task) {
            $heartbeat = $heartbeats->get($task->value);
            $report->check($task->label(), $heartbeat?->succeeded_at, $task->maxAgeHours(), 'app.health.task_outage', $heartbeat?->failed_at);
        }

        return $report->response();
    }
}
