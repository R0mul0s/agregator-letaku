<?php

/**
 * Přihlášení a odhlášení e-mailů uživatele (R51): souhlas s obchodními sděleními se ukládá
 * s časem a verzí textu (doložení souhlasu, § 7 zák. 480/2004 Sb.), odvolání s časem.
 * Odhlášení souhrnu akcí (R42) ho vypne. Volá se z registrace, účtu i odkazu v e-mailu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Enums\DigestFrequency;
use App\Enums\MailingList;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

final class MailingSubscriptions
{
    /**
     * Udělí nebo odvolá souhlas s obchodními sděleními. Opakované udělení ani odvolání
     * nepřepíše čas původního záznamu.
     */
    public function setMarketingConsent(User $user, bool $consent): void
    {
        if ($consent === $user->hasMarketingConsent()) {
            return;
        }

        $user->forceFill($consent
            ? [
                'marketing_consent_at' => CarbonImmutable::now(),
                'marketing_consent_version' => config()->integer('letaky.legal.marketing_consent_version'),
            ]
            : [
                'marketing_consent_at' => null,
                'marketing_consent_version' => null,
                'marketing_consent_withdrawn_at' => CarbonImmutable::now(),
            ])->save();
    }

    /**
     * Odhlásí uživatele z e-mailů (odkaz v e-mailu, hlavička List-Unsubscribe).
     */
    public function unsubscribe(User $user, MailingList $list): void
    {
        match ($list) {
            MailingList::Digest => $user->forceFill(['digest_frequency' => DigestFrequency::Off])->save(),
            MailingList::Marketing => $this->setMarketingConsent($user, false),
        };
    }

    /**
     * Je uživatel k e-mailům přihlášený?
     */
    public function isSubscribed(User $user, MailingList $list): bool
    {
        return match ($list) {
            MailingList::Digest => $user->digest_frequency !== DigestFrequency::Off,
            MailingList::Marketing => $user->hasMarketingConsent(),
        };
    }

    /**
     * Trvalá podepsaná adresa odhlášení — funguje bez přihlášení i ze starého e-mailu,
     * podpis brání odhlášení cizího účtu.
     */
    public function unsubscribeUrl(User $user, MailingList $list): string
    {
        return URL::signedRoute('unsubscribe', ['user' => $user->id, 'list' => $list->value]);
    }
}
