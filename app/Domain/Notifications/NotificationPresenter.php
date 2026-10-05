<?php

/**
 * Záznam centra upozornění (R74) pro stránku a upozornění v telefonu: nadpis, text, čas a stav
 * přečtení. Nadpis je stejný na stránce i v telefonu („Máslo je v akci“, „3 nové akce…“,
 * „Zítra končí 2 akce z vašeho seznamu“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationPresenter
{
    /**
     * Shrnutí záznamu do seznamu; null = druh, který tahle verze neumí ukázat.
     *
     * @return array{id: string, kind: string, title: string, text: string, lowestCount: int, createdAt: string|null, unread: bool, url: string}|null
     */
    public function summary(DatabaseNotification $notification): ?array
    {
        $kind = NotificationKind::tryFrom($notification->type);
        if ($kind === null) {
            return null;
        }

        $groups = OffersNotification::groups($notification);
        $offerIds = OffersNotification::offerIds($notification);
        $lowestCount = count(array_intersect($offerIds, OffersNotification::lowestOfferIds($notification)));

        return [
            'id' => $notification->id,
            'kind' => $kind->value,
            'title' => $this->title($kind, $groups, count($offerIds), $lowestCount > 0),
            // Hlídané položky, nebo obchody, kterých se akce týkají
            'text' => implode(', ', array_column($groups, 'title')),
            // Kolik akcí bylo nejlevnějších za sledované období (etapa 11c) — štítek v seznamu
            'lowestCount' => $lowestCount,
            'createdAt' => $notification->created_at?->toIso8601String(),
            'unread' => $notification->read_at === null,
            'url' => route('notifications.show', $notification->id, absolute: false),
        ];
    }

    /**
     * Nadpis podle druhu: u jedné nové akce s názvem hlídané položky (a „nejlevněji za N týdnů“,
     * je-li nejlevnější za sledované období, etapa 11c), jinak s počtem akcí.
     *
     * @param  list<array{title: string, offerIds: list<int>}>  $groups
     */
    public function title(NotificationKind $kind, array $groups, int $offerCount, bool $lowest = false): string
    {
        $name = $groups[0]['title'] ?? '';

        return match ($kind) {
            NotificationKind::NewOffers => match (true) {
                $offerCount === 1 && $lowest => __('app.notifications.new_offers.title_lowest', [
                    'name' => $name,
                    'weeks' => config()->integer('letaky.price_history.weeks'),
                ]),
                $offerCount === 1 => __('app.notifications.new_offers.title_one', ['name' => $name]),
                default => trans_choice('app.notifications.new_offers.title_many', $offerCount),
            },
            NotificationKind::EndingSoon => trans_choice('app.notifications.ending_soon.title', $offerCount),
        };
    }
}
