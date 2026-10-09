<?php

/**
 * Registrace nového uživatele (Fortify). Přijetí podmínek je povinné a ukládá se s verzí,
 * souhlas s obchodními sděleními je dobrovolný (R51). Odkaz na ověření e-mailu pošle
 * Laravel po události Registered (User implementuje MustVerifyEmail).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Domain\Account\Actions\SetUpNewAccount;
use App\Domain\Account\RegistrationGuard;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly RegistrationGuard $guard,
        private readonly SetUpNewAccount $setUp,
    ) {}

    /**
     * Ověří údaje z registračního formuláře a založí uživatele. Odeslání, které nevypadá
     * jako od člověka (R53), skončí obecnou chybou ještě před validací polí.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! $this->guard->passes($input)) {
            throw ValidationException::withMessages([RegistrationGuard::TOKEN_FIELD => __('app.ui.auth.register.bot_check')]);
        }

        $data = Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->registrationPasswordRules(),
            'terms' => ['accepted'],
            'marketing' => ['boolean'],
        ], ['terms.accepted' => __('app.ui.auth.register.terms_required')])->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $this->setUp->handle($user, (bool) ($data['marketing'] ?? false));

        return $user;
    }
}
