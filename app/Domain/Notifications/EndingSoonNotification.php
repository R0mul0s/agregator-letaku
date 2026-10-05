<?php

/**
 * Záznam v centru upozornění (R74, etapa 11b): akce z nákupního seznamu, které zítra končí,
 * skupiny po obchodech (RecordEndingOffers).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;

final class EndingSoonNotification extends OffersNotification
{
    /**
     * Druh záznamu.
     */
    public function kind(): NotificationKind
    {
        return NotificationKind::EndingSoon;
    }
}
