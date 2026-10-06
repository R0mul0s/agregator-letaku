<?php

/**
 * Živá ukázka hlídání na úvodní stránce (R90): počet dnes platných akcí vybraných produktů
 * ve vybraných obchodech a nejlevnější akce za jednotku po jedné na produkt.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Enums\PackageUnit;
use App\Http\Middleware\ReadOnlySession;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Akce přiřazená k produktu katalogu (jako AssignProducts).
 *
 * @param  array<string, mixed>  $attributes
 */
function demoOffer(Product $product, array $attributes): Offer
{
    $offer = Offer::factory()->create($attributes);
    OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    return $offer;
}

it('spočítá dnes platné akce a ukáže nejlevnější za jednotku, ne nejlevnější balení', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    // 0,5 l za 19,90 = 39,80 Kč/l; 2 l za 59,90 = 29,95 Kč/l — dražší balení je levnější za litr
    demoOffer($beer, ['name' => 'Pivo malé', 'price' => 1990, 'quantity' => 500, 'unit' => PackageUnit::Milliliter]);
    demoOffer($beer, ['name' => 'Pivo PET', 'price' => 5990, 'quantity' => 2000, 'unit' => PackageUnit::Milliliter, 'chain' => Chain::Lidl]);
    demoOffer($beer, ['name' => 'Pivo budoucí', 'price' => 990, 'valid_from' => '2026-10-05', 'valid_to' => '2026-10-11']);
    demoOffer($beer, ['name' => 'Pivo stažené', 'price' => 990, 'withdrawn_at' => now()]);
    demoOffer($beer, ['name' => 'Pivo skončené', 'price' => 990, 'valid_from' => '2026-09-20', 'valid_to' => '2026-10-01']);

    $this->getJson(route('watch-demo', ['produkty' => (string) $beer->id]))
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonCount(1, 'offers')
        ->assertJsonPath('offers.0.name', 'Pivo PET')
        ->assertJsonPath('offers.0.chainName', 'Lidl');
});

it('bere jen vybrané obchody a nejlevnější akce řadí v pořadí výběru produktů', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    $butter = Product::factory()->create(['name' => 'Máslo']);
    demoOffer($beer, ['name' => 'Pivo Kaufland', 'chain' => Chain::Kaufland]);
    demoOffer($beer, ['name' => 'Pivo Tesco', 'chain' => Chain::Tesco]);
    demoOffer($butter, ['name' => 'Máslo Tesco', 'chain' => Chain::Tesco]);

    $this->getJson(route('watch-demo', ['produkty' => $butter->id.','.$beer->id, 'chain' => 'tesco']))
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('offers.0.name', 'Máslo Tesco')
        ->assertJsonPath('offers.1.name', 'Pivo Tesco');
});

it('odmítne neplatné produkty i obchod a bez produktu nic nehledá', function (): void {
    $this->getJson(route('watch-demo', ['produkty' => 'abc']))->assertUnprocessable();
    $this->getJson(route('watch-demo', ['produkty' => implode(',', range(1, config()->integer('letaky.landing.demo_max_products') + 1))]))->assertUnprocessable();
    $this->getJson(route('watch-demo', ['produkty' => '1', 'chain' => 'makro']))->assertUnprocessable();
    $this->getJson(route('watch-demo'))->assertOk()->assertExactJson(['count' => 0, 'offers' => []]);
});

it('dotaz ukázky relaci jen čte (R71)', function (): void {
    expect(app('router')->getRoutes()->getByName('watch-demo')?->gatherMiddleware())
        ->toContain(ReadOnlySession::class);
});
