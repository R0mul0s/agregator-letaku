<?php

/**
 * Záznam v centru upozornění (R74): nové akce na hlídané zboží po stažení letáků, skupiny
 * po hlídaných položkách (RecordNewOffers). Akce, které byly nejlevnější za sledované období
 * (etapa 11c), jsou v `lowestOfferIds` — snímek k okamžiku upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;

final class NewOffersNotification extends OffersNotification
{
    /**
     * @param  list<array{title: string, offerIds: list<int>}>  $groups  Akce po hlídaných položkách
     * @param  list<int>  $lowestOfferIds  Akce nejlevnější za sledované období (PriceHistory, R59)
     */
    public function __construct(array $groups, private readonly array $lowestOfferIds = [])
    {
        parent::__construct($groups);
    }

    /**
     * Druh záznamu.
     */
    public function kind(): NotificationKind
    {
        return NotificationKind::NewOffers;
    }

    /**
     * Data záznamu i s nejlevnějšími akcemi.
     *
     * @return array{groups: list<array{title: string, offerIds: list<int>}>, lowestOfferIds: list<int>}
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'lowestOfferIds' => $this->lowestOfferIds];
    }
}
