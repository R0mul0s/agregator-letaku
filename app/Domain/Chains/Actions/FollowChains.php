<?php

/**
 * Sledované obchody bez upřesnění (R55, R153): nový účet a průvodce prvním nastavením.
 * Obchod, který uživatel sleduje dál, si upřesnění (typ prodejny, prodejny, e-shop) nechá,
 * nově zapnutý sleduje celou nabídku. Upřesnění mění Moje obchody (UpdateShoppingPreferences).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Domain\Chains\Actions;

use App\Enums\Chain;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FollowChains
{
    /**
     * Uživatel bude sledovat právě tyto obchody.
     *
     * @param  list<Chain>  $chains
     */
    public function __invoke(User $user, array $chains): void
    {
        DB::transaction(function () use ($user, $chains): void {
            $user->followedChains()->whereNotIn('chain', array_map(fn (Chain $chain): string => $chain->value, $chains))->delete();
            foreach ($chains as $chain) {
                $user->followedChains()->firstOrCreate(['chain' => $chain], ['store_format' => null, 'include_online_only' => true]);
            }
        });
    }
}
