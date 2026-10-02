<?php

/**
 * Import seznamu prodejen Kauflandu (.klstorefinder.json).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\Store;
use Illuminate\Support\Facades\Http;

it('uloží prodejny a opakovaný import je nezdvojí', function (): void {
    Http::fake(['https://prodejny.kaufland.cz/.klstorefinder.json' => Http::response(responseFixture('kaufland/stores-2026-10-02.json'))]);

    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']])->assertSuccessful();
    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']])->assertSuccessful();

    expect(Store::query()->count())->toBe(3);

    expect(Store::query()->where('external_id', 'CZ1000')->sole())
        ->chain->toBe(Chain::Kaufland)
        ->name->toBe('Kaufland Ostrava-Zábřeh')
        ->city->toBe('Ostrava')
        ->address->toBe('Výškovická 3086/44')
        // decimal(9,6) — šest desetinných míst stačí na desítky centimetrů
        ->latitude->toBe(49.800271);
});

it('prázdný seznam prodejen je chyba', function (): void {
    Http::fake(['https://prodejny.kaufland.cz/.klstorefinder.json' => Http::response('[]')]);

    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']])->assertFailed();

    expect(Store::query()->count())->toBe(0);
});
