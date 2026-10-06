<?php

/**
 * Přihlášení, propojení nebo odpojení účtu u poskytovatele nejde provést (R96). Důvod je
 * kód, text pro uživatele je v lang/cs/app.php (ui.auth.social.refused.<kód>).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Social;

use RuntimeException;
use Throwable;

final class SocialLoginRefused extends RuntimeException
{
    /** Poskytovatel neposlal e-mail (Facebook účet založený přes telefon). */
    public const EMAIL_MISSING = 'email_missing';

    /** Účet s e-mailem existuje a poskytovatel e-mail neověřil — připojit ho sám nesmí. */
    public const EMAIL_TAKEN = 'email_taken';

    /** Účet u poskytovatele už je propojený s jiným uživatelem. */
    public const ALREADY_LINKED_ELSEWHERE = 'already_linked_elsewhere';

    /** Uživatel už má propojený jiný účet stejného poskytovatele. */
    public const OTHER_ACCOUNT_LINKED = 'other_account_linked';

    /** Odpojení by zablokovalo přihlášení — účet nemá heslo ani jiný propojený účet. */
    public const LAST_LOGIN_METHOD = 'last_login_method';

    /** Potvrzení proběhlo jiným účtem u poskytovatele, než který je propojený. */
    public const CONFIRMATION_MISMATCH = 'confirmation_mismatch';

    /** Uživatel přihlášení u poskytovatele zrušil nebo nepovolil přístup. */
    public const CANCELLED = 'cancelled';

    /** Přihlášení u poskytovatele se nepovedlo (návrat otevřený podruhé, vypršelá relace, chyba spojení). */
    public const FAILED = 'failed';

    /**
     * @param  string  $reason  Kód důvodu (konstanty třídy)
     */
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Přihlášení přes poskytovatele odmítnuto: '.$reason, previous: $previous);
    }

    /**
     * Text pro uživatele.
     */
    public function userMessage(): string
    {
        return __('app.ui.auth.social.refused.'.$this->reason);
    }
}
