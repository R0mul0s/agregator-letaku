<?php

/**
 * Sdílené kontroly parserů letáků (R106): cena za jednotku, štítek slevy a geometrie stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\UnitPriceCheck;

describe('cena za jednotku', function (): void {
    it('ověří cenu balení s tolerancí zaokrouhlení', function (): void {
        // 500 g za 29,90 Kč = 59,80 Kč/kg; obchod uvádí 59,80 i 59,90
        expect(UnitPriceCheck::matches(2990, 500, 1000, 5980))->toBeTrue()
            ->and(UnitPriceCheck::matches(2990, 500, 1000, 5990))->toBeTrue()
            ->and(UnitPriceCheck::matches(2990, 500, 1000, 6990))->toBeFalse();
    });

    it('balení bez množství nikdy nesedí — dělení nulou by shodilo stažení obchodu', function (): void {
        expect(UnitPriceCheck::matches(2990, 0, 1000, 5980))->toBeFalse()
            ->and(UnitPriceCheck::matchesAny(2990, 5980, 1000, false, [0.0, 500.0]))->toBeTrue()
            ->and(UnitPriceCheck::matchesAny(2990, 5980, 1000, false, []))->toBeFalse();
    });

    it('u „od“ počítá jen s největším balením', function (): void {
        // 200 g a 500 g za 29,90 Kč: „od 59,80 Kč/kg“ platí pro 500 g
        expect(UnitPriceCheck::matchesAny(2990, 5980, 1000, true, [200.0, 500.0]))->toBeTrue()
            ->and(UnitPriceCheck::matchesAny(2990, 14950, 1000, true, [200.0, 500.0]))->toBeFalse()
            ->and(UnitPriceCheck::matchesAny(2990, 14950, 1000, false, [200.0, 500.0]))->toBeTrue();
    });
});

describe('štítek slevy', function (): void {
    it('připustí uříznuté i zaokrouhlené procento', function (): void {
        // 31,90 → 19,90 = 37,6 %
        expect(DiscountCheck::truncatedOrRounded(3190, 1990, 37))->toBeTrue()
            ->and(DiscountCheck::truncatedOrRounded(3190, 1990, 38))->toBeTrue()
            ->and(DiscountCheck::truncatedOrRounded(3190, 1990, 40))->toBeFalse()
            ->and(DiscountCheck::truncatedOrRounded(0, 1990, 37))->toBeFalse();
    });

    it('zaokrouhlené procento s tolerancí jen u vyšší původní ceny', function (): void {
        expect(DiscountCheck::roundedWithin(1990, 3190, 37, 1))->toBeTrue()
            ->and(DiscountCheck::roundedWithin(1990, 3190, 35, 1))->toBeFalse()
            ->and(DiscountCheck::roundedWithin(3190, 1990, 37, 1))->toBeFalse();
    });
});

describe('geometrie stránky', function (): void {
    it('každý prvek přiřadí nejvýš k jedné ceně, od nejbližšího páru', function (): void {
        $distance = fn (int $price, int $candidate): float => abs($price - $candidate);

        expect(PdfLayout::nearest([10, 20], [12, 19], $distance))->toBe([1 => 19, 0 => 12]);
    });

    it('spáruje cenu a dlaždici, jen když jsou si jednoznačně nejbližší (R107)', function (): void {
        $distance = fn (int $price, int $tile): ?float => abs($price - $tile) <= 50 ? (float) abs($price - $tile) : null;

        // 100 ↔ 110 jednoznačně; 200 leží mezi 180 a 222 (20 vs 22) — bez dvojice; 300 nemá nic v dosahu
        expect(PdfLayout::mutualNearest([100, 200, 300], [110, 180, 222], $distance, 0.7))->toBe([0 => 0]);
    });

    it('slova složí do řádků podle horního okraje a mezery', function (): void {
        $words = [
            new PdfWord('Máslo', 10, 100, 40, 110),
            new PdfWord('250 g', 44, 100.5, 70, 110),
            new PdfWord('Vejce', 200, 100, 230, 110),
        ];

        $rows = PdfLayout::rows($words, 1.5, 6.0);

        expect(array_map(fn (PdfBox $row): string => $row->text, $rows))->toBe(['Máslo 250 g', 'Vejce']);
    });
});
