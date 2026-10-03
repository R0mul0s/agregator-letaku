<?php

/**
 * Stáhne akční nabídku obchodu a uloží ji — zdroje (leaflets) a nabídky (offers).
 *
 * Každé stažení má záznam v scrape_runs. Nula nabídek i stránek je chyba zdroje, ne „žádné akce“
 * (CODING_GUIDELINES, sekce 3). Nabídky se nemažou (R10): opakované stažení stejnou
 * nabídku podle obchodu, ID položky a platnosti jen aktualizuje. Neskončená nabídka,
 * která v novém stažení chybí, se označí jako stažená obchodem (R16). Nakonec se nabídky
 * obchodu znovu přiřadí k produktům katalogu (R30).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\LeafletPageData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Exceptions\SourceReturnedNoOffers;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferStore;
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
        private readonly AssignProducts $assignProducts,
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

                // Zdroj jen se zmínkami (Albert, R36) nabídky nemá — chyba je až prázdno ve všem
                if ($stored === [] && ! array_any($batches, fn (SourceBatch $batch): bool => $batch->pages !== [])) {
                    throw SourceReturnedNoOffers::for($chain);
                }

                $this->storeAvailability($run, $stored);
                $withdrawn = $this->markWithdrawn($chain, $run);
                $this->assignProducts->forChain($chain);

                return [count($stored), $withdrawn];
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
     * @return array<string, list<string>|null> Klíče uložených nabídek => prodejny, kde platí (R49)
     */
    private function storeBatch(Chain $chain, SourceBatch $batch, ScrapeRun $run, array $alreadyStored): array
    {
        $leaflet = $this->storeLeaflet($chain, $batch->leaflet);
        $skip = array_fill_keys($alreadyStored, true);

        $rows = [];
        $storeCodes = [];
        foreach ($batch->offers as $offer) {
            if (! isset($skip[$offer->key()]) && ! isset($rows[$offer->key()])) {
                $rows[$offer->key()] = $this->row($chain, $leaflet, $run, $offer);
                $storeCodes[$offer->key()] = $offer->storeCodes;
            }
        }

        foreach (array_chunk(array_values($rows), self::UPSERT_CHUNK) as $chunk) {
            Offer::query()->upsert($chunk, self::UNIQUE_BY, self::UPDATED_COLUMNS);
        }

        $this->storePages($leaflet, $batch->pages);

        return $storeCodes;
    }

    /**
     * Prodejny, ve kterých nabídky z tohoto stažení platí (R49): předchozí stav se nahradí,
     * řádky dostanou jen nabídky, které neplatí všude.
     *
     * @param  array<string, list<string>|null>  $stored  Klíč nabídky => prodejny, null = všude
     */
    private function storeAvailability(ScrapeRun $run, array $stored): void
    {
        OfferStore::query()->whereIn('offer_id', Offer::query()->select('id')->where('scrape_run_id', $run->id))->delete();

        $restricted = array_filter($stored, fn (?array $codes): bool => $codes !== null);
        if ($restricted === []) {
            return;
        }

        $rows = [];
        foreach (Offer::query()->where('scrape_run_id', $run->id)->get(['id', 'external_id', 'valid_from', 'valid_to']) as $offer) {
            $key = $offer->external_id.'|'.$offer->valid_from->toDateString().'|'.$offer->valid_to->toDateString();
            foreach ($restricted[$key] ?? [] as $code) {
                $rows[] = ['offer_id' => $offer->id, 'store_code' => $code];
            }
        }

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            OfferStore::query()->insert($chunk);
        }
    }

    /**
     * Uloží text stránek letáku pro zmínky bez ceny (R27); stránka se stejným číslem se přepíše.
     *
     * @param  list<LeafletPageData>  $pages
     */
    private function storePages(Leaflet $leaflet, array $pages): void
    {
        $now = CarbonImmutable::now();
        $rows = array_map(fn (LeafletPageData $page): array => [
            'leaflet_id' => $leaflet->id,
            'number' => $page->number,
            'text' => $page->text,
            'image_url' => $page->imageUrl,
            'page_url' => $page->pageUrl,
            'created_at' => $now,
            'updated_at' => $now,
        ], $pages);

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            LeafletPage::query()->upsert($chunk, ['leaflet_id', 'number'], ['text', 'image_url', 'page_url', 'updated_at']);
        }
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
