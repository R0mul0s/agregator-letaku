<?php

/**
 * Upozornění v telefonu na nové akce hlídaných položek (web push, R66). Cron /cron/send-digests
 * ho volá spolu s e-mailovými souhrny každou hodinu 6:30–22:30 — v noci tak upozornění nechodí.
 *
 * Skládá se ze záznamů centra upozornění (R74, RecordNewOffers, RecordEndingOffers
 * a RecordStartingOffers je zapíšou těsně předtím): uživatel s aspoň jedním zařízením dostane
 * upozornění na nepřečtené záznamy (nové akce, končící akce ze seznamu, dnes začínající akce
 * (R76), zprávy od nás se zaškrtnutým „i do telefonu“ — každý
 * druh zvlášť), které vznikly po jeho posledním upozornění, nejvýš jednou za `letaky.push.interval_hours`.
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
use App\Domain\Notifications\AnnouncementRecord;
use App\Domain\Notifications\NotificationPresenter;
use App\Domain\Notifications\OffersNotification;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSubscriptions;
use App\Domain\Push\Vapid;
use App\Enums\NotificationKind;
use App\Models\Offer;
use App\Models\User;
use App\Support\PriceFormatter;
use App\Support\ShortDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Throwable;

final class SendPushNotifications
{
    public function __construct(
        private readonly MyOffers $myOffers,
        private readonly PushSubscriptions $subscriptions,
        private readonly Vapid $vapid,
        private readonly PriceFormatter $prices,
        private readonly NotificationPresenter $presenter,
        private readonly LocalCalendar $calendar,
        private readonly ShortDate $dates,
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
     * Záznamy, které vznikly po posledním upozornění uživatele (dotaz uvnitř whereHas nad
     * users — sloupec push_sent_at je z vnějšího dotazu).
     *
     * @param  Builder<DatabaseNotification>  $query
     */
    private function whereNotPushed(Builder $query): void
    {
        $this->wherePushable($query);
        $query->where(fn (Builder $query) => $query
            ->whereNull('users.push_sent_at')
            ->orWhereColumn('notifications.created_at', '>', 'users.push_sent_at'));
    }

    /**
     * Záznamy, které jdou do telefonu: všechny s akcemi, zprávy od nás jen se zaškrtnutým
     * „i do telefonu“ (jen zprávy o službě, 11d).
     *
     * @param  Builder<DatabaseNotification>  $query
     */
    private function wherePushable(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('type', '!=', NotificationKind::Announcement->value)
            ->orWhere('data->push', true));
    }

    /**
     * Pošle uživateli na všechna jeho zařízení jedno upozornění za každý druh nových záznamů
     * (nové akce, končící akce ze seznamu, zpráva od nás) — každý druh má v liště telefonu své místo.
     */
    private function notify(User $user): bool
    {
        /** @var MorphMany<DatabaseNotification, User> $unread */
        $unread = $user->unreadNotifications();
        $unreadCount = (clone $unread)->count();
        /** @var Collection<int, DatabaseNotification> $records */
        $records = (clone $unread)
            ->tap(fn (Builder $query) => $this->wherePushable($query))
            ->when($user->push_sent_at, fn (Builder $query, CarbonImmutable $at) => $query->where('created_at', '>', $at))
            ->get();

        $delivered = false;
        foreach (NotificationKind::cases() as $kind) {
            $ofKind = $records->filter(fn (DatabaseNotification $record): bool => $record->type === $kind->value)->values();
            $message = match (true) {
                $ofKind->isEmpty() => null,
                $kind === NotificationKind::Announcement => $this->announcementMessage($ofKind, $unreadCount),
                default => $this->message($user, $kind, $ofKind, $unreadCount),
            };
            if ($message === null) {
                continue;
            }

            foreach ($user->pushSubscriptions as $subscription) {
                $delivered = $this->subscriptions->deliver($subscription, $message) || $delivered;
            }
        }

        return $delivered;
    }

    /**
     * Text upozornění: nadpis jako v centru upozornění (jedna nová akce s názvem hlídané
     * položky, jinak s počtem), v textu prvních pár akcí s cenou a obchodem. Null = záznamy
     * bez akcí.
     *
     * @param  Collection<int, DatabaseNotification>  $records  Jednoho druhu, od nejnovějšího
     */
    private function message(User $user, NotificationKind $kind, Collection $records, int $unreadCount): ?PushMessage
    {
        $groups = array_merge(...$records->map(OffersNotification::groups(...))->all());
        $offerIds = array_values(array_unique(array_merge([], ...array_column($groups, 'offerIds'))));
        $offers = Offer::query()->findMany($offerIds)->keyBy('id');
        $offers = array_values(array_filter(array_map(fn (int $id): ?Offer => $offers->get($id), $offerIds)));
        if ($offers === []) {
            return null;
        }

        $count = count($offers);
        $shown = array_slice($offers, 0, config()->integer('letaky.push.max_offers'));
        // Nejlevnější za sledované období (etapa 11c) — dovětek u akce, u jedné i v nadpisu
        $lowest = array_merge([], ...$records->map(OffersNotification::lowestOfferIds(...))->all());

        $today = $this->calendar->today();
        $lines = array_map(function (Offer $offer) use ($user, $lowest, $today): string {
            $price = $this->myOffers->userPrice($user, $offer);
            $line = __(in_array($offer->id, $lowest, true) ? 'app.push.line_lowest' : 'app.push.line', [
                'name' => $offer->name,
                'price' => $price === null ? __('app.digest.no_price') : $this->prices->format($price),
                'chain' => $offer->chain->label(),
                'weeks' => config()->integer('letaky.price_history.weeks'),
            ]);

            // Akce, která ještě nezačala (R76) — ať ji nikdo nehledá v obchodě dnes
            return $offer->isUpcoming($today)
                ? __('app.push.starts', ['line' => $line, 'date' => $this->dates->format($offer->valid_from)])
                : $line;
        }, $shown);
        if ($count > count($shown)) {
            $lines[] = trans_choice('app.push.more', $count - count($shown));
        }

        // Jedna akce: nadpis podle skupiny (hlídané položky), ke které patří
        $titleGroups = $count === 1
            ? array_values(array_filter($groups, fn (array $group): bool => in_array($offers[0]->id, $group['offerIds'], true)))
            : $groups;
        $single = $records->count() === 1 ? $records->first() : null;

        return new PushMessage(
            title: $this->presenter->title($kind, $titleGroups, $count, in_array($offers[0]->id, $lowest, true)),
            body: implode("\n", $lines),
            url: $single === null
                ? route('notifications.index', absolute: false)
                : route('notifications.show', $single->id, absolute: false),
            tag: $kind->pushTag(),
            badge: $unreadCount,
        );
    }

    /**
     * Upozornění na zprávu od nás (11d): nejnovější zpráva s nadpisem a začátkem textu,
     * klepnutí otevře její záznam.
     *
     * @param  Collection<int, DatabaseNotification>  $records  Zprávy od nejnovější
     */
    private function announcementMessage(Collection $records, int $unreadCount): ?PushMessage
    {
        $latest = $records->first();
        if ($latest === null) {
            return null;
        }

        $announcement = AnnouncementRecord::read($latest);

        return new PushMessage(
            title: $announcement['title'],
            body: Str::limit($announcement['body'], config()->integer('letaky.notifications.announcement_excerpt')),
            url: route('notifications.show', $latest->id, absolute: false),
            tag: NotificationKind::Announcement->pushTag(),
            badge: $unreadCount,
        );
    }
}
