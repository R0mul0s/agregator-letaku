<?php

/**
 * Stáhne seznam prodejen obchodu a uloží ho — nové přidá, existující aktualizuje.
 *
 * Prodejny, které ze seznamu zmizí, se nemažou: uživatelé je můžou mít vybrané.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Chains\Actions;

use App\Domain\Sources\Data\StoreData;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

final class ImportChainStores
{
    public function __construct(private readonly SourceRegistry $sources) {}

    /**
     * Stáhne a uloží prodejny obchodu; vrátí jejich počet.
     */
    public function __invoke(Chain $chain): int
    {
        $stores = $this->sources->stores($chain)->fetch();

        DB::transaction(function () use ($chain, $stores): void {
            foreach ($stores as $store) {
                $this->store($chain, $store);
            }
        });

        return count($stores);
    }

    /**
     * Uloží jednu prodejnu.
     */
    private function store(Chain $chain, StoreData $data): void
    {
        Store::query()->updateOrCreate(
            ['chain' => $chain, 'external_id' => $data->externalId],
            [
                'name' => $data->name,
                'format' => $data->format,
                'city' => $data->city,
                'address' => $data->address,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
            ],
        );
    }
}
