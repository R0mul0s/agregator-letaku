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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly UserSessions $sessions) {}

    /**
     * Ověří a uloží nové heslo zapomenutého účtu a odhlásí všechna zařízení (R67) — kdo
     * si heslo obnovuje, mohl o účet přijít. Token „Zapamatovat si mě“ změní Fortify sám.
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

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $this->sessions->deleteAll($user);
    }
}
