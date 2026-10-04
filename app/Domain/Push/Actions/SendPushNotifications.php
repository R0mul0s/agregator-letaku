<?php

/**
 * Upozornění v telefonu na nové akce hlídaných položek (web push, R66). Cron /cron/send-digests
 * ho volá spolu s e-mailovými souhrny každou hodinu 6:30–22:30 — v noci tak upozornění nechodí.
 * Uživatel s aspoň jedním zařízením dostane upozornění po stažení, které přineslo nové akce,
 * nejvýš jednou za `letaky.push.interval_hours`. Akce jsou stejné jako v e-mailu (NewOffers).
 *
 * Po dávkách jako souhrny (R54): jedno volání zpracuje nejvýš `letaky.push.users_per_run`
 * uživatelů, od nejdéle čekajících; kdo nové akce nemá, je zpracovaný taky (`push_sent_at`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push\Actions;

use App\Domain\Digest\NewOffers;
use App\Domain\Matching\MyOffers;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSubscriptions;
use App\Domain\Push\Vapid;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class SendPushNotifications
{
    /** Značka upozornění na akce — nové nahradí předchozí v liště telefonu. */
    private const TAG = 'new-offers';

    public function __construct(
        private readonly NewOffers $newOffers,
        private readonly MyOffers $myOffers,
        private readonly PushSubscriptions $subscriptions,
        private readonly Vapid $vapid,
        private readonly PriceFormatter $prices,
    ) {}

    /**
     * Pošle upozornění jedné dávce uživatelů, kterým je čas; vrátí počet uživatelů, kterým
     * aspoň na jedno zařízení došlo. Chyba u jednoho uživatele se zapíše do logu, ostatní
     * upozornění dostanou dál.
     */
    public function __invoke(): int
    {
        $lastImport = $this->newOffers->lastImportFinishedAt();
        if (! $this->vapid->isConfigured() || $lastImport === null) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $sent = 0;

        $users = User::query()
            ->whereHas('pushSubscriptions')
            ->where(fn (Builder $query) => $query->whereNull('push_sent_at')->orWhere('push_sent_at', '<', $lastImport))
            ->where(fn (Builder $query) => $query
                ->whereNull('push_sent_at')
                ->orWhere('push_sent_at', '<=', $now->subHours(config()->integer('letaky.push.interval_hours'))))
            ->with('pushSubscriptions')
            ->orderBy('push_sent_at')
            ->orderBy('id')
            ->limit(config()->integer('letaky.push.users_per_run'))
            ->get();

        foreach ($users as $user) {
            try {
                if ($this->notify($user)) {
                    $sent++;
                }
                $user->forceFill(['push_sent_at' => $now])->save();
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $sent;
    }

    /**
     * Pošle uživateli upozornění na všechna jeho zařízení, pokud má nové akce.
     */
    private function notify(User $user): bool
    {
        $groups = $this->newOffers->forUser($user, $user->push_sent_at);
        if ($groups === []) {
            return false;
        }

        $message = $this->message($user, $groups);
        $delivered = false;
        foreach ($user->pushSubscriptions as $subscription) {
            $delivered = $this->subscriptions->deliver($subscription, $message) || $delivered;
        }

        return $delivered;
    }

    /**
     * Text upozornění: jedna akce s názvem hlídané položky v nadpisu, víc akcí s počtem;
     * v textu prvních pár akcí s cenou a obchodem.
     *
     * @param  non-empty-list<array{watchItem: WatchItem, offers: list<Offer>}>  $groups
     */
    private function message(User $user, array $groups): PushMessage
    {
        $offers = array_merge(...array_column($groups, 'offers'));
        $count = count($offers);
        $shown = array_slice($offers, 0, config()->integer('letaky.push.max_offers'));

        $lines = array_map(function (Offer $offer) use ($user): string {
            $price = $this->myOffers->userPrice($user, $offer);

            return __('app.push.line', [
                'name' => $offer->name,
                'price' => $price === null ? __('app.digest.no_price') : $this->prices->format($price),
                'chain' => $offer->chain->label(),
            ]);
        }, $shown);
        if ($count > count($shown)) {
            $lines[] = trans_choice('app.push.more', $count - count($shown));
        }

        return new PushMessage(
            title: $count === 1
                ? __('app.push.title_one', ['name' => $groups[0]['watchItem']->name])
                : trans_choice('app.push.title_many', $count),
            body: implode("\n", $lines),
            url: route('home', absolute: false),
            tag: self::TAG,
            badge: $count,
        );
    }
}
