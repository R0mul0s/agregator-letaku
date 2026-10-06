<?php

/**
 * Obchodní řetězce, jejichž letáky aplikace sleduje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum Chain: string
{
    case Kaufland = 'kaufland';
    case Tesco = 'tesco';
    case Albert = 'albert';
    case Lidl = 'lidl';
    case Penny = 'penny';
    case Globus = 'globus';
    case Billa = 'billa';

    /**
     * Název obchodu pro zobrazení (lang/cs/app.php, skupina chains).
     */
    public function label(): string
    {
        return __('app.chains.'.$this->value);
    }

    /**
     * Název obchodu ve 2. pádě („akce Kauflandu“, lang/cs/app.php, skupina chains_genitive).
     */
    public function genitive(): string
    {
        return __('app.chains_genitive.'.$this->value);
    }
}
