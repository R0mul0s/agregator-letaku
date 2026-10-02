<?php

/**
 * Místní data platnosti z časů obchodů v UTC (R7).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Offers\LocalCalendar;

it('začátek platnosti v UTC převede na místní datum', function (string $instant, string $date): void {
    expect((new LocalCalendar)->startFromInstant($instant)->toDateString())->toBe($date);
})->with([
    'půlnoc letního času' => ['2026-09-29T22:00:00Z', '2026-09-30'],
    'ráno' => ['2026-09-30T06:00:00.000Z', '2026-09-30'],
]);

it('konec platnosti převede na poslední místní den', function (string $instant, string $date): void {
    expect((new LocalCalendar)->endFromInstant($instant)->toDateString())->toBe($date);
})->with([
    'půlnoc dalšího dne' => ['2026-10-04T22:00:00Z', '2026-10-04'],
    'poslední sekunda dne' => ['2026-10-06T21:59:59.000Z', '2026-10-06'],
    'půlnoc zimního času' => ['2026-11-01T23:00:00Z', '2026-11-01'],
    'poslední sekunda zimního času' => ['2026-10-27T22:59:59.000Z', '2026-10-27'],
]);

it('dnešek je místní den, ne den v UTC', function (): void {
    $this->travelTo('2026-10-02T22:30:00Z');

    expect((new LocalCalendar)->today()->toDateString())->toBe('2026-10-03');
});
