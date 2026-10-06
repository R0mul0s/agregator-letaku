<?php

/**
 * Výchozí stav nového účtu — stejný pro registraci heslem (CreateNewUser) i přes Google
 * nebo Facebook (RegisterSocialUser, R96): přijetí podmínek s verzí, souhlas s obchodními
 * sděleními (R51) a sledování všech obchodů (R55).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Account\MailingSubscriptions;
use App\Domain\Chains\ChainCatalog;
use App\Models\User;
use Carbon\CarbonImmutable;

final class SetUpNewAccount
{
    public function __construct(
        private readonly MailingSubscriptions $subscriptions,
        private readonly ChainCatalog $chains,
    ) {}

    /**
     * Zapíše souhlasy a začne sledovat obchody.
     *
     * @param  bool  $marketing  Souhlas s obchodními sděleními (dobrovolný, R51)
     */
    public function handle(User $user, bool $marketing): void
    {
        $user->forceFill([
            'terms_accepted_at' => CarbonImmutable::now(),
            'terms_version' => config()->integer('letaky.legal.terms_version'),
        ])->save();
        $this->subscriptions->setMarketingConsent($user, $marketing);

        // Nový účet sleduje všechny obchody (R55) — k první užitečné informaci stačí přidat
        // hlídanou položku; upřesnění (typ prodejny, prodejny, karty) přijde až podle potřeby
        foreach ($this->chains->available() as $chain) {
            $user->followedChains()->create(['chain' => $chain, 'store_format' => null, 'include_online_only' => true]);
        }
    }
}
