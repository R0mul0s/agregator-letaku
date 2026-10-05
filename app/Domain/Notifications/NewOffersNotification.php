<?php

/**
 * Záznam v centru upozornění (R74): nové akce na hlídané zboží po stažení letáků. Ukládá jen
 * názvy hlídaných položek a ID akcí — akce se nemažou (R10), stránka je načte i později
 * a skončené označí. Jen kanál database, bez fronty (hosting ji nemá, R20).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

final class NewOffersNotification extends Notification
{
    /**
     * @param  list<array{watchItem: string, offerIds: list<int>}>  $groups  Akce po hlídaných položkách
     */
    public function __construct(private readonly array $groups) {}

    /**
     * Kanály doručení — jen záznam v databázi; telefon a e-mail mají vlastní odesílání.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Druh záznamu ve sloupci type — krátký kód místo názvu třídy (přejmenování třídy nevadí).
     */
    public function databaseType(object $notifiable): string
    {
        return NotificationKind::NewOffers->value;
    }

    /**
     * Data záznamu.
     *
     * @return array{groups: list<array{watchItem: string, offerIds: list<int>}>}
     */
    public function toArray(object $notifiable): array
    {
        return ['groups' => $this->groups];
    }

    /**
     * Akce po hlídaných položkách z uloženého záznamu; poškozené části vynechá.
     *
     * @return list<array{watchItem: string, offerIds: list<int>}>
     */
    public static function groups(DatabaseNotification $notification): array
    {
        $groups = [];
        foreach ((array) ($notification->data['groups'] ?? []) as $group) {
            if (! is_array($group) || ! is_string($group['watchItem'] ?? null) || ! is_array($group['offerIds'] ?? null)) {
                continue;
            }

            $groups[] = [
                'watchItem' => $group['watchItem'],
                'offerIds' => array_values(array_filter($group['offerIds'], is_int(...))),
            ];
        }

        return $groups;
    }

    /**
     * Všechna ID akcí záznamu v pořadí hlídaných položek, bez opakování.
     *
     * @return list<int>
     */
    public static function offerIds(DatabaseNotification $notification): array
    {
        return array_values(array_unique(array_merge([], ...array_column(self::groups($notification), 'offerIds'))));
    }
}
