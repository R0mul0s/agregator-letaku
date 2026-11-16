<?php

/**
 * Nastavení nového hesla po obnově odkazem z e-mailu (Fortify).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Domain\Account\UserSessions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly UserSessions $sessions) {}

    /**
     * Ověří a uloží nové heslo zapomenutého účtu a odhlásí všechna zařízení i s upozorněními
     * v telefonu (R67, R185) — kdo si heslo obnovuje, mohl o účet přijít. Token „Zapamatovat
     * si mě“ změní Fortify sám. Odkaz z e-mailu prokazuje vlastnictví adresy: neověřený účet
     * mohl předem založit někdo jiný, proto se e-mail označí jako ověřený a propojené účty
     * u poskytovatelů i odkaz na sdílený nákupní seznam zmizí (R170, R185).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            if (! $user->hasVerifiedEmail()) {
                $user->socialAccounts()->delete();
                $user->forceFill([
                    'email_verified_at' => $user->freshTimestamp(),
                    'shopping_share_token' => null,
                ]);
            }
            $user->forceFill([
                'password' => Hash::make($input['password']),
            ])->save();
        });

        $this->sessions->logoutEverywhere($user);
    }
}
