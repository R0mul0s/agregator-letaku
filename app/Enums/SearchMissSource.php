<?php

/**
 * Odkud je hledání, které nic nenašlo (R171): pole hledání ve Všech akcích, nebo „Co chcete
 * hlídat?“ v Hlídám (bez produktu katalogu a bez akce).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Enums;

enum SearchMissSource: string
{
    case Offers = 'offers';
    case Watch = 'watch';

    /**
     * Popisek pro admina v katalogu.
     */
    public function label(): string
    {
        return __('app.ui.catalog.search_misses.sources.'.$this->value);
    }
}
