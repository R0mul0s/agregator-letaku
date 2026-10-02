<?php

/**
 * Stránka účtu — jméno, e-mail a změna hesla. Ukládá je Fortify (R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Zobrazí formuláře účtu; názvy sad chyb musí sedět s akcemi Fortify.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Account', [
            'urls' => [
                'profile' => route('user-profile-information.update', absolute: false),
                'password' => route('user-password.update', absolute: false),
            ],
            'errorBags' => [
                'profile' => UpdateUserProfileInformation::ERROR_BAG,
                'password' => UpdateUserPassword::ERROR_BAG,
            ],
        ]);
    }
}
