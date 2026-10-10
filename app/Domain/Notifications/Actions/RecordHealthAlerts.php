<?php

/**
 * Upozornění adminům na výpadek (R126): stejné kontroly jako `/health/imports`
 * a `/health/tasks` (HealthChecks) a propady kvality dat — málo akcí nebo ověřených cen
 * v letáku (DataQuality, R129). Ozve se jen při změně — když něco nově přestane fungovat
 * (záznam s výpisem všech výpadků), a jednou, až zase funguje všechno. Výpadek, který trvá, se
 * každou hodinu neopakuje. Které výpadky už admini znají, je v cache.
 *
 * Běží jako poslední kanál cronu upozornění (RunNotificationChannels) — každou hodinu 6:30–22:30,
 * v noci tedy nebudí; výpadek cronu samotného hlídá dál UptimeRobot.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\AdminAlerts;
use App\Domain\Offers\Quality\DataQuality;
use App\Enums\CronTask;
use App\Enums\NotificationKind;
use App\Support\Deadline;
use App\Support\HealthChecks;
use Illuminate\Support\Facades\Cache;

final readonly class RecordHealthAlerts
{
    /** Cache: názvy kontrol, jejichž výpadek admini znají. */
    public const CACHE_KEY = 'health.alerted';

    public function __construct(
        private HealthChecks $checks,
        private AdminAlerts $alerts,
        private DataQuality $quality,
    ) {}

    /**
     * Porovná výpadky s minule ohlášenými a při změně upozorní adminy; vrátí počet upozorněných.
     * Rozpočet cronu nepotřebuje — pár dotazů a pár adminů.
     */
    public function __invoke(?Deadline $deadline = null): int
    {
        $drops = $this->quality->drops();
        $outages = $this->checks->imports()->outages() + $this->checks->tasks()->outages() + $drops;
        // Sebe nehlídá — běh se zapíše až po něm, první běh by se ohlásil jako výpadek
        unset($outages[CronTask::HealthAlerts->label()]);
        /** @var list<string> $known */
        $known = Cache::get(self::CACHE_KEY, []);

        $notified = 0;
        if (array_diff(array_keys($outages), $known) !== []) {
            $notified = $this->alerts->send(
                NotificationKind::SystemAlert,
                trans_choice('app.notifications.system_alert.title', count($outages)),
                implode("\n", $outages),
                // Propad kvality dat — rovnou na přehled s grafy
                $drops === [] ? null : route('data-quality', absolute: false),
            );
        } elseif ($outages === [] && $known !== []) {
            $notified = $this->alerts->send(
                NotificationKind::SystemAlert,
                __('app.notifications.system_alert.recovered_title'),
                __('app.notifications.system_alert.recovered_body', ['names' => implode(', ', $known)]),
                null,
            );
        }

        Cache::forever(self::CACHE_KEY, array_keys($outages));

        return $notified;
    }
}
