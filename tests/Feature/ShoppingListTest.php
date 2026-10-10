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

it('odškrtnutí bez připojení uloží najednou, cizí a smazané položky přeskočí (R66)', function (): void {
    $milk = $this->user->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id]);
    $butter = $this->user->shoppingListItems()->create(['offer_id' => Offer::factory()->create()->id, 'checked_at' => now()->subHour()]);
    $foreign = ShoppingListItem::query()->create(['user_id' => User::factory()->create()->id, 'offer_id' => Offer::factory()->create()->id]);

    $this->from(route('shopping-list.index'))->patch(route('shopping-list.sync'), ['checks' => [
        ['id' => $milk->id, 'checked' => true],
        ['id' => $butter->id, 'checked' => false],
        ['id' => $foreign->id, 'checked' => true],
        ['id' => 999999, 'checked' => true],
    ]])->assertRedirect(route('shopping-list.index'));

    expect($milk->fresh()?->checked_at?->toDateTimeString())->toBe('2026-10-02 10:00:00')
        ->and($butter->fresh()?->checked_at)->toBeNull()
        ->and($foreign->fresh()?->checked_at)->toBeNull();
});

it('synchronizace bez seznamu změn neprojde', function (): void {
    $this->patch(route('shopping-list.sync'), ['checks' => 'vse'])->assertSessionHasErrors('checks');
});

it('vlastní položku bez akce přidá k obchodu nebo do skupiny Kdekoli, stejnou nekoupenou podruhé ne (R130)', function (): void {
    $milk = Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Mléko']);
    $this->user->shoppingListItems()->create(['offer_id' => $milk->id]);

    $this->from(route('shopping-list.index'))->post(route('shopping-list.custom'), ['name' => '  Almette   bylinková ', 'chain' => 'lidl'])
        ->assertRedirect(route('shopping-list.index'))
        ->assertSessionHas('status', ShoppingListController::STATUS_ADDED);
    $this->post(route('shopping-list.custom'), ['name' => 'Almette bylinková', 'chain' => 'lidl']);
    $this->post(route('shopping-list.custom'), ['name' => 'Toaletní papír']);

    expect($this->user->shoppingListItems()->whereNull('offer_id')->count())->toBe(2);
    $this->get(route('shopping-list.index'))->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.chain', 'lidl')
        ->where('groups.0.items.0.name', 'Almette bylinková')
        ->where('groups.0.items.0.offer', null)
        ->where('groups.0.items.1.name', 'Mléko')
        ->where('groups.1.chain', 'anywhere')
        ->where('groups.1.chainName', 'Kdekoli')
        ->where('groups.1.items.0.name', 'Toaletní papír')
        // Tlačítko na kartě akce zná jen akce
        ->where('shoppingList.offerIds', [$milk->id]));
});

it('vlastní položka potřebuje název, platný obchod a místo v seznamu (R130)', function (): void {
    $this->post(route('shopping-list.custom'), ['name' => ''])->assertSessionHasErrors('name');
    $this->post(route('shopping-list.custom'), ['name' => 'Almette', 'chain' => 'makro'])->assertSessionHasErrors('chain');

    config(['letaky.shopping_list.max_items' => 1]);
    $this->user->shoppingListItems()->create(['custom_name' => 'Chleba']);
    $this->post(route('shopping-list.custom'), ['name' => 'Almette'])->assertSessionHasErrors('name');
});

it('seznam sdílený odkazem: partner bez účtu vidí seznam a odškrtává, mazat nemůže (R130)', function (): void {
    $this->user->forceFill(['name' => 'Roman Hlaváček'])->save();
    $milk = Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Mléko']);
    $item = $this->user->shoppingListItems()->create(['offer_id' => $milk->id]);
    $this->user->shoppingListItems()->create(['custom_name' => 'Almette']);

    $shareUrl = null;
    $this->get(route('shopping-list.index'))->assertInertia(function (Assert $page) use (&$shareUrl): void {
        $shareUrl = $page->toArray()['props']['share']['url'];
    });
    expect($shareUrl)->toStartWith(url('/seznam/s/'));

    auth()->logout();
    $html = $this->get($shareUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('SharedShoppingList')
        ->where('ownerName', 'Roman')
        ->where('groups.0.chain', 'lidl')
        ->where('groups.0.items.0.deleteUrl', null)
        ->where('groups.1.items.0.name', 'Almette'))
        ->getContent();
    // Soukromá stránka: ani e-mail vlastníka, ani indexace
    expect($html)->not->toContain($this->user->email)
        ->toContain('<meta name="robots" content="noindex, nofollow">');

    $this->patch($shareUrl.'/'.$item->id, ['checked' => true])->assertRedirect();
    expect($item->fresh()?->checked_at)->not->toBeNull();

    // Cizí položka přes tento odkaz ne
    $foreign = User::factory()->create()->shoppingListItems()->create(['custom_name' => 'Cizí']);
    $this->patch($shareUrl.'/'.$foreign->id, ['checked' => true])->assertNotFound();
    expect($foreign->fresh()?->checked_at)->toBeNull();
});

it('nový odkaz zneplatní dosud poslaný, neplatný odkaz je 404 (R130)', function (): void {
    $old = route('shopping-list.shared', ['token' => $this->user->shoppingShareToken()]);

    $this->post(route('shopping-list.share.renew'))->assertSessionHas('status', ShoppingListController::STATUS_SHARE_RENEWED);

    $new = route('shopping-list.shared', ['token' => $this->user->fresh()?->shoppingShareToken()]);
    expect($new)->not->toBe($old);
    $this->get($old)->assertNotFound();
    $this->get($new)->assertOk();
    $this->get('/seznam/s/neplatny')->assertNotFound();
});

it('sdílený seznam jde do analytiky bez tokenu v adrese (R130)', function (): void {
    expect(config('letaky.cookie_consent.redacted_paths'))->toContain('/seznam/s');
});

it('smaže skončené akce, platné akce a vlastní položky nechá (R130)', function (): void {
    $ended = Offer::factory()->create(['valid_from' => CarbonImmutable::today()->subWeek(), 'valid_to' => CarbonImmutable::yesterday()]);
    $current = Offer::factory()->create();
    $this->user->shoppingListItems()->create(['offer_id' => $ended->id]);
    $this->user->shoppingListItems()->create(['offer_id' => $current->id]);
    $this->user->shoppingListItems()->create(['custom_name' => 'Almette']);

    $this->get(route('shopping-list.index'))->assertInertia(fn (Assert $page) => $page->where('hasExpired', true));

    $this->delete(route('shopping-list.clear-expired'))->assertSessionHas('status', ShoppingListController::STATUS_EXPIRED_CLEARED);

    expect($this->user->shoppingListItems()->pluck('offer_id')->all())->toEqualCanonicalizing([$current->id, null]);
    $this->get(route('shopping-list.index'))->assertInertia(fn (Assert $page) => $page->where('hasExpired', false));
});
