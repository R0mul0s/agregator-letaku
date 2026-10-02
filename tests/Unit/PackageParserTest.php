<?php

/**
 * Množství v balení z textů obchodů — podklad pro cenu za jednotku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Offers\Parsing\PackageParser;
use App\Enums\PackageUnit;

it('přečte balení z údaje Kauflandu', function (string $text, float $quantity, PackageUnit $unit): void {
    $package = (new PackageParser)->parse($text);

    expect($package?->quantity)->toBe($quantity)
        ->and($package?->unit)->toBe($unit);
})->with([
    'gramy' => ['500 g', 500.0, PackageUnit::Gram],
    'gramy s popisem obalu' => ['500 g balení', 500.0, PackageUnit::Gram],
    'kilogram' => ['1 kg', 1000.0, PackageUnit::Gram],
    'desetinné gramy' => ['62,5 g', 62.5, PackageUnit::Gram],
    'litr s obalem' => ['1 l elopak', 1000.0, PackageUnit::Milliliter],
    'multipack' => ['4x0,33 l plech', 1320.0, PackageUnit::Milliliter],
    'kusy' => ['20 kusů', 20.0, PackageUnit::Piece],
    'kus' => ['1 kus', 1.0, PackageUnit::Piece],
    'cena za kilogram' => ['cena za 1 kg', 1000.0, PackageUnit::Gram],
]);

it('nejednoznačné balení nepřečte', function (?string $text): void {
    expect((new PackageParser)->parse($text))->toBeNull();
})->with([
    'dvě balení' => ['250 ml/500 ml'],
    'rozsah' => ['5 g-30 g'],
    'bez jednotky' => ['1 svazek'],
    'prázdné' => [''],
    'chybí' => [null],
]);

it('najde balení v názvu produktu Tesco', function (string $title, ?float $quantity, ?PackageUnit $unit): void {
    $package = (new PackageParser)->findInText($title);

    expect($package?->quantity)->toBe($quantity)
        ->and($package?->unit)->toBe($unit);
})->with([
    'procento tuku před balením' => ['Tesco Mléko UHT polotučné 1,5% 1l', 1000.0, PackageUnit::Milliliter],
    'násobek s mezerami' => ['Coca-Cola Zero 4 x 330ml', 1320.0, PackageUnit::Milliliter],
    'poslední údaj' => ['Persil Deep Clean Active Gel 66 praní 2,97l', 2970.0, PackageUnit::Milliliter],
    'kusy' => ['Tesco Čerstvá vejce M 10 ks', 10.0, PackageUnit::Piece],
    'zboží na váhu' => ['Mandarinky', null, null],
]);

it('z jednotky ceny udělá balení o jedné jednotce', function (string $label, ?float $quantity, ?PackageUnit $unit): void {
    $package = (new PackageParser)->fromUnitLabel($label);

    expect($package?->quantity)->toBe($quantity)
        ->and($package?->unit)->toBe($unit);
})->with([
    ['kg', 1000.0, PackageUnit::Gram],
    ['l', 1000.0, PackageUnit::Milliliter],
    ['kus', 1.0, PackageUnit::Piece],
    ['balení', null, null],
]);
