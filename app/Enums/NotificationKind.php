<?php

/**
 * Druh záznamu v centru upozornění (R74) — sloupec notifications.type (databaseType notifikace).
 * Záznamy s akcemi dědí z OffersNotification, zprávy od nás zapisuje SendAnnouncement hromadně.
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

    /** Hlídané akce a akce ze seznamu, které dnes začínají a známe je dopředu (R76). */
    case StartingToday = 'starting_today';

    /** Zpráva od nás — o službě, nebo propagační jen se souhlasem (etapa 11d). */
    case Announcement = 'announcement';

    /** Jen admin: nové hlášení chyby v akci (R125). */
    case OfferReport = 'offer_report';

    /** Jen admin: výpadek stahování nebo úlohy cronu, nebo že zase vše běží (R126). */
    case SystemAlert = 'system_alert';

    /**
     * Záznam pro adminy — nadpis a text jsou v datech (AdminAlerts) a do telefonu jde hned při
     * zápisu, ne z cronu upozornění (SendPushNotifications ho vynechá).
     */
    public function isAdminAlert(): bool
    {
        return $this === self::OfferReport || $this === self::SystemAlert;
    }

    /**
     * Značka upozornění v telefonu — nové nahradí předchozí stejného druhu v liště telefonu,
     * jiný druh ho nepřepíše.
     */
    public function pushTag(): string
    {
        return match ($this) {
            self::NewOffers => 'new-offers',
            self::EndingSoon => 'ending-soon',
            self::StartingToday => 'starting-today',
            self::Announcement => 'announcement',
            self::OfferReport => 'offer-report',
            self::SystemAlert => 'system-alert',
        };
    }
}
