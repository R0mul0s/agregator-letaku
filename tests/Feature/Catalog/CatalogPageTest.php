<?php

/**
 * Stránky katalogu produktů pro admina (R29, R30).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductExclusion;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('katalog je jen pro admina a jen admin ho má v navigaci', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('catalog.index'))->assertForbidden();
    $this->actingAs($user)->post(route('catalog.store'), ['name' => 'X', 'keywords' => 'x'])->assertForbidden();
    $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('navigation', fn ($items): bool => ! collect($items)->contains('label', 'nav.catalog')));

    $this->actingAs($this->admin)->get(route('catalog.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('navigation', fn ($items): bool => collect($items)->contains('label', 'nav.catalog')));
});

it('ukáže produkty s cestou kategorie a počtem přiřazených akcí', function (): void {
    $dairy = Category::factory()->create(['name' => 'Mléčné, vejce a margaríny']);
    $butterSection = Category::factory()->childOf($dairy)->create(['name' => 'Máslo']);
    $butterShelf = Category::factory()->childOf($butterSection)->create(['name' => 'Máslo']);
    $product = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo', 'category_id' => $butterShelf->id]);
    Offer::factory()->create(['name' => 'Tatra máslo']);
    Offer::factory()->create(['name' => 'Máslo různé druhy', 'variant_note' => 'různé druhy']);
    $this->actingAs($this->admin)->put(route('catalog.update', $product), ['name' => 'Máslo', 'keywords' => 'máslo', 'category_id' => $butterShelf->id]);

    $this->actingAs($this->admin)->get(route('catalog.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Catalog/Index')
        ->where('products.0.categoryLabel', 'Mléčné, vejce a margaríny › Máslo')
        ->where('products.0.matchCount', 2)
        ->where('products.0.maybeCount', 0)
        ->where('products.0.department', 'Mléčné, vejce a margaríny')
        ->where('products.0.categoryName', 'Máslo')
        ->where('products.0.watchersCount', 0)
        // Regál a police „Máslo › Máslo“ mají stejný popisek — ve výběru jen jednou
        ->has('categories', 2)
        ->where('categories.1.label', 'Mléčné, vejce a margaríny › Máslo'));

    // V detailu produktu se jeho kategorie (police) ukáže, i když má stejný popisek jako regál
    $this->actingAs($this->admin)->get(route('catalog.show', $product))->assertInertia(fn (Assert $page) => $page
        ->has('categories', 3)
        ->where('categories.2.id', $butterShelf->id));
});

it('hledá v názvu, slovech i cestě kategorie bez diakritiky a filtruje oddělení (R43)', function (): void {
    $drinks = Category::factory()->create(['name' => 'Nápoje']);
    $beer = Category::factory()->childOf($drinks)->create(['name' => 'Pivo']);
    $dairy = Category::factory()->create(['name' => 'Mléčné výrobky']);
    Product::factory()->create(['name' => 'Ležák', 'keywords' => 'lezak', 'category_id' => $beer->id]);
    Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo', 'category_id' => $dairy->id]);
    Product::factory()->create(['name' => 'Kofola', 'keywords' => 'kofola', 'category_id' => null]);
    $names = fn (string $url): array => array_column($this->actingAs($this->admin)->get($url)->viewData('page')['props']['products'], 'name');

    expect($names('/katalog?q=napoje'))->toBe(['Ležák'])
        ->and($names('/katalog?q=MASLO'))->toBe(['Máslo'])
        ->and($names('/katalog?q=pivo+lezak'))->toBe(['Ležák'])
        ->and($names('/katalog?oddeleni=Mléčné+výrobky'))->toBe(['Máslo']);

    $this->actingAs($this->admin)->get('/katalog')->assertInertia(fn (Assert $page) => $page
        ->where('departments', ['Mléčné výrobky', 'Nápoje'])
        ->where('total', 3)
        ->where('hasProducts', true));
});

it('řadí na serveru podle názvu, kategorie a počtu hlídajících (R43)', function (): void {
    $a = Category::factory()->create(['name' => 'A oddělení']);
    $z = Category::factory()->create(['name' => 'Z oddělení']);
    Product::factory()->create(['name' => 'Bez kategorie', 'category_id' => null]);
    Product::factory()->create(['name' => 'Cé', 'category_id' => $a->id]);
    $watched = Product::factory()->create(['name' => 'Áčko', 'category_id' => $z->id]);
    WatchItem::factory()->create(['product_id' => $watched->id, 'keywords' => null]);
    $names = fn (string $url): array => array_column($this->actingAs($this->admin)->get($url)->viewData('page')['props']['products'], 'name');

    expect($names('/katalog'))->toBe(['Áčko', 'Bez kategorie', 'Cé'])
        ->and($names('/katalog?razeni=category'))->toBe(['Cé', 'Áčko', 'Bez kategorie'])
        ->and($names('/katalog?razeni=category&smer=desc'))->toBe(['Áčko', 'Cé', 'Bez kategorie'])
        ->and($names('/katalog?razeni=watchers'))->toBe(['Áčko', 'Bez kategorie', 'Cé']);
});

it('stránkuje s „Načíst další“ a zachová hledání a řazení v odkazech (R43)', function (): void {
    config(['letaky.catalog.per_page' => 2]);
    Product::factory()->count(5)->sequence(fn ($sequence): array => ['name' => 'Pivo '.$sequence->index])->create();

    $this->actingAs($this->admin)->get('/katalog?q=pivo&razeni=watchers')->assertInertia(fn (Assert $page) => $page
        ->has('products', 2)
        ->where('total', 5)
        ->where('pagination.loadMoreUrl', '/katalog?q=pivo&razeni=watchers&od=1&strana=2'));

    $this->actingAs($this->admin)->get('/katalog?od=1&strana=3')->assertInertia(fn (Assert $page) => $page
        ->has('products', 5)
        ->where('pagination.loadMoreUrl', null));
});

it('založí produkt, přiřadí mu akce a otevře jeho detail', function (): void {
    Offer::factory()->create(['name' => 'Čerstvá vejce']);

    $this->actingAs($this->admin)
        ->post(route('catalog.store'), ['name' => 'Vejce', 'keywords' => 'vejce', 'exclude_keywords' => 'těstoviny'])
        ->assertRedirect(route('catalog.show', Product::query()->sole()));

    expect(OfferProduct::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('catalog.store'), ['name' => 'Vejce', 'keywords' => 'vejce'])
        ->assertSessionHasErrors('name');
});

it('v detailu najde akce k ručnímu přiřazení a opraví přiřazení', function (): void {
    $product = Product::factory()->create(['name' => 'Vejce', 'keywords' => 'vejce']);
    $eggs = Offer::factory()->create(['name' => 'Čerstvá vejce']);
    $quail = Offer::factory()->create(['name' => 'Křepelčí vajíčka']);
    $this->actingAs($this->admin)->put(route('catalog.update', $product), ['name' => 'Vejce', 'keywords' => 'vejce']);

    $this->actingAs($this->admin)->get(route('catalog.show', [$product, 'hledat' => 'vajíčka']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')
            ->has('assigned', 1)
            ->where('assigned.0.name', 'Čerstvá vejce')
            ->has('search.results', 1)
            ->where('search.results.0.includeUrl', route('catalog.offers.include', [$product, $quail], absolute: false)));

    $this->actingAs($this->admin)->post(route('catalog.offers.include', [$product, $quail]))->assertRedirect();
    $this->actingAs($this->admin)->delete(route('catalog.offers.exclude', [$product, $eggs]))->assertRedirect();

    expect(OfferProduct::query()->pluck('offer_id')->all())->toBe([$quail->id])
        ->and(OfferProductExclusion::query()->pluck('offer_id')->all())->toBe([$eggs->id]);

    $this->actingAs($this->admin)->delete(route('catalog.offers.restore', [$product, $eggs]))->assertRedirect();

    expect(OfferProduct::query()->orderBy('offer_id')->pluck('offer_id')->all())->toBe([$eggs->id, $quail->id])
        ->and(OfferProductExclusion::query()->count())->toBe(0);
});

it('smaže produkt i s přiřazením a hlídaným položkám nechá jeho pravidla', function (): void {
    $product = Product::factory()->create(['keywords' => 'vejce', 'exclude_keywords' => 'těstoviny']);
    $offer = Offer::factory()->create(['name' => 'Vejce']);
    OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => 'match']);
    $watchItem = WatchItem::factory()->create(['product_id' => $product->id, 'keywords' => null]);

    $this->actingAs($this->admin)->delete(route('catalog.destroy', $product))->assertRedirect(route('catalog.index'));

    expect(Product::query()->count())->toBe(0)
        ->and(OfferProduct::query()->count())->toBe(0)
        ->and($watchItem->refresh())
        ->product_id->toBeNull()
        ->keywords->toBe('vejce')
        ->exclude_keywords->toBe('těstoviny');
});

it('příkaz letaky:admin udělí a odebere správu katalogu', function (): void {
    $user = User::factory()->create(['email' => 'roman@example.com']);

    $this->artisan('letaky:admin', ['email' => 'roman@example.com'])->assertSuccessful();
    expect($user->refresh()->is_admin)->toBeTrue();

    $this->artisan('letaky:admin', ['email' => 'roman@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->refresh()->is_admin)->toBeFalse();

    $this->artisan('letaky:admin', ['email' => 'nikdo@example.com'])->assertFailed();
});
