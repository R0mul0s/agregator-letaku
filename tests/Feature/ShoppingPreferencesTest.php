<?php

/**
 * Stránka Moje obchody — sledované obchody a věrnostní karty (R19, R21).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\StoreFormat;
use App\Models\Store;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('ukáže všechny obchody, sledovatelné jen ty se zdrojem nabídek', function (): void {
    // Obchod bez zdroje nabídek (dnes mají zdroj všechny)
    config(['letaky.sources.albert.offers_source' => null]);

    $this->get(route('preferences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Preferences')
            ->has('chains', count(Chain::cases()))
            ->where('chains.0.value', 'kaufland')
            ->where('chains.0.available', true)
            ->where('chains.0.loyaltyProgram', 'kaufland_card')
            ->where('chains.1.value', 'tesco')
            ->where('chains.1.hasStoreFormats', true)
            ->where('chains.1.hasEshop', true)
            ->where('chains.2.value', 'albert')
            ->where('chains.2.available', false));
});

it('uloží sledované obchody s upřesněním a karty', function (): void {
    $this->put(route('preferences.update'), [
        'chains' => [
            ['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true],
            ['chain' => 'tesco', 'store_format' => 'supermarket', 'include_online_only' => false],
        ],
        'loyalty_programs' => ['clubcard'],
    ])->assertRedirect(route('preferences'))
        ->assertSessionHas('status', 'preferences-saved');

    $tesco = $this->user->followedChains()->where('chain', Chain::Tesco)->sole();
    expect($this->user->followedChains()->count())->toBe(2)
        ->and($tesco->store_format)->toBe(StoreFormat::Supermarket)
        ->and($tesco->include_online_only)->toBeFalse()
        ->and($this->user->fresh()?->hasLoyaltyProgram(LoyaltyProgram::Clubcard))->toBeTrue();
});

it('po zrušení sledování obchod odebere', function (): void {
    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true]],
        'loyalty_programs' => [],
    ]);

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'tesco', 'store_format' => null, 'include_online_only' => true]],
        'loyalty_programs' => [],
    ]);

    expect($this->user->followedChains()->pluck('chain')->all())->toBe([Chain::Tesco]);
});

it('nedovolí sledovat obchod bez zdroje nabídek', function (): void {
    config(['letaky.sources.albert.offers_source' => null]);

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'albert', 'store_format' => null, 'include_online_only' => true]],
        'loyalty_programs' => [],
    ])->assertSessionHasErrors('chains.0.chain');

    expect($this->user->followedChains()->count())->toBe(0);
});

it('u Kauflandu nabídne prodejny k výběru a uloží vybrané (R49)', function (): void {
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov']);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ1550', 'name' => 'Vrchlabí', 'city' => 'Vrchlabí']);

    $this->get(route('preferences'))->assertInertia(fn (Assert $page) => $page
        ->where('chains.0.stores', [
            ['code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov'],
            ['code' => 'CZ1550', 'name' => 'Vrchlabí', 'city' => 'Vrchlabí'],
        ])
        ->where('chains.0.storeCodes', [])
        ->where('chains.1.stores', [])
        ->where('maxSelectedStores', 10));

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true, 'store_codes' => ['CZ4400', 'CZ1550']]],
        'loyalty_programs' => [],
    ])->assertSessionHasNoErrors();

    expect($this->user->followedChains()->sole()->store_codes)->toBe(['CZ4400', 'CZ1550'])
        ->and($this->user->selectedStoreCodes())->toBe(['CZ4400', 'CZ1550']);

    // Žádná vybraná prodejna = všechny
    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true, 'store_codes' => []]],
        'loyalty_programs' => [],
    ]);
    expect($this->user->followedChains()->sole()->store_codes)->toBeNull();
});

it('neuloží prodejnu, která neexistuje, ani víc prodejen, než je limit', function (): void {
    config(['letaky.stores.max_selected' => 1]);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov']);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ1550', 'name' => 'Vrchlabí', 'city' => 'Vrchlabí']);

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true, 'store_codes' => ['CZ0000']]],
        'loyalty_programs' => [],
    ])->assertSessionHasErrors('chains.0.store_codes.0');

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true, 'store_codes' => ['CZ4400', 'CZ1550']]],
        'loyalty_programs' => [],
    ])->assertSessionHasErrors('chains.0.store_codes');
});
