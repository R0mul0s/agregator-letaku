<?php

/**
 * Prodejny k výběru v okně Filtry Všech akcí (R164, R169) — načtou se až po otevření okna. Se
 * ~1 450 prodejnami COOP by seznam prodejen tvořil přes třetinu každého načtení stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use Illuminate\Http\JsonResponse;

class OfferStoreOptionsController extends Controller
{
    /**
     * Prodejny obchodů, jejichž akce se liší po prodejnách: obchod => [{ code, name, city, note? }].
     */
    public function __invoke(ChainCatalog $catalog): JsonResponse
    {
        $stores = [];
        foreach (Chain::enabled() as $chain) {
            $chainStores = $catalog->stores($chain);
            if ($chainStores !== []) {
                $stores[$chain->value] = $chainStores;
            }
        }

        return response()->json(['stores' => $stores]);
    }
}
