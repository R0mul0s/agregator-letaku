<?php

/**
 * Stáhne prodejny obchodu a u každé seznam akcí, které v ní platí (R49). Import nabídek
 * z těchto seznamů pozná, v kterých prodejnách akce platí, a stáhne detail akcí, které ve
 * výchozí nabídce chybí. Prodejna, která ze seznamu zmizela, se smaže.
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
        $closed = Store::query()->where('chain', $chain)->whereNotIn('code', array_keys($stores))->delete();
        if ($closed > 0) {
            $this->forgetClosedStores($chain, array_keys($stores));
        }

        $lists = 0;
        $lastError = null;
        foreach (array_keys($stores) as $code) {
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

        return ['stores' => count($stores), 'lists' => $lists];
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
