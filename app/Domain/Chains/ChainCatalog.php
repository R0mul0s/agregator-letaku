<?php

/**
 * Obchody pro nastavení uživatele — které jdou sledovat a co se u nich dá upřesnit (R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Chains;

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Models\Store;

final class ChainCatalog
{
    public function __construct(private readonly SourceRegistry $sources) {}

    /**
     * Obchody, které jde sledovat — mají zdroj nabídek.
     *
     * @return list<Chain>
     */
    public function available(): array
    {
        return $this->sources->chainsWithOffers();
    }

    /**
     * Rozlišuje obchod typy prodejen (hypermarket / supermarket)?
     */
    public function hasStoreFormats(Chain $chain): bool
    {
        return config("letaky.sources.{$chain->value}.has_store_formats") === true;
    }

    /**
     * Je konec akcí obchodu jen odhad (Billa: akční týden, prodlužování, R48, R54)? Takovým
     * akcím se neupozorňuje, že končí (R74).
     */
    public function hasEstimatedValidity(Chain $chain): bool
    {
        return config("letaky.sources.{$chain->value}.estimated_validity") === true;
    }

    /**
     * Má obchod akce jen v e-shopu?
     */
    public function hasEshop(Chain $chain): bool
    {
        return config("letaky.sources.{$chain->value}.has_eshop") === true;
    }

    /**
     * Prodejny obchodu k výběru (R49), podle města a názvu; prázdné u obchodu, jehož akce
     * se po prodejnách neliší. Kaufland a COOP je stahují (ImportStores), hypermarkety Globusu
     * zakládá migrace (R131). U COOP poznámka u prodejny, které nemá žádný ze sledovaných
     * letáků (Terno, TIP, TUTY, Morava, R169) — akce COOP tam neuvidíte.
     *
     * @return list<array{code: string, name: string, city: string, note?: string}>
     */
    public function stores(Chain $chain): array
    {
        $noteEmpty = config("letaky.sources.{$chain->value}.store_note_without_lists") === true;

        return array_values(Store::query()->where('chain', $chain)->orderBy('city')->orderBy('name')->get(['code', 'name', 'city', 'offer_keys', 'offer_keys_fetched_at'])
            ->map(fn (Store $store): array => [
                'code' => $store->code,
                'name' => $store->name,
                'city' => $store->city,
                ...($noteEmpty && $store->offer_keys_fetched_at !== null && $store->offer_keys === [] ? ['note' => __('app.ui.preferences.store_without_leaflet')] : []),
            ])
            ->all());
    }

    /**
     * Zapnuté obchody, které mají prodejny k výběru (R49), v pořadí výčtu — bez načtení prodejen.
     *
     * @return list<Chain>
     */
    public function chainsWithStores(): array
    {
        $withStores = Store::query()->distinct()->pluck('chain')->map(fn (Chain $chain): string => $chain->value)->all();

        return array_values(array_filter(Chain::enabled(), fn (Chain $chain): bool => in_array($chain->value, $withStores, true)));
    }

    /**
     * Věrnostní program obchodu.
     */
    public function loyaltyProgram(Chain $chain): ?LoyaltyProgram
    {
        foreach (LoyaltyProgram::cases() as $program) {
            if ($program->chain() === $chain) {
                return $program;
            }
        }

        return null;
    }
}
