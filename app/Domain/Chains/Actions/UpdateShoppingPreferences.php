<?php

/**
 * Uloží, které obchody uživatel sleduje, s upřesněním, vybrané prodejny a věrnostní karty (R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Chains\Actions;

use App\Domain\Chains\FollowedChainData;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateShoppingPreferences
{
    /**
     * Nahradí nastavení uživatele. Prodejny obchodů, které uživatel přestal sledovat, se odeberou.
     *
     * @param  list<FollowedChainData>  $chains
     * @param  list<int>  $storeIds
     * @param  list<LoyaltyProgram>  $loyaltyPrograms
     */
    public function __invoke(User $user, array $chains, array $storeIds, array $loyaltyPrograms): void
    {
        DB::transaction(function () use ($user, $chains, $storeIds, $loyaltyPrograms): void {
            $followed = array_map(fn (FollowedChainData $data): Chain => $data->chain, $chains);

            $user->followedChains()->whereNotIn('chain', $followed)->delete();
            foreach ($chains as $data) {
                $user->followedChains()->updateOrCreate(['chain' => $data->chain], [
                    'store_format' => $data->storeFormat,
                    'include_online_only' => $data->includeOnlineOnly,
                ]);
            }

            $user->stores()->sync(
                Store::query()->whereKey($storeIds)->whereIn('chain', $followed)->pluck('id')->all(),
            );

            $user->update(['loyalty_programs' => array_values(array_unique($loyaltyPrograms, SORT_REGULAR))]);
        });
    }
}
