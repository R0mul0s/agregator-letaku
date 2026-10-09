<?php

/**
 * Úloha cronu, jejíž poslední úspěšný běh hlídá `/health/tasks` (R115). Hodnota je klíč
 * v tabulce `task_heartbeats`, limit v `letaky.health.max_task_age_hours` a název
 * v `app.health.tasks`. Kanály upozornění (R74) mají hodnotu shodnou s klíčem textů
 * výsledku v `lang/cs/app.php` (`app.<kanál>.done`).
 *
 * Stažení akcí hlídá `/health/imports` (`scrape_runs`) a seznamy akcí prodejen jejich
 * `stores.offer_keys_fetched_at` — ty tu nejsou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Enums;

enum CronTask: string
{
    case Notifications = 'notifications';
    case EndingSoon = 'ending_soon';
    case StartingToday = 'starting_today';
    case Digest = 'digest';
    case Push = 'push';
    case Maintenance = 'maintenance';
    case Categories = 'categories';

    /**
     * Po kolika hodinách bez úspěšného běhu je úloha výpadek.
     */
    public function maxAgeHours(): int
    {
        return config()->integer("letaky.health.max_task_age_hours.{$this->value}");
    }

    /**
     * Název úlohy pro výpis na `/health/tasks`.
     */
    public function label(): string
    {
        return __("app.health.tasks.{$this->value}");
    }
}
