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
     * Obchod, ke kterému program patří.
     */
    public function chain(): Chain
    {
        return match ($this) {
            self::KauflandCard => Chain::Kaufland,
            self::Clubcard => Chain::Tesco,
            self::MujAlbert => Chain::Albert,
            self::LidlPlus => Chain::Lidl,
            self::PennyKarta => Chain::Penny,
        };
    }

    /**
     * Název programu pro zobrazení (lang/cs/app.php, skupina ui.loyalty_programs).
     */
    public function label(): string
    {
        return __('app.ui.loyalty_programs.'.$this->value);
    }
}
