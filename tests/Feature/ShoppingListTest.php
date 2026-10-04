<?php

/**
 * Nákupní seznam (R61): přidání a odebrání z karty akce, odškrtnutí, úklid po nákupu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Http\Controllers\ShoppingListController;
use App\Models\Offer;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('tlačítko na kartě akci přidá a podruhé odebere, stránka zůstane', function (): void {
    $offer = Offer::factory()->create();

    $this->from(route('offers'))->post(route('shopping-list.toggle'), ['offer_id' => $offer->id])
        ->assertRedirect(route('offers'))
        ->assertSessionHas('status', ShoppingListController::STATUS_ADDED);
    expect($this->user->shoppingListItems()->sole()->offer_id)->toBe($offer->id);

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('shoppingList.offerIds', [$offer->id]));

    $this->from(route('offers'))->post(route('shopping-list.toggle'), ['offer_id' => $offer->id])
        ->assertSessionHas('status', ShoppingListController::STATUS_REMOVED);
    expect($this->user->shoppingListItems()->count())->toBe(0);
});

it('ukáže seznam po obchodech, v obchodě nejdřív nekoupené, skončenou akci označí', function (): void {
    $milk = Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Mléko']);
    $butter = Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Máslo']);
    $eggs = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Vejce', 'valid_from' => CarbonImmutable::today()->subWeek(), 'valid_to' => CarbonImmutable::yesterday()]);
    $this->user->shoppingListItems()->create(['offer_id' => $butter->id, 'checked_at' => now()]);
    $this->user->shoppingListItems()->create(['offer_id' => $milk->id]);
    $this->user->shoppingListItems()->create(['offer_id' => $eggs->id]);

    $this->get(route('shopping-list.index'))->assertInertia(fn (Assert $page) => $page
        ->component('ShoppingList')
        ->where('groups.0.chain', 'kaufland')
        ->where('groups.0.items.0.expired', true)
        ->where('groups.1.chain', 'lidl')
        ->where('groups.1.items.0.offer.name', 'Mléko')
        ->where('groups.1.items.1.offer.name', 'Máslo')
        ->where('groups.1.items.1.checked', true)
        ->where('hasChecked', true));
});

it('odškrtne položku, smaže ji a po nákupu smaže všechny odškrtnuté', function (): void {
    $bought = $this->user->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id]);
    $left = $this->user->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id]);
    $removed = $this->user->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id]);

    $this->patch(route('shopping-list.update', $bought), ['checked' => true]);
    expect($bought->fresh()?->checked_at)->not->toBeNull();

    $this->delete(route('shopping-list.destroy', $removed));
    $this->delete(route('shopping-list.clear-checked'))->assertSessionHas('status', ShoppingListController::STATUS_CLEARED);

    expect(ShoppingListItem::query()->pluck('id')->all())->toBe([$left->id]);
});

it('cizí položku neodškrtne ani nesmaže', function (): void {
    $foreign = User::factory()->create()->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id]);

    $this->patch(route('shopping-list.update', $foreign), ['checked' => true])->assertForbidden();
    $this->delete(route('shopping-list.destroy', $foreign))->assertForbidden();
    $this->delete(route('shopping-list.clear-checked'));

    expect($foreign->fresh())->not->toBeNull();
});

it('nad limit nepřidá, ale odebrat jde vždy', function (): void {
    config(['letaky.shopping_list.max_items' => 1]);
    $first = Offer::factory()->create();
    $this->post(route('shopping-list.toggle'), ['offer_id' => $first->id]);

    $this->post(route('shopping-list.toggle'), ['offer_id' => Offer::factory()->create()->id])->assertSessionHasErrors('offer_id');
    $this->post(route('shopping-list.toggle'), ['offer_id' => $first->id])->assertSessionHasNoErrors();

    expect($this->user->shoppingListItems()->count())->toBe(0);
});

it('nepřihlášený seznam nemá', function (): void {
    auth()->logout();

    $this->get(route('shopping-list.index'))->assertRedirect(route('login'));
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('shoppingList', null));
});
