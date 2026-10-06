<?php

/**
 * Produkt katalogu Billy v krátkém tvaru pro párování s dlaždicemi letáku (R89) — celý katalog
 * (~12 tisíc produktů) se drží v paměti, proto jen pole potřebná k párování a k akci.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

final readonly class BillaCatalogProduct
{
    /**
     * @param  int  $usualPrice  Haléře; běžná cena — přeškrtnutá u akce, jinak aktuální (u zboží na váhu za kg)
     * @param  string|null  $unit  Jednotka balení pro porovnání (g, ml, ks), null bez balení
     */
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $brand,
        public ?string $packageText,
        public ?float $quantity,
        public ?string $unit,
        public int $usualPrice,
        public ?string $category,
        public ?string $imageUrl,
        public ?string $sourceUrl,
    ) {}
}
