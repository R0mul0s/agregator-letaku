<?php

/**
 * Proč uživatel odešel k poskytovateli přihlášení (R96) — podle toho návrat
 * (SocialLoginController::callback) přihlásí, propojí účet, nebo potvrdí totožnost.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Social;

enum SocialIntent: string
{
    // Přihlášení nebo registrace nepřihlášeného
    case Login = 'login';
    // Propojení s přihlášeným účtem (Můj účet)
    case Link = 'link';
    // Potvrzení totožnosti účtu bez hesla před citlivou změnou (IdentityConfirmation)
    case Confirm = 'confirm';
}
