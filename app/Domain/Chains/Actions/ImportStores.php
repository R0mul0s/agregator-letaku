<?php

/**
 * Stáhne prodejny obchodu a u každé seznam akcí, které v ní platí (R49). Import nabídek
 * z těchto seznamů pozná, v kterých prodejnách akce platí, a stáhne detail akcí, které ve
 * výchozí nabídce chybí. Prodejna, která ze seznamu zmizela, se smaže — ale jen když jich
 * nezmizelo podezřele mnoho (`letaky.stores.max_closed_share`, R170).
 *
 * U COOP je „seznam akcí“ prodejny seznam letáků, které v ní platí, a stránek prodejen je tolik,
 * že se jedno stažení postará jen o část (`store_lists_per_run`, R169).
 *
 * Seznam akcí jedné prodejny, který se nestáhne, nezastaví ostatní — zůstane předchozí a po
 * `store_offers_max_age_hours` se přestane brát v úvahu. Chyba je až to, když se nestáhne žádný.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Chains\Actions;

use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Models\FollowedChain;
use App\Models\Store;
use App\Support\Exceptions\AlreadyRunning;
use App\Support\ExclusiveRun;
use Carbon\CarbonImmutable;
use Throwable;

final class ImportStores
{
    private const LOCK_PREFIX = 'import-stores.';

    public function __construct(private readonly SourceRegistry $sources) {}

    /**
     * Uloží prodejny a jejich seznamy akcí; vrátí počet prodejen a stažených seznamů.
     *
     * @return array{stores: int, lists: int}
     *
     * @throws AlreadyRunning Prodejny obchodu se už stahují (R113)
     * @throws Throwable Seznam prodejen se nestáhl, nebo se nestáhl seznam akcí žádné prodejny
     */
    public function __invoke(Chain $chain): array
    {
        return ExclusiveRun::run(self::LOCK_PREFIX.$chain->value, fn (): array => $this->import($chain));
    }

    /**
     * Stáhne a uloží prodejny a jejich seznamy akcí.
     *
     * @return array{stores: int, lists: int}
     *
     * @throws Throwable
     */
    private function import(Chain $chain): array
    {
        $source = $this->sources->stores($chain);
        $stores = $source->stores();
        $now = CarbonImmutable::now();

        Store::query()->upsert(
            array_map(fn (string $code, array $store): array => [
                'chain' => $chain->value,
                'code' => $code,
                'name' => $store['name'],
                'city' => $store['city'],
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($stores), array_values($stores)),
            ['chain', 'code'],
            ['name', 'city', 'updated_at'],
        );
        $suspicious = $this->closeStores($chain, array_keys($stores));

        $lists = 0;
        $lastError = null;
        foreach ($this->codesToRefresh($chain, array_keys($stores)) as $code) {
            try {
                $keys = $source->offerKeys($code);
            } catch (Throwable $error) {
                report($error);
                $lastError = $error;

                continue;
            }

            // Hromadný update obchází přetypování modelu — JSON ručně
            Store::query()->where('chain', $chain)->where('code', $code)->update([
                'offer_keys' => json_encode($keys, JSON_THROW_ON_ERROR),
                'offer_keys_fetched_at' => CarbonImmutable::now(),
            ]);
            $lists++;
        }

        if ($lists === 0 && $lastError !== null) {
            throw $lastError;
        }
        // Seznamy se obnovily, ale chybějící prodejny jsou podezřelé — chyba pro /health/tasks a adminy
        if ($suspicious !== null) {
            throw $suspicious;
        }

        return ['stores' => count($stores), 'lists' => $lists];
    }

    /**
     * Smaže prodejny, které ze seznamu zmizely, a odebere je z výběru uživatelů. Zmizí-li jich
     * víc než `max_closed_share` (R170), nesmaže nic — nejspíš se změnil formát stránky a část
     * prodejen se nepřečetla; vrátí výjimku, kterou stažení po obnově seznamů akcí vyhodí.
     *
     * @param  list<string>  $openCodes
     */
    private function closeStores(Chain $chain, array $openCodes): ?SourceResponseChanged
    {
        $closing = Store::query()->where('chain', $chain)->whereNotIn('code', $openCodes);
        $closed = (clone $closing)->count();
        if ($closed === 0) {
            return null;
        }

        $total = Store::query()->where('chain', $chain)->count();
        if ($closed > $total * config()->float('letaky.stores.max_closed_share')) {
            return SourceResponseChanged::because($chain, "ze seznamu prodejen zmizelo {$closed} z {$total} — nic se nesmazalo");
        }

        $closing->delete();
        $this->forgetClosedStores($chain, $openCodes);

        return null;
    }

    /**
     * Prodejny, jejichž seznam se stáhne teď: všechny, nebo s `store_lists_per_run` jen tolik
     * nejdéle neobnovených (nové první) — COOP má ~1 450 stránek prodejen a obnoví je postupně
     * během několika běhů cronu (R169).
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function codesToRefresh(Chain $chain, array $codes): array
    {
        $perRun = config("letaky.sources.{$chain->value}.store_lists_per_run");
        if (! is_int($perRun)) {
            return $codes;
        }

        return array_values(Store::query()
            ->where('chain', $chain)
            ->whereIn('code', $codes)
            ->orderByRaw('offer_keys_fetched_at IS NOT NULL')
            ->orderBy('offer_keys_fetched_at')
            ->limit($perRun)
            ->pluck('code')
            ->all());
    }

    /**
     * Odebere zavřené prodejny z výběru uživatelů (R113) — s kódem, který už neexistuje, by
     * uživatel tiše viděl jen akce bez omezení a uložení Mých obchodů by validace odmítla.
     * Bez zbylé prodejny výběr zanikne (null = všechny prodejny).
     *
     * @param  list<string>  $openCodes
     */
    private function forgetClosedStores(Chain $chain, array $openCodes): void
    {
        FollowedChain::query()
            ->where('chain', $chain)
            ->whereNotNull('store_codes')
            ->lazyById()
            ->each(function (FollowedChain $followed) use ($openCodes): void {
                $codes = array_values(array_intersect($followed->store_codes ?? [], $openCodes));
                if ($codes !== $followed->store_codes) {
                    $followed->store_codes = $codes === [] ? null : $codes;
                    $followed->save();
                }
            });
    }
}
