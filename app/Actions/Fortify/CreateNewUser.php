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

use App\Domain\Account\MailingSubscriptions;
use App\Domain\Account\RegistrationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly MailingSubscriptions $subscriptions,
        private readonly RegistrationGuard $guard,
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'marketing' => ['boolean'],
        ], ['terms.accepted' => __('app.ui.auth.register.terms_required')])->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill([
            'terms_accepted_at' => CarbonImmutable::now(),
            'terms_version' => config()->integer('letaky.legal.terms_version'),
        ])->save();
        $this->subscriptions->setMarketingConsent($user, (bool) ($data['marketing'] ?? false));

        return $user;
    }
}
