<?php

/**
 * Události pro Google Analytics, které ohlásí server po úspěšném uložení (R134). Hodnota
 * je název události v GA4 — změna názvu rozdělí statistiky na dvě a klíčovou událost
 * je potřeba v GA označit znovu. Sdílení měří prohlížeč (`lib/share.js`, událost `share`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Enums;

enum AnalyticsEvent: string
{
    // Doporučená událost GA4, parametr `method` (email, google, seznam, facebook)
    case SignUp = 'sign_up';
    // Parametr `item_type` (product = z katalogu, keywords = vlastní slova)
    case AddWatchItem = 'add_watch_item';
    // Parametr `item_type` (offer = akce, custom = vlastní položka) a `chain` (obchod, je-li)
    case AddToShoppingList = 'add_to_shopping_list';
}
