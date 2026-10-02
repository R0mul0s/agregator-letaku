<?php

/**
 * Stránka Hlídám — přidání, úprava a smazání hlídaných položek (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('ukáže jen vlastní položky a produkty katalogu', function (): void {
    $product = Product::factory()->create(['name' => 'Máslo']);
    WatchItem::factory()->for($this->user)->create(['name' => 'Moje vejce']);
    WatchItem::factory()->for($this->user)->create(['name' => 'Moje máslo', 'product_id' => $product->id, 'keywords' => null]);
    WatchItem::factory()->create(['name' => 'Cizí vejce']);

    $this->get(route('watch-items.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WatchItems')
            ->has('watchItems', 2)
            ->where('watchItems.0.name', 'Moje máslo')
            ->where('watchItems.0.productName', 'Máslo')
            ->where('watchItems.1.name', 'Moje vejce')
            ->where('watchItems.1.productId', null)
            ->where('products.0.name', 'Máslo'));
});

it('u položky ukáže počet aktuálních akcí a nejnižší cenu, kterou uživatel zaplatí', function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Tesco, 'include_online_only' => true]);
    $this->user->update(['loyalty_programs' => [LoyaltyProgram::Clubcard]]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Mléko', 'keywords' => 'mléko']);
    WatchItem::factory()->for($this->user)->create(['name' => 'Vejce', 'keywords' => 'vejce']);
    Offer::factory()->create(['name' => 'Mléko polotučné', 'chain' => Chain::Tesco, 'price' => 1990]);
    Offer::factory()->create(['name' => 'Mléko s Clubcard', 'chain' => Chain::Tesco, 'price' => 2490, 'loyalty_price' => 1490, 'loyalty_program' => LoyaltyProgram::Clubcard]);

    $this->get(route('watch-items.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('watchItems.0.name', 'Mléko')
            ->where('watchItems.0.offersCount', 2)
            ->where('watchItems.0.lowestPrice', 1490)
            ->where('watchItems.1.offersCount', 0)
            ->where('watchItems.1.lowestPrice', null));
});

it('přidá položku z katalogu bez vlastních slov', function (): void {
    $product = Product::factory()->create(['name' => 'Vejce']);

    $this->post(route('watch-items.store'), [
        'name' => 'Vejce',
        'product_id' => $product->id,
        'keywords' => 'zapomenutá slova',
    ])->assertRedirect(route('watch-items.index'));

    expect($this->user->watchItems()->sole())
        ->product_id->toBe($product->id)
        ->keywords->toBeNull();

    $this->post(route('watch-items.store'), ['name' => 'Neexistující', 'product_id' => 999999])
        ->assertSessionHasErrors('product_id');
});

it('přidá položku', function (): void {
    $this->post(route('watch-items.store'), [
        'name' => 'Coca-Cola Zero',
        'keywords' => 'coca cola',
        'variant_keywords' => 'zero',
        'exclude_keywords' => '',
    ])->assertRedirect(route('watch-items.index'));

    expect($this->user->watchItems()->sole())
        ->name->toBe('Coca-Cola Zero')
        ->keywords->toBe('coca cola')
        ->variant_keywords->toBe('zero')
        ->exclude_keywords->toBeNull();
});

it('bez hledaných slov položku nepřidá a chybu napíše česky', function (): void {
    $this->post(route('watch-items.store'), ['name' => 'Vejce', 'keywords' => ''])
        ->assertSessionHasErrors(['keywords' => 'Zadejte hledaná slova, nebo vyberte produkt z katalogu.']);

    expect(WatchItem::query()->count())->toBe(0);
});

it('nad limit položku nepřidá', function (): void {
    config(['letaky.watch.max_items_per_user' => 1]);
    WatchItem::factory()->for($this->user)->create();

    $this->post(route('watch-items.store'), ['name' => 'Máslo', 'keywords' => 'máslo'])
        ->assertSessionHasErrors('name');

    expect($this->user->watchItems()->count())->toBe(1);
});

it('upraví a smaže vlastní položku', function (): void {
    $item = WatchItem::factory()->for($this->user)->create();

    $this->put(route('watch-items.update', $item), ['name' => 'Vejce M', 'keywords' => 'vejce', 'exclude_keywords' => 'maggi'])
        ->assertRedirect(route('watch-items.index'));
    expect($item->fresh())
        ->name->toBe('Vejce M')
        ->exclude_keywords->toBe('maggi');

    $this->delete(route('watch-items.destroy', $item))->assertRedirect(route('watch-items.index'));
    expect(WatchItem::query()->count())->toBe(0);
});

it('z Mých slev přestane hlídat a vrátí se zpět; odkaz Upravit otevře úpravu v Hlídám (R43)', function (): void {
    $item = WatchItem::factory()->for($this->user)->create(['name' => 'Vejce', 'keywords' => 'vejce']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('watchItems.0.fromCatalog', false)
        ->where('watchItems.0.editUrl', '/hlidam?upravit='.$item->id)
        ->where('watchItems.0.deleteUrl', '/hlidam/'.$item->id)
        ->where('watchItems.0.lowestPrice', null));

    $this->get('/hlidam?upravit='.$item->id)->assertInertia(fn (Assert $page) => $page->where('editId', $item->id));

    $this->from(route('home'))->delete(route('watch-items.destroy', $item))->assertRedirect(route('home'));
    expect(WatchItem::query()->count())->toBe(0);
});

it('cizí položku neupraví ani nesmaže', function (): void {
    $foreign = WatchItem::factory()->create(['name' => 'Cizí']);

    $this->put(route('watch-items.update', $foreign), ['name' => 'Moje', 'keywords' => 'vejce'])->assertForbidden();
    $this->delete(route('watch-items.destroy', $foreign))->assertForbidden();

    expect($foreign->fresh()?->name)->toBe('Cizí');
});

it('stejný produkt z katalogu nepřidá podruhé a v katalogu ho označí jako hlídaný', function (): void {
    $product = Product::factory()->create(['name' => 'Vejce']);
    $this->post(route('watch-items.store'), ['name' => 'Vejce', 'product_id' => $product->id]);

    $this->post(route('watch-items.store'), ['name' => 'Vejce znovu', 'product_id' => $product->id])
        ->assertSessionHasErrors(['product_id' => 'Tenhle produkt už hlídáte.']);

    expect($this->user->watchItems()->count())->toBe(1);
    $this->get(route('watch-items.index'))->assertInertia(fn (Assert $page) => $page->where('products.0.watched', true));

    // Jiný uživatel stejný produkt hlídat může
    $this->actingAs(User::factory()->create())
        ->post(route('watch-items.store'), ['name' => 'Vejce', 'product_id' => $product->id])
        ->assertSessionHasNoErrors();
});
