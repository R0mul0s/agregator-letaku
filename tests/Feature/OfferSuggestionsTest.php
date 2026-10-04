<?php

/**
 * Našeptávač hledání ve Všech akcích (R71): produkty katalogu s počtem akcí a cenou,
 * první akce podle relevance, oprava překlepu a oblíbené produkty pro prázdné pole.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::flush();
});

/**
 * Přiřadí akci k produktu katalogu (jako AssignProducts).
 */
function assignToProduct(Offer $offer, Product $product): void
{
    OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
}

it('navrhne produkty katalogu s počtem akcí a nejnižší cenou, produkt bez akcí ne', function (): void {
    $butter = Product::factory()->create(['name' => 'Máslo']);
    Product::factory()->create(['name' => 'Máslo arašídové']);
    assignToProduct(Offer::factory()->create(['name' => 'Tatra máslo', 'price' => 4990]), $butter);
    assignToProduct(Offer::factory()->create(['name' => 'Madeta máslo', 'price' => 3990]), $butter);
    assignToProduct(Offer::factory()->create(['name' => 'Máslo skončené', 'price' => 990, 'valid_from' => '2026-09-20', 'valid_to' => '2026-10-01']), $butter);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'product_id' => $butter->id, 'keywords' => null]);

    $this->getJson(route('offers.suggestions', ['q' => 'maslo']))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.name', 'Máslo')
        ->assertJsonPath('products.0.offersCount', 2)
        ->assertJsonPath('products.0.lowestPrice', 3990)
        ->assertJsonPath('products.0.url', '/akce?produkt='.$butter->id)
        ->assertJsonPath('products.0.watched', true)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('corrected', null);
});

it('akce v návrzích řadí podle relevance — shoda jen v popisu až nakonec', function (): void {
    Offer::factory()->create(['name' => 'Coca-Cola 1l', 'description' => 'MENU PIZZA+COLA', 'discount_percent' => 50]);
    Offer::factory()->create(['name' => 'Dr. Oetker Pizza Speciale', 'discount_percent' => 20]);
    Offer::factory()->create(['name' => 'Pizza šunková', 'discount_percent' => 10]);

    $this->getJson(route('offers.suggestions', ['q' => 'pizza']))
        ->assertJsonPath('offers.*.name', ['Pizza šunková', 'Dr. Oetker Pizza Speciale', 'Coca-Cola 1l'])
        ->assertJsonPath('total', 3);
});

it('hledá jen od začátku slova — „kola“ nenajde čokoládu, ale opraví se na Colu', function (): void {
    Offer::factory()->create(['name' => 'Milka čokoláda']);
    Offer::factory()->create(['name' => 'Coca-Cola Zero']);

    $this->getJson(route('offers.suggestions', ['q' => 'cola']))->assertJsonPath('offers.*.name', ['Coca-Cola Zero']);
    $this->getJson(route('offers.suggestions', ['q' => 'kola']))
        ->assertJsonPath('corrected', 'cola')
        ->assertJsonPath('offers.*.name', ['Coca-Cola Zero']);
});

it('návrhy omezí na zvolený obchod a krátký text nenašeptává', function (): void {
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);

    $this->getJson(route('offers.suggestions', ['q' => 'vejce', 'chain' => 'tesco']))
        ->assertJsonPath('offers.*.name', ['Vejce Tesco']);

    $this->getJson(route('offers.suggestions', ['q' => 'v']))
        ->assertJsonPath('products', [])
        ->assertJsonPath('offers', []);
});

it('překlep opraví podle slov aktuálních akcí', function (): void {
    Offer::factory()->create(['name' => 'Pizza Margherita']);
    Offer::factory()->create(['name' => 'Pivo Gambrinus']);

    $this->getJson(route('offers.suggestions', ['q' => 'pyzza']))
        ->assertJsonPath('corrected', 'pizza')
        ->assertJsonPath('offers.0.name', 'Pizza Margherita');
});

it('prázdné pole dostane oblíbené produkty — ty s nejvíc akcemi', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    $milk = Product::factory()->create(['name' => 'Mléko']);
    assignToProduct(Offer::factory()->create(['name' => 'Pivo A']), $beer);
    assignToProduct(Offer::factory()->create(['name' => 'Pivo B']), $beer);
    assignToProduct(Offer::factory()->create(['name' => 'Mléko A']), $milk);

    $this->getJson(route('offers.suggestions'))
        ->assertJsonPath('popular', true)
        ->assertJsonPath('products.*.name', ['Pivo', 'Mléko']);
});

it('našeptávač relaci nezapisuje — souběžné uložení nepřijde o zprávu pro toast (R71)', function (): void {
    $this->withSession(['status' => 'watch-item-added'])
        ->getJson(route('offers.suggestions', ['q' => 'vejce']))
        ->assertOk();

    expect(app('session.store')->getHandler())->toBeInstanceOf(NullSessionHandler::class);
});
