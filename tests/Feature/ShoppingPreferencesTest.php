<?php

/**
 * Stránka Moje obchody — sledované obchody, prodejny a věrnostní karty (R19).
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
    Store::factory()->of(Chain::Kaufland)->create(['city' => 'Benešov']);

    $this->get(route('preferences'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Preferences')
            ->has('chains', count(Chain::cases()))
            ->where('chains.0.value', 'kaufland')
            ->where('chains.0.available', true)
            ->where('chains.0.hasStores', true)
            ->where('chains.0.loyaltyProgram', 'kaufland_card')
            ->where('chains.1.value', 'tesco')
            ->where('chains.1.hasStoreFormats', true)
            ->where('chains.1.hasEshop', true)
            ->where('chains.2.value', 'albert')
            ->where('chains.2.available', false)
            ->has('stores', 1));
});

it('uloží sledované obchody s upřesněním, prodejny a karty', function (): void {
    $store = Store::factory()->of(Chain::Kaufland)->create();

    $this->put(route('preferences.update'), [
        'chains' => [
            ['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true],
            ['chain' => 'tesco', 'store_format' => 'supermarket', 'include_online_only' => false],
        ],
        'store_ids' => [$store->id],
        'loyalty_programs' => ['clubcard'],
    ])->assertRedirect(route('preferences'))
        ->assertSessionHas('status', 'preferences-saved');

    $tesco = $this->user->followedChains()->where('chain', Chain::Tesco)->sole();
    expect($this->user->followedChains()->count())->toBe(2)
        ->and($tesco->store_format)->toBe(StoreFormat::Supermarket)
        ->and($tesco->include_online_only)->toBeFalse()
        ->and($this->user->stores()->pluck('stores.id')->all())->toBe([$store->id])
        ->and($this->user->fresh()?->hasLoyaltyProgram(LoyaltyProgram::Clubcard))->toBeTrue();
});

it('po zrušení sledování obchodu odebere i jeho prodejny', function (): void {
    $store = Store::factory()->of(Chain::Kaufland)->create();
    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true]],
        'store_ids' => [$store->id],
        'loyalty_programs' => [],
    ]);

    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'tesco', 'store_format' => null, 'include_online_only' => true]],
        'store_ids' => [$store->id],
        'loyalty_programs' => [],
    ]);

    expect($this->user->followedChains()->pluck('chain')->all())->toBe([Chain::Tesco])
        ->and($this->user->stores()->count())->toBe(0);
});

it('nedovolí sledovat obchod bez zdroje nabídek', function (): void {
    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'albert', 'store_format' => null, 'include_online_only' => true]],
        'store_ids' => [],
        'loyalty_programs' => [],
    ])->assertSessionHasErrors('chains.0.chain');

    expect($this->user->followedChains()->count())->toBe(0);
});
