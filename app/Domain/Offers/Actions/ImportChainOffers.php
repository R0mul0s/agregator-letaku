<?php

/**
 * Stáhne akční nabídku obchodu a uloží ji — zdroje (leaflets) a nabídky (offers).
 *
 * Každé stažení má záznam v scrape_runs. Nula nabídek je chyba zdroje, ne „žádné akce“
 * (CODING_GUIDELINES, sekce 3). Nabídky se nemažou (R10): opakované stažení stejnou
 * nabídku podle obchodu, ID položky a platnosti jen aktualizuje. Neskončená nabídka,
 * která v novém stažení chybí, se označí jako stažená obchodem (R16).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Exceptions\SourceReturnedNoOffers;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportChainOffers
{
    /** Počet řádků v jednom hromadném zápisu. */
    private const UPSERT_CHUNK = 500;

    /** Sloupce unikátního klíče nabídky. */
    private const UNIQUE_BY = ['chain', 'external_id', 'valid_from', 'valid_to'];

    /** Sloupce, které se při opakovaném stažení přepíší (vše kromě klíče a created_at). */
    private const UPDATED_COLUMNS = [
        'leaflet_id', 'scrape_run_id', 'withdrawn_at', 'store_format', 'name', 'brand', 'description',
        'variant_note', 'package_text', 'quantity', 'unit', 'price', 'original_price', 'loyalty_price',
        'loyalty_program', 'discount_percent', 'offer_type', 'promotion_text', 'online_only',
        'source_category', 'image_url', 'source_url', 'raw', 'updated_at',
    ];

    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Stáhne a uloží nabídku obchodu; vrátí záznam o stažení. Chyba se zapíše a vyhodí dál.
     *
     * @throws Throwable
     */
    public function __invoke(Chain $chain): ScrapeRun
    {
        $run = ScrapeRun::start($chain);

        try {
            $batches = $this->sources->offers($chain)->fetch();

            [$offersCount, $withdrawnCount] = DB::transaction(function () use ($chain, $batches, $run): array {
                $stored = [];
                foreach ($batches as $batch) {
                    $stored += $this->storeBatch($chain, $batch, $run, array_keys($stored));
                }

                if ($stored === []) {
                    throw SourceReturnedNoOffers::for($chain);
                }

                return [count($stored), $this->markWithdrawn($chain, $run)];
            });

            $run->succeed($offersCount, $withdrawnCount);
        } catch (Throwable $error) {
            $run->fail($error);

            throw $error;
        }

        return $run;
    }

    /**
     * Uloží zdroj a jeho nabídky; nabídku, kterou už uložila dřívější dávka, přeskočí.
     *
     * @param  list<string>  $alreadyStored  Klíče nabídek z dřívějších dávek
     * @return array<string, true> Klíče uložených nabídek
     */
    private function storeBatch(Chain $chain, SourceBatch $batch, ScrapeRun $run, array $alreadyStored): array
    {
        $leaflet = $this->storeLeaflet($chain, $batch->leaflet);
        $skip = array_fill_keys($alreadyStored, true);

        $rows = [];
        foreach ($batch->offers as $offer) {
            if (! isset($skip[$offer->key()])) {
                $rows[$offer->key()] ??= $this->row($chain, $leaflet, $run, $offer);
            }
        }

        foreach (array_chunk(array_values($rows), self::UPSERT_CHUNK) as $chunk) {
            Offer::query()->upsert($chunk, self::UNIQUE_BY, self::UPDATED_COLUMNS);
        }

        return array_fill_keys(array_keys($rows), true);
    }

    /**
     * Neskončené nabídky obchodu, které v tomto stažení chyběly, označí jako stažené
     * obchodem (R16). Když se později znovu objeví, upsert označení zruší.
     */
    private function markWithdrawn(Chain $chain, ScrapeRun $run): int
    {
        return Offer::query()
            ->where('chain', $chain)
            ->where('scrape_run_id', '!=', $run->id)
            ->whereNull('withdrawn_at')
            ->notExpired($this->calendar->today())
            ->update(['withdrawn_at' => CarbonImmutable::now()]);
    }

    /**
     * Založí nebo aktualizuje zdroj nabídek.
     */
    private function storeLeaflet(Chain $chain, LeafletData $data): Leaflet
    {
        return Leaflet::query()->updateOrCreate(
            ['chain' => $chain, 'kind' => $data->kind, 'external_id' => $data->externalId],
            [
                'title' => $data->title,
                'format' => $data->format,
                'valid_from' => $data->validFrom,
                'valid_to' => $data->validTo,
                'source_url' => $data->sourceUrl,
                'fetched_at' => CarbonImmutable::now(),
            ],
        );
    }

    /**
     * Řádek tabulky offers — hromadný zápis obchází přetypování modelu, hodnoty jsou syrové.
     *
     * @return array<string, mixed>
     */
    private function row(Chain $chain, Leaflet $leaflet, ScrapeRun $run, OfferData $offer): array
    {
        $now = CarbonImmutable::now();

        return [
            'chain' => $chain->value,
            'leaflet_id' => $leaflet->id,
            'scrape_run_id' => $run->id,
            'withdrawn_at' => null,
            'store_format' => $offer->storeFormat?->value,
            'external_id' => $offer->externalId,
            'name' => $offer->name,
            'brand' => $offer->brand,
            'description' => $offer->description,
            'variant_note' => $offer->variantNote,
            'package_text' => $offer->packageText,
            'quantity' => $offer->package?->quantity,
            'unit' => $offer->package?->unit->value,
            'price' => $offer->price,
            'original_price' => $offer->originalPrice,
            'loyalty_price' => $offer->loyaltyPrice,
            'loyalty_program' => $offer->loyaltyProgram?->value,
            'discount_percent' => $offer->discountPercent,
            'offer_type' => $offer->offerType->value,
            'promotion_text' => $offer->promotionText,
            'online_only' => $offer->onlineOnly,
            'valid_from' => $offer->validFrom->toDateString(),
            'valid_to' => $offer->validTo->toDateString(),
            'source_category' => $offer->sourceCategory,
            'image_url' => $offer->imageUrl,
            'source_url' => $offer->sourceUrl,
            'raw' => json_encode($offer->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
