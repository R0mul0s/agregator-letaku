<?php

/**
 * Dokončení registrace přes Google nebo Facebook (R96): jméno upravitelné, e-mail od
 * poskytovatele, souhlas s podmínkami povinný a s obchodními sděleními dobrovolný (R51).
 * Účet nemá heslo. E-mail ověřený u Googlu je ověřený i u nás, jinak přijde odkaz
 * (událost Registered) jako u registrace heslem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Actions\Fortify\ProfileValidationRules;
use App\Domain\Account\Social\SocialIdentity;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RegisterSocialUser
{
    use ProfileValidationRules;

    public function __construct(private readonly SetUpNewAccount $setUp) {}

    /**
     * Ověří formulář dokončení registrace a založí účet propojený s poskytovatelem.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws SocialLoginRefused
     */
    public function handle(SocialIdentity $identity, array $input): User
    {
        if ($identity->email === null) {
            throw new SocialLoginRefused(SocialLoginRefused::EMAIL_MISSING);
        }

        $data = Validator::make([...$input, 'email' => $identity->email], [
            'name' => $this->nameRules(),
            // Mezitím mohl účet s adresou vzniknout jinak (registrace heslem v jiné kartě)
            'email' => [Rule::unique(User::class)],
            'terms' => ['accepted'],
            'marketing' => ['boolean'],
        ], [
            'terms.accepted' => __('app.ui.auth.register.terms_required'),
            'email.unique' => __('app.ui.auth.social.refused.'.SocialLoginRefused::EMAIL_TAKEN),
        ])->validate();

        $user = DB::transaction(function () use ($identity, $data): User {
            $user = User::create(['name' => $data['name'], 'email' => $identity->email, 'password' => null]);
            if ($identity->emailVerified) {
                $user->markEmailAsVerified();
            }
            $user->socialAccounts()->create(['provider' => $identity->provider, 'provider_user_id' => $identity->id]);
            $this->setUp->handle($user, (bool) ($data['marketing'] ?? false));

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
