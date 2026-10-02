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
     * Ověří a uloží jméno a e-mail. Ověření e-mailu aplikace nepoužívá (config/fortify.php).
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

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
        ])->save();
    }
}
