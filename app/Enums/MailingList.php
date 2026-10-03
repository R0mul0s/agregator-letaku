<?php

/**
 * E-maily, ze kterých se jde odhlásit jedním klepnutím bez přihlášení (R51):
 * souhrn akcí (R42) a obchodní sdělení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Enums;

enum MailingList: string
{
    case Digest = 'souhrn';
    case Marketing = 'novinky';

    /**
     * Název pro zobrazení (lang/cs/app.php, skupina ui.mailing_lists).
     */
    public function label(): string
    {
        return __('app.ui.mailing_lists.'.$this->value);
    }
}
