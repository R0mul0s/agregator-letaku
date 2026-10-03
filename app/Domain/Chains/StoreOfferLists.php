<?php

/**
 * Seznamy akcí prodejen uložené ImportStores (R49) — pro import nabídek: které akce chybí
 * ve výchozí nabídce a v kterých prodejnách akce platí. Bere jen seznamy mladší než
 * `store_offers_max_age_hours` — starý seznam by akci „schoval“ v prodejně, kde už platí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Chains;

use App\Enums\Chain;
use App\Models\Store;
use Carbon\CarbonImmutable;

final class StoreOfferLists
{
    /**
     * Akce podle prodejny: kód prodejny => [klíč nabídky => true].
     *
     * @return array<string, array<string, true>>
     */
    public function fresh(Chain $chain): array
    {
        $since = CarbonImmutable::now()->subHours(config()->integer("letaky.sources.{$chain->value}.store_offers_max_age_hours"));

        $lists = [];
        foreach (Store::query()->where('chain', $chain)->where('offer_keys_fetched_at', '>=', $since)->get(['code', 'offer_keys']) as $store) {
            $lists[$store->code] = array_fill_keys($store->offer_keys ?? [], true);
        }

        return $lists;
    }
}
