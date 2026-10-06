<?php

/**
 * Propojení přihlášeného účtu s Googlem nebo Facebookem v Mém účtu (R96). Účet
 * u poskytovatele smí patřit jen jednomu uživateli a uživatel má u poskytovatele nejvýš
 * jeden propojený účet.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Account\Social\SocialIdentity;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Models\SocialAccount;
use App\Models\User;

final class LinkSocialAccount
{
    /**
     * Propojí účet; už propojený stejný účet nechá být.
     *
     * @throws SocialLoginRefused
     */
    public function handle(User $user, SocialIdentity $identity): void
    {
        $owner = SocialAccount::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->id)
            ->value('user_id');
        if ($owner === $user->id) {
            return;
        }
        if ($owner !== null) {
            throw new SocialLoginRefused(SocialLoginRefused::ALREADY_LINKED_ELSEWHERE);
        }
        if ($user->socialAccounts()->where('provider', $identity->provider)->exists()) {
            throw new SocialLoginRefused(SocialLoginRefused::OTHER_ACCOUNT_LINKED);
        }

        $user->socialAccounts()->create(['provider' => $identity->provider, 'provider_user_id' => $identity->id]);
    }
}
