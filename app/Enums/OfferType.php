<?php

/**
 * Typ akce. „Sleva“ je jen nabídka s původní cenou (R8).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum OfferType: string
{
    /** Akční cena s uvedenou původní cenou. */
    case Discount = 'discount';

    /** Akční cena bez původní ceny — Kaufland „AKCE! pouze“, Tesco „Super cena“. Nemusí být nižší než obvykle. */
    case PromoPrice = 'promo_price';

    /** Nižší cena platí jen s věrnostní kartou nebo aplikací; bez ní se platí běžná cena. */
    case LoyaltyOnly = 'loyalty_only';

    /** Akce na množství nebo kombinaci („3 za cenu 2“, „MENU“) — podrobnosti v promotion_text. */
    case Multibuy = 'multibuy';

    /**
     * Název typu pro zobrazení (lang/cs/app.php, skupina ui.offer_types).
     */
    public function label(): string
    {
        return __('app.ui.offer_types.'.$this->value);
    }
}
