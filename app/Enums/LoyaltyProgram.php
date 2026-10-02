<?php

/**
 * Věrnostní programy obchodů — cena s kartou nebo aplikací se ukládá vedle běžné ceny (R8).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyProgram: string
{
    case KauflandCard = 'kaufland_card';
    case Clubcard = 'clubcard';
    case MujAlbert = 'muj_albert';
    case LidlPlus = 'lidl_plus';
    case PennyKarta = 'penny_karta';

    /**
     * Název programu pro zobrazení (lang/cs/app.php, skupina ui.loyalty_programs).
     */
    public function label(): string
    {
        return __('app.ui.loyalty_programs.'.$this->value);
    }
}
