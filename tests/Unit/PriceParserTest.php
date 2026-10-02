<?php

/**
 * Převod cen od obchodů na haléře (R7).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Offers\Exceptions\InvalidPrice;
use App\Domain\Offers\Parsing\PriceParser;

it('převede text ceny na haléře', function (string $text, int $halers): void {
    expect((new PriceParser)->parse($text))->toBe($halers);
})->with([
    'desetinná čárka' => ['59,90', 5990],
    'jedno desetinné místo' => ['29,9', 2990],
    'celé koruny' => ['149', 14900],
    'tisíce s mezerou' => ['1 299,00', 129900],
    'tisíce s nezlomitelnou mezerou' => ["1\u{00A0}299,00", 129900],
    's měnou' => ['29,90 Kč', 2990],
    'desetinná tečka' => ['10.00', 1000],
]);

it('odmítne text, který není cena', function (string $text): void {
    (new PriceParser)->parse($text);
})->with(['AKCE!', '29,999', ''])->throws(InvalidPrice::class);

it('prázdnou volitelnou cenu vrátí jako null', function (): void {
    expect((new PriceParser)->parseOptional(null))->toBeNull()
        ->and((new PriceParser)->parseOptional('  '))->toBeNull();
});

it('převede číslo z JSON bez chyby zaokrouhlení floatu', function (float $crowns, int $halers): void {
    expect((new PriceParser)->fromFloat($crowns))->toBe($halers);
})->with([
    [27.9, 2790],
    [0.29, 29],
    [219.9, 21990],
    [149, 14900],
]);

it('najde v popisu akce první částku v Kč', function (string $text, ?int $halers): void {
    expect((new PriceParser)->findFirst($text))->toBe($halers);
})->with([
    'Clubcard' => ['8,90 Kč Více než o polovinu nižší cena s Clubcard', 890],
    'za kg' => ['Ušetřete 1/3 99,00 Kč/kg s Clubcard', 9900],
    'procento před cenou' => ['Ušetřete 25% 149,00 Kč/kg s Clubcard', 14900],
    'tisíce' => ['2 za 1 299 Kč', 129900],
    'bez částky' => ['3 za cenu 2 Clubcard cena - Nejlevnější produkt zdarma', null],
]);
