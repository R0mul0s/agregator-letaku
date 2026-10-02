<?php

/**
 * Změna hesla přihlášeného uživatele (Fortify).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /** Pojmenovaná sada chyb — stránka účtu má dva formuláře a chyby se nesmí plést. */
    public const ERROR_BAG = 'updatePassword';

    /**
     * Ověří současné heslo a uloží nové.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ])->validateWithBag(self::ERROR_BAG);

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
