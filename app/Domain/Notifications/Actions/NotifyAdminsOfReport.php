<?php

/**
 * Nové hlášení chyby v akci adminům (R125) — záznam v centru upozornění a hned i do telefonu
 * (AdminAlerts), i tomu, kdo hlásí (vlastní hlášení je úkol na později, R126). Z detailu
 * záznamu tlačítko vede na stránku Hlášení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\AdminAlerts;
use App\Enums\NotificationKind;
use App\Models\OfferReport;
use Illuminate\Support\Str;

final readonly class NotifyAdminsOfReport
{
    public function __construct(private AdminAlerts $alerts) {}

    /**
     * Upozorní adminy na hlášení; vrátí počet upozorněných.
     */
    public function __invoke(OfferReport $report): int
    {
        $offer = $report->offer;
        $body = __('app.notifications.offer_report.body', [
            'reason' => $report->reason->label(),
            'offer' => $offer->name,
            'chain' => $offer->chain->label(),
        ]);
        if ($report->note !== null) {
            $body .= ' '.__('app.notifications.offer_report.note', ['note' => Str::limit($report->note, config()->integer('letaky.offer_reports.push_note_length'))]);
        }

        return $this->alerts->send(
            NotificationKind::OfferReport,
            __('app.notifications.offer_report.title'),
            $body,
            route('reports.index', absolute: false),
        );
    }
}
