<?php

/**
 * Stáhne akční nabídku obchodu a uloží ji — zdroje (leaflets) a nabídky (offers).
 *
 * Každé stažení má záznam v scrape_runs. Nula nabídek je chyba zdroje, ne „žádné akce“
 * (CODING_GUIDELINES, sekce 3), i když zdroj vrátil stránky letáku (R54). Nabídky se nemažou
 * (R10): opakované stažení stejnou nabídku podle obchodu, ID položky a platnosti jen aktualizuje.
 * Neskončená nabídka, která v novém stažení chybí, se označí jako stažená obchodem (R16) —
 * ale ne, když jich chybí podezřele mnoho (R54). Zápis dávek dělá BatchWriter (včetně převzetí
 * řádků pod předběžným ID R88 a prodloužení pokračujících akcí R54, OfferContinuity). Nakonec se
 * nabídky obchodu znovu přiřadí k produktům katalogu (R30) a po uvolnění zámku se změněné veřejné
 * stránky ohlásí vyhledávačům přes IndexNow (R105).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Offers\ChangedOfferPages;
use App\Domain\Offers\Exceptions\ImportAlreadyRunning;
use App\Domain\Offers\Exceptions\SourceReturnedNoOffers;
use App\Domain\Offers\Exceptions\SuspiciousWithdrawal;
use App\Domain\Offers\Import\BatchWriter;
use App\Domain\Offers\Import\RecordLeafletStats;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Support\Seo\IndexNow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportChainOffers
{
    /** Předpona zámku stažení obchodu v cache (R57). */
    private const LOCK_PREFIX = 'import-offers:';

    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly LocalCalendar $calendar,
        private readonly BatchWriter $writer,
        private readonly AssignProducts $assignProducts,
        private readonly ChangedOfferPages $changedPages,
        private readonly IndexNow $indexNow,
        private readonly RecordLeafletStats $leafletStats,
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
            $run = $this->import($chain);
        } finally {
            $lock->release();
        }

        $this->notifySearchEngines($run);

        return $run;
    }

    /**
     * Ohlásí vyhledávačům stránky, které stažení změnilo (IndexNow, R105). Uložené akce to
     * neovlivní — chyba se jen zapíše.
     */
    private function notifySearchEngines(ScrapeRun $run): void
    {
        if (! $this->indexNow->enabled()) {
            return;
        }

        try {
            $this->indexNow->submit($this->changedPages->forRun($run));
        } catch (Throwable $error) {
            report($error);
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
                    $stored += $this->writer->store($chain, $batch, $run, array_keys($stored));
                }

                // Nula je chyba, i když zdroj vrátil stránky letáku (rozbitý parser letáku, R54)
                if ($stored === []) {
                    throw SourceReturnedNoOffers::for($chain);
                }

                $this->writer->storeAvailability($run, $stored);
                // Přehled kvality dat (R129): akce a ověřené ceny po letácích
                $this->leafletStats->record($chain, $run, $batches);
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
}
