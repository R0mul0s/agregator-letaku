<?php

/**
 * Druh záznamu v centru upozornění (R74) — sloupec notifications.type (databaseType notifikace).
 * Další druhy (akce zlevnila, zprávy od nás) přibudou v dalších etapách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Enums;

enum NotificationKind: string
{
    /** Nové akce na hlídané zboží po stažení letáků. */
    case NewOffers = 'new_offers';

    /** Akce z nákupního seznamu zítra končí (etapa 11b). */
    case EndingSoon = 'ending_soon';

    /**
     * Značka upozornění v telefonu — nové nahradí předchozí stejného druhu v liště telefonu,
     * jiný druh ho nepřepíše.
     */
    public function pushTag(): string
    {
        return match ($this) {
            self::NewOffers => 'new-offers',
            self::EndingSoon => 'ending-soon',
        };
    }
}
