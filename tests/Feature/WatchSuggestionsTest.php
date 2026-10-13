<?php

/**
 * Návrhy, co hlídat (R148): co si uživatel opakovaně dává do nákupního seznamu se nabídne
 * v Hlídám i v seznamu; „Ne, díky“ a začátek hlídání návrh zavřou, úklid staré návrhy smaže.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Account\Actions\RunMaintenance;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchSuggestion;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-13 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Přidá vlastní položku do seznamu, odškrtne a smaže ji (jako po nákupu) a posune čas o den.
 */
function buyCustomItem(string $name): void
{
    test()->post(route('shopping-list.custom'), ['name' => $name]);
    test()->user->shoppingListItems()->update(['checked_at' => now()]);
    test()->delete(route('shopping-list.clear-checked'));
    test()->travel(1)->day();
}

/**
 * Návrhy na stránce.
 *
 * @return list<array<string, mixed>>
 */
function suggestionsOn(string $route): array
{
    $items = [];
    test()->get(route($route))->assertInertia(function (Assert $page) use (&$items): void {
        $items = $page->toArray()['props']['watchSuggestions']['items'];
    });

    return $items;
}

it('vlastní položku koupenou podruhé nabídne v seznamu i v Hlídám', function (): void {
    buyCustomItem('Almette');
    expect(suggestionsOn('shopping-list.index'))->toBe([]);

    buyCustomItem('almette ');

    $suggestion = WatchSuggestion::query()->sole();
    expect(suggestionsOn('shopping-list.index'))->toBe([[
        'id' => $suggestion->id,
        'name' => 'almette',
        'productId' => null,
        'hits' => 2,
        'dismissUrl' => route('watch-suggestions.destroy', $suggestion, absolute: false),
    ]])->and(suggestionsOn('watch-items.index'))->toHaveCount(1);
});

it('vlastní položku se jménem produktu katalogu nabídne jako produkt', function (): void {
    $milk = Product::factory()->create(['name' => 'Mléko']);

    buyCustomItem('mleko');
    buyCustomItem('Mléko');

    expect(suggestionsOn('watch-items.index'))->sequence(fn ($item) => $item->name->toBe('Mléko')->productId->toBe($milk->id));
});

it('stejnou položku přidanou znovu týž den započítá jen jednou', function (): void {
    $this->post(route('shopping-list.custom'), ['name' => 'Almette']);
    $this->user->shoppingListItems()->delete();
    $this->post(route('shopping-list.custom'), ['name' => 'Almette']);

    expect(WatchSuggestion::query()->sole()->hits)->toBe(1)
        ->and(suggestionsOn('shopping-list.index'))->toBe([]);
});

it('akci z karty započítá podle produktu katalogu, akci bez produktu ne', function (): void {
    $butter = Product::factory()->create(['name' => 'Máslo']);
    foreach (['Máslo Tatra', 'Máslo Madeta'] as $name) {
        $offer = Offer::factory()->create(['name' => $name]);
        OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $butter->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
        $this->post(route('shopping-list.toggle'), ['offer_id' => $offer->id]);
        $this->travel(1)->day();
    }
    $this->post(route('shopping-list.toggle'), ['offer_id' => Offer::factory()->create(['name' => 'Zubní pasta'])->id]);

    expect(suggestionsOn('shopping-list.index'))->sequence(fn ($item) => $item->productId->toBe($butter->id)->hits->toBe(2));
});

it('po začátku hlídání návrh zmizí a po smazání hlídání se nevrátí', function (): void {
    buyCustomItem('Almette');
    buyCustomItem('Almette');

    $this->post(route('watch-items.store'), ['name' => 'Almette', 'keywords' => 'almette']);
    expect(suggestionsOn('watch-items.index'))->toBe([]);

    $this->user->watchItems()->delete();
    expect(suggestionsOn('watch-items.index'))->toBe([]);
});

it('„Ne, díky“ návrh zavře natrvalo, cizí návrh nezavře', function (): void {
    buyCustomItem('Almette');
    buyCustomItem('Almette');
    $suggestion = WatchSuggestion::query()->sole();

    $this->actingAs(User::factory()->create())->delete(route('watch-suggestions.destroy', $suggestion))->assertForbidden();
    $this->actingAs($this->user)->from(route('shopping-list.index'))
        ->delete(route('watch-suggestions.destroy', $suggestion))
        ->assertRedirect(route('shopping-list.index'));
    buyCustomItem('Almette');

    expect(suggestionsOn('shopping-list.index'))->toBe([])
        ->and($suggestion->fresh()?->dismissed_at)->not->toBeNull();
});

it('po okně bez nákupu počítá znovu a úklid staré návrhy smaže', function (): void {
    buyCustomItem('Almette');
    buyCustomItem('Almette');
    $this->travel(config()->integer('letaky.watch_suggestions.window_days') + 1)->days();

    expect(suggestionsOn('shopping-list.index'))->toBe([]);
    buyCustomItem('Almette');
    expect(WatchSuggestion::query()->sole()->hits)->toBe(1);

    $this->travel(config()->integer('letaky.watch_suggestions.window_days') + 1)->days();
    expect(app(RunMaintenance::class)()['watch_suggestions'])->toBe(1)
        ->and(WatchSuggestion::query()->count())->toBe(0);
});

it('zavřený návrh úklid smaže až po dismiss_days', function (): void {
    buyCustomItem('Almette');
    WatchSuggestion::query()->update(['dismissed_at' => now()]);

    $this->travel(config()->integer('letaky.watch_suggestions.window_days') + 1)->days();
    app(RunMaintenance::class)();
    expect(WatchSuggestion::query()->count())->toBe(1);

    $this->travel(config()->integer('letaky.watch_suggestions.dismiss_days'))->days();
    app(RunMaintenance::class)();
    expect(WatchSuggestion::query()->count())->toBe(0);
});

it('se zrušením účtu smaže i návrhy', function (): void {
    buyCustomItem('Almette');

    $this->user->delete();

    expect(WatchSuggestion::query()->count())->toBe(0);
});
