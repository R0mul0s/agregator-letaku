<?php

/**
 * Upozornění v telefonu adminům na nové hlášení chyby v akci (R125) — hned po odeslání
 * formuláře, ne až z cronu (fronta na hostingu není). Dostanou ho zařízení adminů se zapnutými
 * upozorněními, kromě toho, kdo hlásí; klepnutí otevře stránku Hlášení. Chyba push služby
 * odeslání hlášení nezastaví — zapíše se do logu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Push\Actions;

use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSubscriptions;
use App\Models\OfferReport;
use App\Models\PushSubscription;
use Illuminate\Support\Str;
use Throwable;

final readonly class NotifyAdminsOfReport
{
    /** Značka upozornění — další hlášení nahradí předchozí v liště, nepřibývají. */
    private const TAG = 'offer-report';

    public function __construct(private PushSubscriptions $subscriptions) {}

    /**
     * Pošle upozornění na hlášení; vrátí počet doručených.
     */
    public function __invoke(OfferReport $report): int
    {
        $devices = PushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('is_admin', true)->whereKeyNot($report->user_id))
            ->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        $message = $this->message($report);
        $sent = 0;
        foreach ($devices as $device) {
            try {
                $sent += (int) $this->subscriptions->deliver($device, $message);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    /**
     * Důvod, akce a obchod, případně začátek popisu od uživatele.
     */
    private function message(OfferReport $report): PushMessage
    {
        $offer = $report->offer;
        $body = __('app.push.report_body', [
            'reason' => $report->reason->label(),
            'offer' => $offer->name,
            'chain' => $offer->chain->label(),
        ]);
        if ($report->note !== null) {
            $body .= ' '.__('app.push.report_note', ['note' => Str::limit($report->note, config()->integer('letaky.offer_reports.push_note_length'))]);
        }

        return new PushMessage(
            title: __('app.push.report_title'),
            body: $body,
            url: route('reports.index', absolute: false),
            tag: self::TAG,
        );
    }
}
