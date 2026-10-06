<?php

/**
 * Potvrzení totožnosti před citlivou změnou účtu (R54, R96): změna e-mailu a hesla,
 * odhlášení ostatních zařízení, zrušení účtu. Účet s heslem ho zadá, účet bez hesla
 * (založený přes Google nebo Facebook) se krátce předtím znovu přihlásí u poskytovatele —
 * kdo by ukradl relaci, nemá ani heslo, ani přihlášení u poskytovatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Account\Social\SocialIdentity;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Models\User;
use App\Rules\ConfirmedIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;

final class IdentityConfirmation
{
    /** Klíč relace s časem potvrzení u poskytovatele (unix čas). */
    private const SESSION_KEY = 'auth.social_confirmed_at';

    /**
     * Zapíše potvrzení u poskytovatele (návrat SocialIntent::Confirm) — jen když se uživatel
     * přihlásil účtem, který má propojený.
     *
     * @throws SocialLoginRefused
     */
    public function confirmWith(User $user, SocialIdentity $identity, Session $session): void
    {
        $linked = $user->socialAccounts()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->id)
            ->exists();
        if (! $linked) {
            throw new SocialLoginRefused(SocialLoginRefused::CONFIRMATION_MISMATCH);
        }

        $session->put(self::SESSION_KEY, CarbonImmutable::now()->getTimestamp());
    }

    /**
     * Potvrdil se uživatel u poskytovatele před méně než letaky.auth.social.confirmation_minutes?
     */
    public function isFresh(Session $session): bool
    {
        $confirmedAt = $session->get(self::SESSION_KEY);
        $validSince = CarbonImmutable::now()->subMinutes(config()->integer('letaky.auth.social.confirmation_minutes'))->getTimestamp();

        return is_int($confirmedAt) && $confirmedAt >= $validSince;
    }

    /**
     * Pravidla validace pole s heslem pro potvrzení: s heslem současné heslo, bez hesla
     * čerstvé potvrzení u poskytovatele (pole zůstane prázdné).
     *
     * @return list<mixed>
     */
    public function rules(User $user): array
    {
        return $user->hasPassword()
            ? ['required', 'string', 'current_password:web']
            : [new ConfirmedIdentity($this)];
    }
}
