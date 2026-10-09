<?php

/**
 * Zápis dávky ze zdroje do databáze: zdroj nabídek (leták, web), akce hromadným upsertem,
 * prodejny, kde akce platí (R49), a text stránek letáku pro zmínky (R27). Hromadný zápis
 * obchází přetypování modelu — hodnoty jsou syrové (enumy `->value`, JSON, data `Y-m-d`).
 * Vyčleněno z ImportChainOffers (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers\Import;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\LeafletPageData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Parsing\WebUrl;
use App\Enums\Chain;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;

final readonly class BatchWriter
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

    public function __construct(private OfferContinuity $continuity) {}

    /**
     * Uloží zdroj a jeho nabídky; nabídku, kterou už uložila dřívější dávka, přeskočí.
     *
     * @param  list<string>  $alreadyStored  Klíče nabídek z dřívějších dávek
     * @return array<string, list<string>|null> Klíče uložených nabídek => prodejny, kde platí (R49)
     */
    public function store(Chain $chain, SourceBatch $batch, ScrapeRun $run, array $alreadyStored): array
    {
        $leaflet = $this->storeLeaflet($chain, $batch->leaflet);
        $skip = array_fill_keys($alreadyStored, true);

        $this->continuity->adoptProvisional($chain, $batch->offers);

        $rows = [];
        $storeCodes = [];
        foreach ($this->continuity->continuePrevious($chain, $batch->offers) as $offer) {
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
    public function storeAvailability(ScrapeRun $run, array $stored): void
    {
        OfferStore::query()->whereIn('offer_id', Offer::query()->select('id')->where('scrape_run_id', $run->id))->delete();

        $restricted = array_filter($stored, fn (?array $codes): bool => $codes !== null);
        if ($restricted === []) {
            return;
        }

        $rows = [];
        foreach (Offer::query()->where('scrape_run_id', $run->id)->get(['id', 'external_id', 'valid_from', 'valid_to']) as $offer) {
            foreach ($restricted[OfferData::keyOf($offer->external_id, $offer->valid_from, $offer->valid_to)] ?? [] as $code) {
                $rows[] = ['offer_id' => $offer->id, 'store_code' => $code];
            }
        }

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            OfferStore::query()->insert($chunk);
        }
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
                'source_url' => WebUrl::orNull($data->sourceUrl),
                'fetched_at' => CarbonImmutable::now(),
            ],
        );
    }

    /**
     * Uloží text stránek letáku pro zmínky bez ceny (R27); stránka se stejným číslem se přepíše
     * a stránka, kterou leták už nemá (kratší leták, stránka bez textu), se smaže — jinak by
     * z ní dál vznikaly zmínky (R113). Dávka bez stránek nechá uložené být (zdroj je tentokrát
     * nenese, třeba leták bez textové vrstvy).
     *
     * @param  list<LeafletPageData>  $pages
     */
    private function storePages(Leaflet $leaflet, array $pages): void
    {
        if ($pages === []) {
            return;
        }

        LeafletPage::query()
            ->where('leaflet_id', $leaflet->id)
            ->whereNotIn('number', array_map(fn (LeafletPageData $page): int => $page->number, $pages))
            ->delete();

        $now = CarbonImmutable::now();
        $rows = array_map(fn (LeafletPageData $page): array => [
            'leaflet_id' => $leaflet->id,
            'number' => $page->number,
            'text' => $page->text,
            'image_url' => WebUrl::orNull($page->imageUrl),
            'page_url' => WebUrl::orNull($page->pageUrl),
            'created_at' => $now,
            'updated_at' => $now,
        ], $pages);

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            LeafletPage::query()->upsert($chunk, ['leaflet_id', 'number'], ['text', 'image_url', 'page_url', 'updated_at']);
        }
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
            'image_url' => WebUrl::orNull($offer->imageUrl),
            'source_url' => WebUrl::orNull($offer->sourceUrl),
            'raw' => json_encode($offer->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
