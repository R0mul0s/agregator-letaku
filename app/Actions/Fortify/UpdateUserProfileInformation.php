<?php

/**
 * Změna jména a e-mailu přihlášeného uživatele (Fortify).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /** Pojmenovaná sada chyb — stránka účtu má dva formuláře a chyby se nesmí plést. */
    public const ERROR_BAG = 'updateProfileInformation';

    /**
     * Ověří a uloží jméno a e-mail. Nová adresa se musí znovu ověřit (R51) — do té doby
     * na ni nechodí souhrny, jinak by šlo posílat e-maily na cizí adresu.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ])->validateWithBag(self::ERROR_BAG);

        $emailChanged = mb_strtolower($input['email']) !== mb_strtolower($user->email);

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
    }
}
