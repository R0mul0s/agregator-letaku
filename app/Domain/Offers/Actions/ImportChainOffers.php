<?php

/**
 * Stáhne akční nabídku obchodu a uloží ji — zdroje (leaflets) a nabídky (offers).
 *
 * Každé stažení má záznam v scrape_runs. Nula nabídek je chyba zdroje, ne „žádné akce“
 * (CODING_GUIDELINES, sekce 3), i když zdroj vrátil stránky letáku (R54). Nabídky se nemažou
 * (R10): opakované stažení stejnou nabídku podle obchodu, ID položky a platnosti jen aktualizuje.
 * Neskončená nabídka, která v novém stažení chybí, se označí jako stažená obchodem (R16) —
 * ale ne, když jich chybí podezřele mnoho (R54). Akce uložená dřív pod předběžným ID (Globus, Billa: PDF
 * budoucího letáku) převezme ID akce ze zdroje (R88). Nakonec se nabídky obchodu znovu přiřadí
 * k produktům katalogu (R30).
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
use App\Domain\Offers\Exceptions\ImportAlreadyRunning;
use App\Domain\Offers\Exceptions\SourceReturnedNoOffers;
use App\Domain\Offers\Exceptions\SuspiciousWithdrawal;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\WebUrl;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportChainOffers
{
    /** Předpona zámku stažení obchodu v cache (R57). */
    private const LOCK_PREFIX = 'import-offers:';

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
        // Jedno stažení obchodu najednou (R57) — souběžná by si navzájem stáhla akce (R16).
        // Zámek v cache vyprší sám, když hosting proces ukončí dřív, než ho uvolní.
        $lock = Cache::lock(self::LOCK_PREFIX.$chain->value, config()->integer('letaky.import.lock_seconds'));
        if (! $lock->get()) {
            throw ImportAlreadyRunning::for($chain);
        }

        try {
            $this->failStuckRuns($chain);

            return $this->import($chain);
        } finally {
            $lock->release();
        }
    }

    /**
     * Stažení pod zámkem: zdroj, uložení v transakci a záznam o výsledku.
     *
     * @throws Throwable
     */
    private function import(Chain $chain): ScrapeRun
    {
        $run = ScrapeRun::start($chain);

        try {
            $batches = $this->sources->offers($chain)->fetch();

            [$offersCount, $withdrawn] = DB::transaction(function () use ($chain, $batches, $run): array {
                $stored = [];
                foreach ($batches as $batch) {
                    $stored += $this->storeBatch($chain, $batch, $run, array_keys($stored));
                }

                // Nula je chyba, i když zdroj vrátil stránky letáku (rozbitý parser letáku, R54)
                if ($stored === []) {
                    throw SourceReturnedNoOffers::for($chain);
                }

                $this->storeAvailability($run, $stored);
                $withdrawn = $this->markWithdrawn($chain, $run);
                $this->assignProducts->forChain($chain);

                return [count($stored), $withdrawn];
            });

            if ($withdrawn instanceof SuspiciousWithdrawal) {
                $run->succeedPartially($offersCount, $withdrawn);
                report($withdrawn);
            } else {
                $run->succeed($offersCount, $withdrawn);
            }
        } catch (Throwable $error) {
            $run->fail($error);

            throw $error;
        }

        return $run;
    }

    /**
     * Stažení obchodu, které zůstalo „běží“ déle, než platí zámek, nedoběhlo — hosting proces
     * ukončil (časový limit, paměť) a záznam se nestihl uzavřít. Označí se jako neúspěšné (R57).
     */
    private function failStuckRuns(Chain $chain): void
    {
        ScrapeRun::query()
            ->where('chain', $chain)
            ->where('status', ScrapeStatus::Running)
            ->where('started_at', '<', CarbonImmutable::now()->subSeconds(config()->integer('letaky.import.lock_seconds')))
            ->update([
                'status' => ScrapeStatus::Failed,
                'error' => __('app.import.stuck'),
                'finished_at' => CarbonImmutable::now(),
            ]);
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

        $this->adoptProvisional($chain, $batch->offers);

        $rows = [];
        $storeCodes = [];
        foreach ($this->continuePrevious($chain, $batch->offers) as $offer) {
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
     * Řádek uložený pod předběžným ID (`OfferData::$supersedes`) převezme ID a platnost akce
     * ze zdroje (R88): Globus ukládá akce budoucího letáku z PDF, a když začnou platit, vrátí
     * je API pod vlastním ID. Upsert pak řádek jen aktualizuje — zůstane jeho `created_at`
     * (souhrn ani centrum upozornění akci neohlásí podruhé jako novou, R74, R76), přiřazení
     * k produktům katalogu i ID v uložených upozorněních. Převezme se jen řádek s překrývající
     * se platností a jen když akce se stejným klíčem ještě uložená není.
     *
     * @param  list<OfferData>  $offers
     */
    private function adoptProvisional(Chain $chain, array $offers): void
    {
        $claims = [];
        foreach ($offers as $offer) {
            if ($offer->supersedes !== null && $offer->supersedes !== $offer->externalId) {
                $claims[$offer->supersedes][] = $offer;
            }
        }
        if ($claims === []) {
            return;
        }

        $provisional = Offer::query()
            ->where('chain', $chain)
            ->whereIn('external_id', array_keys($claims))
            ->get(['id', 'external_id', 'valid_from', 'valid_to']);
        if ($provisional->isEmpty()) {
            return;
        }

        // Akce, které už jsou uložené pod ID ze zdroje — ty žádný řádek nepřevezmou
        $targets = [];
        foreach ($provisional as $row) {
            foreach ($claims[$row->external_id] as $offer) {
                $targets[$offer->externalId] = true;
            }
        }
        $existing = Offer::query()
            ->where('chain', $chain)
            ->whereIn('external_id', array_map(strval(...), array_keys($targets)))
            ->get(['external_id', 'valid_from', 'valid_to'])
            ->mapWithKeys(fn (Offer $row): array => [$row->external_id.'|'.$row->valid_from->toDateString().'|'.$row->valid_to->toDateString() => true])
            ->all();

        foreach ($provisional as $row) {
            foreach ($claims[$row->external_id] as $index => $offer) {
                if (isset($existing[$offer->key()]) || $row->valid_from->greaterThan($offer->validTo) || $row->valid_to->lessThan($offer->validFrom)) {
                    continue;
                }

                Offer::query()->whereKey($row->id)->update([
                    'external_id' => $offer->externalId,
                    'valid_from' => $offer->validFrom->toDateString(),
                    'valid_to' => $offer->validTo->toDateString(),
                ]);
                $existing[$offer->key()] = true;
                unset($claims[$row->external_id][$index]);

                break;
            }
        }
    }

    /**
     * Akce, která navazuje na uloženou akci se stejnou cenou, převezme její začátek platnosti,
     * takže upsert prodlouží existující řádek místo založení nového (R54). Jinak by pokračující
     * akce každý týden dostala nové ID — souhrn by ji poslal jako novou a ruční opravy katalogu
     * by se ztratily. Jen u zdrojů, které platnost samy odvozují (Billa: akční týden, R48);
     * u ostatních by se slily skutečně odlišné akce. Akce, které ještě nezačaly, se neprodlužují (R89).
     *
     * @param  list<OfferData>  $offers
     * @return list<OfferData>
     */
    private function continuePrevious(Chain $chain, array $offers): array
    {
        if ($offers === [] || config("letaky.sources.{$chain->value}.extends_continuing_offers") !== true) {
            return $offers;
        }

        $previous = Offer::query()
            ->where('chain', $chain)
            ->active()
            ->whereIn('external_id', array_values(array_unique(array_map(fn (OfferData $offer): string => $offer->externalId, $offers))))
            ->get(['id', 'external_id', 'valid_from', 'valid_to', 'price', 'loyalty_price'])
            ->groupBy('external_id');

        $today = $this->calendar->today();
        $result = [];
        $extended = [];
        foreach ($offers as $offer) {
            // Akce, která ještě nezačala (Billa: PDF letáku dalšího týdne, R89), nic neprodlužuje —
            // pokračování pozná zdroj sám a prodlouží ho až akce z API v novém týdnu
            $row = $offer->validFrom->greaterThan($today) ? null
                : $previous->get($offer->externalId)?->first(fn (Offer $row): bool => $this->continues($row, $offer));
            if ($row instanceof Offer) {
                if ($row->valid_to->toDateString() !== $offer->validTo->toDateString()) {
                    $extended[$offer->validTo->toDateString()][] = $row->id;
                }
                $offer = $offer->withValidFrom($row->valid_from);
            }
            $result[] = $offer;
        }

        foreach ($extended as $validTo => $ids) {
            foreach (array_chunk($ids, self::UPSERT_CHUNK) as $chunk) {
                Offer::query()->whereIn('id', $chunk)->update(['valid_to' => $validTo]);
            }
        }

        return $result;
    }

    /**
     * Navazuje nabídka na uloženou akci? Uložená začala dřív, skončila nejdřív den před
     * začátkem nové a nejpozději s ní a má stejnou cenu (změna ceny = nová akce).
     */
    private function continues(Offer $row, OfferData $offer): bool
    {
        return $row->valid_from->lessThan($offer->validFrom)
            && $row->valid_to->greaterThanOrEqualTo($offer->validFrom->subDay())
            && $row->valid_to->lessThanOrEqualTo($offer->validTo)
            && $row->price === $offer->price
            && $row->loyalty_price === $offer->loyaltyPrice;
    }

    /**
     * Neskončené nabídky obchodu, které v tomto stažení chyběly, označí jako stažené
     * obchodem (R16). Když se později znovu objeví, upsert označení zruší.
     *
     * Chybí-li víc než povolený podíl neskončených akcí, zdroj nejspíš vrátil jen část nabídky
     * (R54) — neoznačí se nic a vrátí se důvod; akce zůstanou do konce platnosti.
     */
    private function markWithdrawn(Chain $chain, ScrapeRun $run): int|SuspiciousWithdrawal
    {
        $current = Offer::query()
            ->where('chain', $chain)
            ->active()
            ->notExpired($this->calendar->today());
        $missing = (clone $current)->where('scrape_run_id', '!=', $run->id);

        $missingCount = $missing->count();
        $currentCount = $current->count();
        $maxShare = config()->float('letaky.import.max_withdrawn_share');
        if ($missingCount > $currentCount * $maxShare) {
            return SuspiciousWithdrawal::for($chain, $missingCount, $currentCount, $maxShare);
        }

        return $missing->update(['withdrawn_at' => CarbonImmutable::now()]);
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
