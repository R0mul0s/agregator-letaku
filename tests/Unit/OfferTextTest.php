<?php

/**
 * Texty nabídek — souhrnné „různé druhy“ (R9), úprava bílých znaků, cena za jednotku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Offers\UnitPrice;
use App\Enums\PackageUnit;

it('pozná souhrnnou nabídku', function (?string $text, ?string $note): void {
    expect((new VariantNote)->detect(null, $text))->toBe($note);
})->with([
    ['Coca-Cola/Fanta/Sprite různé druhy', 'různé druhy'],
    ['Rama Různé Druhy', 'různé druhy'],
    ['Coca-Cola 4pack vybrané druhy', 'vybrané druhy'],
    ['Coca-Cola 1,5 l, více druhů', 'více druhů'],
    ['Coca-Cola Zero 500ml', null],
]);

it('sloučí bílé znaky a prázdný text vrátí jako null', function (): void {
    expect(Text::clean("Rama\nrůzné   druhy "))->toBe('Rama různé druhy')
        ->and(Text::clean(" \n "))->toBeNull()
        ->and(Text::join('Tatra', null, "Máslo\n"))->toBe('Tatra Máslo')
        ->and(Text::join(null, ''))->toBeNull();
});

it('spočítá cenu za kg, l a kus', function (?int $price, ?float $quantity, ?PackageUnit $unit, ?int $unitPrice): void {
    expect(UnitPrice::of($price, $quantity, $unit))->toBe($unitPrice);
})->with([
    '125 g za 59,90' => [5990, 125.0, PackageUnit::Gram, 47920],
    '1,5 l za 31,90' => [3190, 1500.0, PackageUnit::Milliliter, 2127],
    '20 vajec za 59,90' => [5990, 20.0, PackageUnit::Piece, 300],
    'bez balení' => [5990, null, null, null],
    'bez ceny' => [null, 500.0, PackageUnit::Gram, null],
]);
