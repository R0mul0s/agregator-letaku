<?php

/**
 * Druh zprávy od nás (R74, etapa 11d). Zpráva o službě (nový obchod, změna podmínek) jde všem
 * a smí i do telefonu; propagační (novinky, nabídky partnerů) jen uživatelům se souhlasem
 * s obchodními sděleními (R51) a jen do centra — souhlas zní na e-mail, ne na telefon.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Enums;

enum AnnouncementCategory: string
{
    case Service = 'service';
    case Marketing = 'marketing';

    /**
     * Smí zpráva tohoto druhu i do telefonu?
     */
    public function allowsPush(): bool
    {
        return $this === self::Service;
    }
}
