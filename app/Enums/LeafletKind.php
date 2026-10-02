<?php

/**
 * Druh zdroje nabídek — tištěný leták, akční stránka webu, nebo akce e-shopu (R4).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum LeafletKind: string
{
    case Leaflet = 'leaflet';
    case Web = 'web';
    case Eshop = 'eshop';
}
