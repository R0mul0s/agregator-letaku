<?php

/**
 * Akce vyřazené u všech obchodů (R181) — erotické pomůcky ano, kondomy, lubrikanty a běžné zboží ne.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

use App\Domain\Offers\Import\ExcludedOffers;

it('vyřadí erotické pomůcky, kondomy a běžné zboží nechá', function (string $name, bool $excluded): void {
    expect(app(ExcludedOffers::class)->isExcluded($name))->toBe($excluded);
})->with([
    // Skutečné názvy z akcí Košíku 8. 11. 2026
    ['Durex Vibrátor Mini Delight bullet', true],
    ['PlusOne Luxusní multi-vibrátor Masterpiece', true],
    ['Durex Play Masturbátor', true],
    ['Durex Vibrační kroužek Intense', true],
    ['PlusOne Vibrační masér Deep Pulse', true],
    ['PlusOne Prsní a tělový vibrační masér Care Touch', true],
    ['Durex Classic Kondomy', false],
    ['Durex Intense Orgasmic gel 10ml', false],
    ['Durex Naturals Pure Intimní gel', false],
    ['Chilly Gel pro intimní hygienu', false],
    ['Rexona Motionsense sexy bouquet strawberry & apricot antiperspirant sprej', false],
    ['Oral-B Pro 3 elektrický zubní kartáček', false],
]);

it('prázdný vzor nevyřadí nic', function (): void {
    config(['letaky.import.excluded_name_pattern' => '']);

    expect(app(ExcludedOffers::class)->isExcluded('Durex Vibrátor Mini Delight bullet'))->toBeFalse();
});
