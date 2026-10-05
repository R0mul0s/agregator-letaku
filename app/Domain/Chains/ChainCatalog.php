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
     * se po prodejnách neliší.
     *
     * @return list<array{code: string, name: string, city: string}>
     */
    public function stores(Chain $chain): array
    {
        if (config("letaky.sources.{$chain->value}.stores_source") === null) {
            return [];
        }

        return array_values(Store::query()->where('chain', $chain)->orderBy('city')->orderBy('name')->get(['code', 'name', 'city'])
            ->map(fn (Store $store): array => ['code' => $store->code, 'name' => $store->name, 'city' => $store->city])
            ->all());
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
