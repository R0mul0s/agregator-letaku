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
     * Má obchod akce jen v e-shopu?
     */
    public function hasEshop(Chain $chain): bool
    {
        return config("letaky.sources.{$chain->value}.has_eshop") === true;
    }

    /**
     * Má obchod seznam prodejen?
     */
    public function hasStores(Chain $chain): bool
    {
        return in_array($chain, $this->sources->chainsWithStores(), true);
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
