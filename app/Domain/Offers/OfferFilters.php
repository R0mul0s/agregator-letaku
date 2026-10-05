<?php

/**
 * Filtry výpisu akcí (Všechny akce a jejich našeptávač): obchody, produkt katalogu (R71),
 * jen akce, které ještě nezačaly (R76), a bez akcí jen z e-shopu (R82). Zná i názvy
 * parametrů adresy Všech akcí — čte je OffersRequest, odkazy skládá urlParameters().
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;

final readonly class OfferFilters
{
    /** Parametr adresy: obchody oddělené čárkou (jeden obchod = dřívější `?chain=kaufland`). */
    public const CHAIN_PARAMETER = 'chain';

    /** Hodnota parametru obchodů: všechny obchody i pro přihlášeného se sledovanými. */
    public const ALL_CHAINS = 'vse';

    /** Oddělovač obchodů v parametru. */
    public const CHAIN_SEPARATOR = ',';

    /** Parametr adresy: akce jednoho produktu katalogu (z našeptávače, R71). */
    public const PRODUCT_PARAMETER = 'produkt';

    /** Parametr adresy: jen akce, které ještě nezačaly (R76). */
    public const UPCOMING_PARAMETER = 'brzy';

    /** Parametr adresy: bez akcí jen z e-shopu (R82). */
    public const WITHOUT_ESHOP_PARAMETER = 'bez-eshopu';

    /**
     * @param  list<Chain>  $chains  Jen akce těchto obchodů; prázdné = všechny obchody
     * @param  int|null  $productId  Jen akce přiřazené k produktu katalogu
     * @param  bool  $upcomingOnly  Jen akce, které ještě nezačaly
     * @param  bool  $withoutEshop  Bez akcí jen z e-shopu (`online_only`)
     */
    public function __construct(
        public array $chains = [],
        public ?int $productId = null,
        public bool $upcomingOnly = false,
        public bool $withoutEshop = false,
    ) {}

    /**
     * Hodnota parametru obchodů; prázdný výběr = „vse“ (bez parametru by přihlášený
     * dostal své sledované obchody).
     */
    public function chainParameter(): string
    {
        return $this->chains === []
            ? self::ALL_CHAINS
            : implode(self::CHAIN_SEPARATOR, array_map(fn (Chain $chain): string => $chain->value, $this->chains));
    }

    /**
     * Parametry adresy Všech akcí s těmito filtry (vypnuté a všechny obchody vynechá).
     *
     * @return array<string, int|string>
     */
    public function urlParameters(): array
    {
        return array_filter([
            self::CHAIN_PARAMETER => $this->chains === [] ? null : $this->chainParameter(),
            self::PRODUCT_PARAMETER => $this->productId,
            self::UPCOMING_PARAMETER => $this->upcomingOnly ? 1 : null,
            self::WITHOUT_ESHOP_PARAMETER => $this->withoutEshop ? 1 : null,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
