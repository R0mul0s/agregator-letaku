<?php

/**
 * Upozornění v telefonu na nové akce hlídaných položek (web push, R66). Cron /cron/send-digests
 * ho volá spolu s e-mailovými souhrny každou hodinu 6:30–22:30 — v noci tak upozornění nechodí.
 *
 * Skládá se ze záznamů centra upozornění (R74, RecordNewOffers je zapíše těsně předtím):
 * uživatel s aspoň jedním zařízením dostane upozornění na nepřečtené záznamy o nových akcích,
 * které vznikly po jeho posledním upozornění, nejvýš jednou za `letaky.push.interval_hours`.
 * Klepnutí otevře záznam v centru (víc záznamů = celé centrum), číslo na ikoně aplikace
 * je počet nepřečtených záznamů — stejné jako u zvonku v hlavičce.
 *
 * Po dávkách jako souhrny (R54): jedno volání zpracuje nejvýš `letaky.push.users_per_run` uživatelů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push\Actions;

use App\Domain\Matching\MyOffers;
use App\Domain\Notifications\NewOffersNotification;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSubscriptions;
use App\Domain\Push\Vapid;
use App\Enums\NotificationKind;
use App\Models\Offer;
use App\Models\User;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

final class SendPushNotifications
{
    /** Značka upozornění na akce — nové nahradí předchozí v liště telefonu. */
    private const TAG = 'new-offers';

    public function __construct(
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
        if (! $this->vapid->isConfigured()) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $sent = 0;

        $users = User::query()
            // Neověřenému účtu služba nic neposílá (podmínky čl. 4.1, R67) — jako e-maily (R51)
            ->whereNotNull('email_verified_at')
            ->whereHas('pushSubscriptions')
            ->whereHas('unreadNotifications', fn (Builder $query) => $this->whereNotPushed($query))
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
     * Záznamy o nových akcích, které vznikly po posledním upozornění uživatele (dotaz uvnitř
     * whereHas nad users — sloupec push_sent_at je z vnějšího dotazu).
     *
     * @param  Builder<DatabaseNotification>  $query
     */
    private function whereNotPushed(Builder $query): void
    {
        $query->where('type', NotificationKind::NewOffers->value)
            ->where(fn (Builder $query) => $query
                ->whereNull('users.push_sent_at')
                ->orWhereColumn('notifications.created_at', '>', 'users.push_sent_at'));
    }

    /**
     * Pošle uživateli upozornění na všechna jeho zařízení.
     */
    private function notify(User $user): bool
    {
        /** @var MorphMany<DatabaseNotification, User> $unread */
        $unread = $user->unreadNotifications();
        /** @var Collection<int, DatabaseNotification> $records */
        $records = (clone $unread)
            ->where('type', NotificationKind::NewOffers->value)
            ->when($user->push_sent_at, fn (Builder $query, CarbonImmutable $at) => $query->where('created_at', '>', $at))
            ->get();

        $message = $this->message($user, $records, $unread->count());
        if ($message === null) {
            return false;
        }

        $delivered = false;
        foreach ($user->pushSubscriptions as $subscription) {
            $delivered = $this->subscriptions->deliver($subscription, $message) || $delivered;
        }

        return $delivered;
    }

    /**
     * Text upozornění: jedna akce s názvem hlídané položky v nadpisu, víc akcí s počtem;
     * v textu prvních pár akcí s cenou a obchodem. Null = záznamy bez akcí.
     *
     * @param  Collection<int, DatabaseNotification>  $records  Od nejnovějšího
     */
    private function message(User $user, Collection $records, int $unreadCount): ?PushMessage
    {
        $groups = array_merge(...$records->map(NewOffersNotification::groups(...))->all());
        $offerIds = array_values(array_unique(array_merge([], ...array_column($groups, 'offerIds'))));
        $offers = Offer::query()->findMany($offerIds)->keyBy('id');
        $offers = array_values(array_filter(array_map(fn (int $id): ?Offer => $offers->get($id), $offerIds)));
        if ($offers === []) {
            return null;
        }

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

        $single = $records->count() === 1 ? $records->first() : null;
        // Jedna akce: nadpis s názvem hlídané položky, ke které patří
        $watchItem = collect($groups)->first(fn (array $group): bool => in_array($offers[0]->id, $group['offerIds'], true));

        return new PushMessage(
            title: $count === 1
                ? __('app.notifications.new_offers.title_one', ['name' => $watchItem['watchItem'] ?? $offers[0]->name])
                : trans_choice('app.notifications.new_offers.title_many', $count),
            body: implode("\n", $lines),
            url: $single === null
                ? route('notifications.index', absolute: false)
                : route('notifications.show', $single->id, absolute: false),
            tag: self::TAG,
            badge: $unreadCount,
        );
    }
}
