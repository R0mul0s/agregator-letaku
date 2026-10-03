<?php

/**
 * Uloží, které obchody uživatel sleduje, s upřesněním a jeho věrnostní karty (R19, R21).
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
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateShoppingPreferences
{
    /**
     * Nahradí nastavení uživatele.
     *
     * @param  list<FollowedChainData>  $chains
     * @param  list<LoyaltyProgram>  $loyaltyPrograms
     */
    public function __invoke(User $user, array $chains, array $loyaltyPrograms): void
    {
        DB::transaction(function () use ($user, $chains, $loyaltyPrograms): void {
            $followed = array_map(fn (FollowedChainData $data): Chain => $data->chain, $chains);

            $user->followedChains()->whereNotIn('chain', $followed)->delete();
            foreach ($chains as $data) {
                $user->followedChains()->updateOrCreate(['chain' => $data->chain], [
                    'store_format' => $data->storeFormat,
                    'include_online_only' => $data->includeOnlineOnly,
                    'store_codes' => $data->storeCodes === [] ? null : $data->storeCodes,
                ]);
            }

            $user->update(['loyalty_programs' => array_values(array_unique($loyaltyPrograms, SORT_REGULAR))]);
        });
    }
}
