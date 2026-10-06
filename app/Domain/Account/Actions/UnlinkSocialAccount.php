<?php

/**
 * Odpojení Googlu nebo Facebooku od účtu v Mém účtu (R96). Poslední způsob přihlášení
 * odpojit nejde — účet bez hesla by se už nepřihlásil (zbývala by jen obnova hesla).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Account\Social\SocialLoginRefused;
use App\Enums\SocialProvider;
use App\Models\User;

final class UnlinkSocialAccount
{
    /**
     * Odpojí účet poskytovatele; nepropojený nechá být.
     *
     * @throws SocialLoginRefused
     */
    public function handle(User $user, SocialProvider $provider): void
    {
        $account = $user->socialAccounts()->where('provider', $provider)->first();
        if ($account === null) {
            return;
        }
        if (! $user->hasPassword() && $user->socialAccounts()->count() <= 1) {
            throw new SocialLoginRefused(SocialLoginRefused::LAST_LOGIN_METHOD);
        }

        $account->delete();
    }
}
