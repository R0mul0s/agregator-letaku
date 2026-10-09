<?php

/**
 * Data z textu letáků bez roku a přes Nový rok (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Sources\Penny\PennyLeafletParser;
use App\Domain\Sources\Penny\SvgToken;
use Carbon\CarbonImmutable;

/**
 * Platnost jako dvojice textů „Y-m-d“, nebo null.
 *
 * @param  array{CarbonImmutable, CarbonImmutable|null}|null  $range
 * @return list<string|null>|null
 */
function rangeDates(?array $range): ?array
{
    return $range === null ? null : array_map(fn (?CarbonImmutable $date): ?string => $date?->toDateString(), $range);
}

it('rok jen u konce: začátek v prosinci patří do předchozího roku', function (): void {
    $dates = new LeafletDates(new LocalCalendar);

    expect(rangeDates($dates->range(28, 12, null, 3, 1, 2027)))->toBe(['2026-12-28', '2027-01-03'])
        ->and(rangeDates($dates->range(1, 10, null, 7, 10, 2026)))->toBe(['2026-10-01', '2026-10-07']);
});

it('rok jen u začátku: konec v lednu patří do dalšího roku', function (): void {
    expect(rangeDates((new LeafletDates(new LocalCalendar))->range(30, 12, 2026, 2, 1, null)))->toBe(['2026-12-30', '2027-01-02']);
});

it('bez roku rozhoduje konec nejbližší k referenčnímu dni', function (string $reference, array $expected): void {
    $range = (new LeafletDates(new LocalCalendar))->range(30, 12, null, 2, 1, null, CarbonImmutable::parse($reference));

    expect(rangeDates($range))->toBe($expected);
})->with([
    'leták končí v lednu' => ['2027-01-05', ['2026-12-30', '2027-01-02']],
    'leták končí v prosinci' => ['2026-12-31', ['2026-12-30', '2027-01-02']],
]);

it('datum bez roku najde nejbližší k referenčnímu dni', function (): void {
    $dates = new LeafletDates(new LocalCalendar);

    expect($dates->near(30, 12, CarbonImmutable::parse('2027-01-05'))?->toDateString())->toBe('2026-12-30')
        ->and($dates->near(3, 1, CarbonImmutable::parse('2026-12-28'))?->toDateString())->toBe('2027-01-03')
        ->and($dates->near(8, 10, CarbonImmutable::parse('2026-10-11'))?->toDateString())->toBe('2026-10-08');
});

it('neexistující den, konec před začátkem ani chybějící rok platnost nedají', function (): void {
    $dates = new LeafletDates(new LocalCalendar);

    expect($dates->range(31, 4, null, 5, 5, 2026))->toBeNull()
        ->and($dates->range(10, 10, 2026, 3, 10, 2026))->toBeNull()
        ->and($dates->range(1, 10, null, 7, 10, null))->toBeNull()
        ->and($dates->date(29, 2, 2027))->toBeNull();
});

it('platnost stránky Penny přes Nový rok začíná v prosinci', function (): void {
    $parser = app(PennyLeafletParser::class);
    $token = new SvgToken(0.0, 0.0, 10.0, '#000', 'Nabídka platná od pondělí 28. 12. do neděle 3. 1. 2027');

    expect(rangeDates($parser->pageValidity([$token])))->toBe(['2026-12-28', '2027-01-03']);
});
