<?php

/**
 * Kontroly hlídání (R115): stahování akcí obchodů (`/health/imports`) a ostatní úlohy cronu
 * (`/health/tasks`) — seznamy akcí prodejen, kanály upozornění, úklid a kategorie. Sdílí je
 * odpovědi pro monitoring a upozornění adminům na výpadek (RecordHealthAlerts, R126).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Support;

use App\Domain\Sources\SourceRegistry;
use App\Enums\CronTask;
use App\Models\ScrapeRun;
use App\Models\Store;
use App\Models\TaskHeartbeat;

final readonly class HealthChecks
{
    public function __construct(private SourceRegistry $sources) {}

    /**
     * Každý obchod se zdrojem: úspěšné stažení novější než limit.
     */
    public function imports(): HealthReport
    {
        $report = new HealthReport;
        foreach ($this->sources->chainsWithOffers() as $chain) {
            $report->check(
                $chain->label(),
                ScrapeRun::lastFinishedAt(chain: $chain),
                config()->integer('letaky.health.max_import_age_hours'),
                'app.health.outage',
            );
        }

        return $report;
    }

    /**
     * Seznamy akcí prodejen (R49, podle `stores.offer_keys_fetched_at`) a úlohy cronu podle
     * `task_heartbeats`: úspěšný běh novější než limit úlohy.
     */
    public function tasks(): HealthReport
    {
        $report = new HealthReport;
        foreach ($this->sources->chainsWithStores() as $chain) {
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

        return $report;
    }
}
