<?php

/**
 * Řazení akcí v Mých slevách — předvolba uživatele (R41). Uvnitř každé hlídané položky
 * jsou vždy nejdřív jisté shody, „možná“ na konci (R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum OffersSort: string
{
    /** Od nejnižší ceny za kilo, litr nebo kus, kterou uživatel zaplatí (R19). */
    case UnitPrice = 'unit_price';

    /** Od nejvyšší slevy v procentech; akce bez známé slevy na konec. */
    case Discount = 'discount';

    /** Od akce, která končí nejdřív. */
    case EndingSoon = 'ending_soon';

    /**
     * Název řazení pro zobrazení (lang/cs/app.php, skupina ui.offers_sort).
     */
    public function label(): string
    {
        return __('app.ui.offers_sort.'.$this->value);
    }

    /**
     * Samostatný název (výběr řazení v Mých slevách, R102) — label() navazuje na „Řadit od“.
     */
    public function shortLabel(): string
    {
        return __('app.ui.offers_sort_short.'.$this->value);
    }
}
