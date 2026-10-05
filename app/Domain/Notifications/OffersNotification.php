<?php

/**
 * Záznam centra upozornění (R74) s akcemi po skupinách — hlídaná položka u nových akcí,
 * obchod u končících akcí ze seznamu. Ukládá jen nadpisy skupin a ID akcí: akce se nemažou
 * (R10), stránka je načte i později a skončené označí. Jen kanál database, bez fronty (R20).
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

abstract class OffersNotification extends Notification
{
    /**
     * @param  list<array{title: string, offerIds: list<int>}>  $groups  Akce po skupinách
     */
    public function __construct(private readonly array $groups) {}

    /**
     * Druh záznamu.
     */
    abstract public function kind(): NotificationKind;

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
        return $this->kind()->value;
    }

    /**
     * Data záznamu.
     *
     * @return array{groups: list<array{title: string, offerIds: list<int>}>}
     */
    public function toArray(object $notifiable): array
    {
        return ['groups' => $this->groups];
    }

    /**
     * Akce po skupinách z uloženého záznamu; poškozené části vynechá.
     *
     * @return list<array{title: string, offerIds: list<int>}>
     */
    public static function groups(DatabaseNotification $notification): array
    {
        $groups = [];
        foreach ((array) ($notification->data['groups'] ?? []) as $group) {
            if (! is_array($group) || ! is_string($group['title'] ?? null) || ! is_array($group['offerIds'] ?? null)) {
                continue;
            }

            $groups[] = [
                'title' => $group['title'],
                'offerIds' => array_values(array_filter($group['offerIds'], is_int(...))),
            ];
        }

        return $groups;
    }

    /**
     * ID akcí záznamu, které byly v okamžiku upozornění nejlevnější za sledované období
     * (PriceHistory, R59; etapa 11c) — jen u nových akcí, jinak prázdné.
     *
     * @return list<int>
     */
    public static function lowestOfferIds(DatabaseNotification $notification): array
    {
        return array_values(array_filter((array) ($notification->data['lowestOfferIds'] ?? []), is_int(...)));
    }

    /**
     * Všechna ID akcí záznamu v pořadí skupin, bez opakování.
     *
     * @return list<int>
     */
    public static function offerIds(DatabaseNotification $notification): array
    {
        return array_values(array_unique(array_merge([], ...array_column(self::groups($notification), 'offerIds'))));
    }
}
