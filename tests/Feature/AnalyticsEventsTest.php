<?php

/**
 * Události pro Google Analytics z odpovědi serveru (R134): přidání hlídané položky a položky
 * na nákupní seznam, sdílený prop analyticsEvents jen na příští stránce. Registrace je
 * v RegistrationTest a SocialLoginTest.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Support\AnalyticsEvents;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('přidání hlídané položky ohlásí druh položky, ne její název ani slova', function (): void {
    $product = Product::factory()->create(['name' => 'Vejce']);

    $this->post(route('watch-items.store'), ['name' => 'Vejce', 'product_id' => $product->id])
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'add_watch_item', 'params' => ['item_type' => 'product']]]);
    $this->post(route('watch-items.store'), ['name' => 'Coca-Cola Zero', 'keywords' => 'coca cola'])
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'add_watch_item', 'params' => ['item_type' => 'keywords']]]);
});

it('neuložená hlídaná položka nic neohlásí', function (): void {
    $this->post(route('watch-items.store'), ['name' => 'Bez slov', 'keywords' => ''])
        ->assertSessionHasErrors('keywords')
        ->assertSessionMissing(AnalyticsEvents::SESSION_KEY);
});

it('přidání akce na seznam ohlásí obchod, odebrání nic', function (): void {
    $offer = Offer::factory()->create(['chain' => Chain::Lidl]);

    $this->post(route('shopping-list.toggle'), ['offer_id' => $offer->id])
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'add_to_shopping_list', 'params' => ['item_type' => 'offer', 'chain' => 'lidl']]]);
    $this->post(route('shopping-list.toggle'), ['offer_id' => $offer->id])
        ->assertSessionMissing(AnalyticsEvents::SESSION_KEY);
});

it('vlastní položka bez obchodu se ohlásí bez obchodu, stejná nekoupená podruhé ne', function (): void {
    $this->post(route('shopping-list.custom'), ['name' => 'Toaletní papír'])
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'add_to_shopping_list', 'params' => ['item_type' => 'custom']]]);
    $this->post(route('shopping-list.custom'), ['name' => 'Toaletní papír'])
        ->assertSessionMissing(AnalyticsEvents::SESSION_KEY);
});

it('událost dostane jen příští stránka, další už ne', function (): void {
    $offer = Offer::factory()->create(['chain' => Chain::Kaufland]);

    $this->from(route('offers'))->post(route('shopping-list.toggle'), ['offer_id' => $offer->id]);

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('analyticsEvents', [['name' => 'add_to_shopping_list', 'params' => ['item_type' => 'offer', 'chain' => 'kaufland']]]));
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('analyticsEvents', []));
});
