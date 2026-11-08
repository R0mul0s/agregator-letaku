<?php

/**
 * Akce, které se u žádného obchodu neukládají (R181) — erotické pomůcky (vibrátory, masturbátory).
 * Vysoké slevy by je dostaly do Nejlepších slev týdne a ukázky na úvodní stránce. Kondomy,
 * lubrikanty a intimní gely zůstávají jako běžná drogerie. Vzor názvu je v konfiguraci
 * (`letaky.import.excluded_name_pattern`), platí pro všechny obchody i budoucí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

namespace App\Domain\Offers\Import;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;

final class ExcludedOffers
{
    /**
     * Dávky bez vyloučených akcí; leták, stránky a statistika dlaždic zůstanou beze změny.
     *
     * @param  list<SourceBatch>  $batches
     * @return list<SourceBatch>
     */
    public function filter(array $batches): array
    {
        return array_map(fn (SourceBatch $batch): SourceBatch => new SourceBatch(
            leaflet: $batch->leaflet,
            offers: array_values(array_filter($batch->offers, fn (OfferData $offer): bool => ! $this->isExcluded($offer->name))),
            pages: $batch->pages,
            tiles: $batch->tiles,
            coveredOffers: $batch->coveredOffers,
        ), $batches);
    }

    /**
     * Patří akce s tímto názvem mezi vyloučené?
     */
    public function isExcluded(string $name): bool
    {
        $pattern = config('letaky.import.excluded_name_pattern');

        return is_string($pattern) && $pattern !== '' && preg_match($pattern, $name) === 1;
    }
}
