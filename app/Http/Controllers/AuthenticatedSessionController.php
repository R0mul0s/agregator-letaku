<?php

/**
 * Přihlášení a odhlášení Fortify s odhlášením jen tohoto zařízení (R151).
 *
 * Fortify odhlašuje přes `logout()`, který vymění token „Zapamatovat si mě“ v `users.remember_token`.
 * Token je pro uživatele jeden, takže odhlášení na počítači zrušilo zapamatované přihlášení i v telefonu
 * a ten uživatele po vypršení relace (`session.lifetime`) odhlásil. Odhlásit všechna zařízení jde
 * v Mém účtu (`UserSessions::logoutOthers`) a změnou hesla.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-16
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

final class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    /**
     * Odhlásí uživatele jen na tomto zařízení; zapamatované přihlášení ostatních zařízení zůstane.
     */
    public function destroy(Request $request): LogoutResponse
    {
        $this->guard->logoutCurrentDevice();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return app(LogoutResponse::class);
    }
}
