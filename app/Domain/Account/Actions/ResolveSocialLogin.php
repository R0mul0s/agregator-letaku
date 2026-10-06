<?php

/**
 * Ke komu patří přihlášení přes Google nebo Facebook (R96). Nejdřív podle propojeného účtu
 * (ID u poskytovatele), pak podle e-mailu — ale k existujícímu účtu se připojí jen e-mail,
 * za který poskytovatel ručí (Google). Bez účtu vrátí null a uživatel dokončí registraci.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Account\Social\SocialIdentity;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Domain\Account\UserSessions;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResolveSocialLogin
{
    public function __construct(private readonly UserSessions $sessions) {}

    /**
     * Uživatel, kterého přihlásit, nebo null, když účet ještě nemá.
     *
     * @throws SocialLoginRefused
     */
    public function handle(SocialIdentity $identity): ?User
    {
        $account = SocialAccount::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->id)
            ->with('user')
            ->first();
        if ($account !== null) {
            return $account->user;
        }

        if ($identity->email === null) {
            throw new SocialLoginRefused(SocialLoginRefused::EMAIL_MISSING);
        }

        $user = User::query()->where('email', $identity->email)->first();
        if ($user === null) {
            return null;
        }

        // Neověřený e-mail od poskytovatele by dovolil převzít cizí účet: stačilo by
        // u poskytovatele uvést cizí adresu. Propojit jde po přihlášení heslem v Mém účtu.
        if (! $identity->emailVerified) {
            throw new SocialLoginRefused(SocialLoginRefused::EMAIL_TAKEN);
        }
        if ($user->socialAccounts()->where('provider', $identity->provider)->exists()) {
            throw new SocialLoginRefused(SocialLoginRefused::OTHER_ACCOUNT_LINKED);
        }

        DB::transaction(function () use ($user, $identity): void {
            if (! $user->hasVerifiedEmail()) {
                $this->takeOverUnverified($user);
            }
            $user->socialAccounts()->create(['provider' => $identity->provider, 'provider_user_id' => $identity->id]);
        });

        return $user;
    }

    /**
     * Účet s neověřeným e-mailem mohl založit kdokoli, kdo adresu znal (předem obsazený účet).
     * Majitel adresy ji teď prokázal u Googlu — heslo zakladatele se zahodí a jeho přihlášení
     * se zruší; kdo heslo chce, nastaví si ho obnovou hesla nebo v Mém účtu.
     */
    private function takeOverUnverified(User $user): void
    {
        $this->sessions->deleteAll($user);
        $user->forceFill([
            'password' => null,
            'email_verified_at' => $user->freshTimestamp(),
            'remember_token' => Str::random(60),
        ])->save();
    }
}
