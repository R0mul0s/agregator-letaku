<?php

/**
 * Záznam v centru upozornění (R76): hlídané akce a akce z nákupního seznamu, které dnes
 * začínají a známe je dopředu, skupiny po hlídaných položkách a nákupní seznam (RecordStartingOffers).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;

final class StartingTodayNotification extends OffersNotification
{
    /**
     * Druh záznamu.
     */
    public function kind(): NotificationKind
    {
        return NotificationKind::StartingToday;
    }
}
