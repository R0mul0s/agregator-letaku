<?php

/**
 * Záznam centra upozornění (R74) pro stránku: nadpis, text, čas a stav přečtení. Nadpis
 * nových akcí je stejný jako u upozornění v telefonu („Máslo je v akci“, „3 nové akce…“).
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
     * @return array{id: string, kind: string, title: string, text: string, createdAt: string|null, unread: bool, url: string}|null
     */
    public function summary(DatabaseNotification $notification): ?array
    {
        return match (NotificationKind::tryFrom($notification->type)) {
            NotificationKind::NewOffers => [
                'id' => $notification->id,
                'kind' => NotificationKind::NewOffers->value,
                'title' => $this->newOffersTitle($notification),
                // Hlídané položky, kterých se akce týkají
                'text' => implode(', ', array_column(NewOffersNotification::groups($notification), 'watchItem')),
                'createdAt' => $notification->created_at?->toIso8601String(),
                'unread' => $notification->read_at === null,
                'url' => route('notifications.show', $notification->id, absolute: false),
            ],
            null => null,
        };
    }

    /**
     * Nadpis záznamu o nových akcích: jedna akce s názvem hlídané položky, víc s počtem.
     */
    private function newOffersTitle(DatabaseNotification $notification): string
    {
        $groups = NewOffersNotification::groups($notification);
        $count = count(NewOffersNotification::offerIds($notification));

        return $count === 1
            ? __('app.notifications.new_offers.title_one', ['name' => $groups[0]['watchItem'] ?? ''])
            : trans_choice('app.notifications.new_offers.title_many', $count);
    }
}
