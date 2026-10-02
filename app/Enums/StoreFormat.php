<?php

/**
 * Formát prodejny. Albert a Tesco mají pro hypermarkety a supermarkety odlišné letáky (PLAN.md, kap. 2).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum StoreFormat: string
{
    case Hypermarket = 'hypermarket';
    case Supermarket = 'supermarket';

    /**
     * Název formátu pro zobrazení (lang/cs/app.php, skupina store_formats).
     */
    public function label(): string
    {
        return __('app.store_formats.'.$this->value);
    }
}
