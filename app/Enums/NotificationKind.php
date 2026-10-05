<?php

/**
 * Druh záznamu v centru upozornění (R74) — sloupec notifications.type (databaseType notifikace).
 * Další druhy (akce ze seznamu brzy končí, akce zlevnila, zprávy od nás) přibudou v dalších etapách.
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
}
