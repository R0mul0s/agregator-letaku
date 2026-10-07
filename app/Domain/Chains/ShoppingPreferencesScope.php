<?php

/**
 * Nabídky podle nastavení Mých obchodů (R19, R49, R100): typ prodejny, akce jen z e-shopu,
 * vybrané prodejny a věrnostní karty. Moje slevy berou jen sledované obchody (whereFollowed),
 * Všechny akce („Podle Mých obchodů“) nastavení jen uplatní — obchod, který uživatel nesleduje,
 * ale ve výpisu si ho vybral, zůstane celý (apply).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Chains;

use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ShoppingPreferencesScope
{
    /**
     * Omezí nabídky nastavením uživatele: u sledovaných obchodů typ prodejny, e-shop a prodejny,
     * u všech bez akcí jen s kartou, kterou nemá. Ostatní podmínky dotazu (obchody) nemění.
     *
     * @param  Builder<Offer>  $query
     */
    public function apply(Builder $query, User $user): void
    {
        $followed = $user->followedChains()->get();

        $query->where(function (Builder $query) use ($followed): void {
            foreach ($followed as $chain) {
                $query->orWhere(fn (Builder $query) => $this->whereFollowed($query, $chain));
            }
            $query->orWhereNotIn('chain', $followed->map(fn (FollowedChain $chain): string => $chain->chain->value)->all());
        });
        $this->whereAvailableTo($query, $user);
    }

    /**
     * Nabídky jednoho sledovaného obchodu: typ prodejny (nabídka bez typu platí všude),
     * akce jen z e-shopu a vybrané prodejny (R49; akce bez prodejen platí všude) podle volby uživatele.
     *
     * @param  Builder<Offer>  $query
     */
    public function whereFollowed(Builder $query, FollowedChain $chain): void
    {
        $query->where('chain', $chain->chain);

        if ($chain->store_format !== null) {
            $query->where(fn (Builder $query) => $query->whereNull('store_format')->orWhere('store_format', $chain->store_format));
        }

        if (! $chain->include_online_only) {
            $query->where('online_only', false);
        }

        if ($chain->store_codes !== null && $chain->store_codes !== []) {
            $query->availableInStores($chain->store_codes);
        }
    }

    /**
     * Bez akcí jen s kartou, kterou uživatel nemá — bez karty by platil běžnou cenu (R19).
     * Stejné pravidlo jako MyOffers::isAvailableTo.
     *
     * @param  Builder<Offer>  $query
     */
    private function whereAvailableTo(Builder $query, User $user): void
    {
        $programs = $user->loyalty_programs?->map(fn (LoyaltyProgram $program): string => $program->value)->values()->all() ?? [];

        $query->where(fn (Builder $query) => $query
            ->where('offer_type', '!=', OfferType::LoyaltyOnly)
            ->when($programs !== [], fn (Builder $query) => $query->orWhereIn('loyalty_program', $programs)));
    }
}
