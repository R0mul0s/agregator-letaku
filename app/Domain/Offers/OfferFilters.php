<?php

/**
 * Filtry výpisu akcí (Všechny akce a jejich našeptávač): obchody, produkt katalogu (R71),
 * jen akce, které ještě nezačaly (R76), bez akcí jen z e-shopu (R82), řazení a nastavení
 * Mých obchodů přihlášeného (R100), končí brzy, nové a sleva od (R101). Zná i názvy
 * parametrů adresy Všech akcí — čte je OffersRequest, odkazy skládá urlParameters().
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Enums\OfferListSort;
use App\Models\User;

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

    /** Parametr adresy: řazení (OfferListSort, R100). */
    public const SORT_PARAMETER = 'razeni';

    /** Parametr adresy: `0` = bez nastavení Mých obchodů přihlášeného (R100); výchozí je s nimi. */
    public const SHOPPING_PREFERENCES_PARAMETER = 'moje-obchody';

    /** Parametr adresy: jen akce, které končí do `letaky.offers.ending_soon_days` dní (R101). */
    public const ENDING_SOON_PARAMETER = 'konci-brzy';

    /** Parametr adresy: jen akce zveřejněné za posledních `letaky.offers.fresh_days` dní (R101). */
    public const FRESH_PARAMETER = 'nove';

    /** Parametr adresy: jen skutečné slevy od tolika procent (R101). */
    public const MIN_DISCOUNT_PARAMETER = 'sleva-od';

    /**
     * @param  list<Chain>  $chains  Jen akce těchto obchodů; prázdné = všechny obchody
     * @param  int|null  $productId  Jen akce přiřazené k produktu katalogu
     * @param  bool  $upcomingOnly  Jen akce, které ještě nezačaly
     * @param  bool  $withoutEshop  Bez akcí jen z e-shopu (`online_only`)
     * @param  OfferListSort|null  $sort  Zvolené řazení; null = podle situace (OfferListSort::defaultFor)
     * @param  User|null  $preferencesOf  Uplatnit nastavení Mých obchodů tohoto uživatele (ShoppingPreferencesScope)
     * @param  bool  $preferencesOff  Přihlášený nastavení vypnul — jen pro adresu (urlParameters)
     * @param  bool  $endingSoon  Jen akce, které už platí a brzy končí
     * @param  bool  $freshOnly  Jen nově zveřejněné akce
     * @param  int|null  $minDiscount  Jen skutečné slevy (R8) od tolika procent
     */
    public function __construct(
        public array $chains = [],
        public ?int $productId = null,
        public bool $upcomingOnly = false,
        public bool $withoutEshop = false,
        public ?OfferListSort $sort = null,
        public ?User $preferencesOf = null,
        public bool $preferencesOff = false,
        public bool $endingSoon = false,
        public bool $freshOnly = false,
        public ?int $minDiscount = null,
    ) {}

    /**
     * Stejné filtry s nastavením Mých obchodů jiného uživatele, nebo bez něj (null).
     */
    public function withPreferencesOf(?User $user): self
    {
        return new self(
            $this->chains,
            $this->productId,
            $this->upcomingOnly,
            $this->withoutEshop,
            $this->sort,
            $user,
            $this->preferencesOff,
            $this->endingSoon,
            $this->freshOnly,
            $this->minDiscount,
        );
    }

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
            self::ENDING_SOON_PARAMETER => $this->endingSoon ? 1 : null,
            self::FRESH_PARAMETER => $this->freshOnly ? 1 : null,
            self::MIN_DISCOUNT_PARAMETER => $this->minDiscount,
            self::SORT_PARAMETER => $this->sort?->value,
            self::SHOPPING_PREFERENCES_PARAMETER => $this->preferencesOff ? 0 : null,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
