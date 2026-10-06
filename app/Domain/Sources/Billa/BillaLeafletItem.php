<?php

/**
 * Ověřená dlaždice PDF letáku Billy (R89) — ceny, typ akce a platnost z letáku, zatím bez produktu
 * katalogu. Produkt (a s ním ID akce) jí přiřadí `BillaCatalogMatcher` podle názvu, balení a běžné ceny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Enums\OfferType;
use Carbon\CarbonImmutable;

final readonly class BillaLeafletItem
{
    /**
     * @param  string  $name  Název z letáku („Lipánek tvarohový“)
     * @param  list<array{unit: string, quantity: float}>  $packages  Balení z popisu v jednotkách pro porovnání (g, ml, ks)
     * @param  bool  $variants  Leták nabízí víc druhů („více druhů“, „2 druhy“, „od 270 g“) — akce platí na víc produktů katalogu
     * @param  int  $price  Haléře; cena bez Klubu (u akce na množství běžná cena kusu)
     * @param  int|null  $usualPrice  Haléře; běžná cena, kterou má produkt v katalogu (přeškrtnutá, „běžná cena“
     *                                u ceny s Klubem, cena jednoho kusu u akce na množství); null bez ní („NAŠE CENA“)
     * @param  array<string, mixed>  $raw  Text dlaždice a ceny pro `offers.raw`
     */
    public function __construct(
        public string $name,
        public ?string $packageText,
        public array $packages,
        public bool $variants,
        public OfferType $offerType,
        public int $price,
        public ?int $usualPrice,
        public ?int $originalPrice,
        public ?int $loyaltyPrice,
        public ?int $discountPercent,
        public ?string $promotionText,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public int $page,
        public array $raw,
    ) {}
}
