<?php

/**
 * Prodejny a jejich výběr uživatelem (R3).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\StoreFormat;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('uloží prodejnu s obchodem a formátem jako enumy', function (): void {
    $store = Store::factory()->of(Chain::Albert, StoreFormat::Hypermarket)->create();

    expect($store->fresh())
        ->chain->toBe(Chain::Albert)
        ->format->toBe(StoreFormat::Hypermarket);
});

it('nedovolí dvě prodejny se stejným ID u téhož obchodu', function (): void {
    Store::factory()->of(Chain::Kaufland)->create(['external_id' => 'CZ3300']);

    Store::factory()->of(Chain::Kaufland)->create(['external_id' => 'CZ3300']);
})->throws(UniqueConstraintViolationException::class);

it('dovolí stejné ID prodejny u různých obchodů', function (): void {
    Store::factory()->of(Chain::Kaufland)->create(['external_id' => '1001']);
    Store::factory()->of(Chain::Penny)->create(['external_id' => '1001']);

    expect(Store::query()->count())->toBe(2);
});

it('uživatel si vybere prodejny a smazáním účtu výběr zmizí', function (): void {
    $user = User::factory()->create();
    $stores = Store::factory()->count(2)->create();

    $user->stores()->attach($stores);
    expect($user->stores()->count())->toBe(2);

    $user->delete();
    expect(DB::table('store_user')->count())->toBe(0)
        ->and(Store::query()->count())->toBe(2);
});
