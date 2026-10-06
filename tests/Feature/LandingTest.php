<?php

/**
 * Úvodní stránka pro nepřihlášené (R44); přihlášený má na stejné adrese Moje slevy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('nepřihlášenému ukáže úvodní stránku s počty, přihlášenému Moje slevy', function (): void {
    Offer::factory()->count(3)->create();
    Offer::factory()->create(['valid_from' => '2026-09-01', 'valid_to' => '2026-09-30']);
    Product::factory()->count(2)->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->where('stats.offers', 3)
            ->where('stats.products', 2)
            ->where('stats.chains', 7)
            // Logo obchodu je odkaz na jeho akce (R68), Albert už má akce s cenou z PDF (R86)
            ->where('chainUrls.kaufland', '/akce/kaufland')
            ->where('chainUrls.albert', '/akce/albert')
            ->where('urls.register', '/registrace')
            // Hledání v hlavním pruhu, ukázka hlídání a hra (R90)
            ->where('urls.suggestions', '/akce/naseptavac')
            ->where('demo.url', '/ukazka-hlidani')
            ->has('demo.products')
            ->has('demo.initial.count')
            ->has('quiz'));

    $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn (Assert $page) => $page->component('Home'));
});

it('ukázka akcí bere nejvyšší slevy s obrázkem a střídá obchody', function (): void {
    config(['letaky.landing.top_offers' => 3]);
    $discount = fn (string $name, Chain $chain, int $percent, ?string $image = 'https://example.com/a.jpg'): Offer => Offer::factory()->create([
        'name' => $name, 'chain' => $chain, 'discount_percent' => $percent, 'offer_type' => OfferType::Discount, 'image_url' => $image,
    ]);
    $discount('Kaufland 60', Chain::Kaufland, 60);
    $discount('Kaufland 55', Chain::Kaufland, 55);
    $discount('Lidl 40', Chain::Lidl, 40);
    $discount('Penny bez obrázku', Chain::Penny, 70, null);
    $discount('Tesco 30', Chain::Tesco, 30);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('topOffers', fn ($offers): bool => collect($offers)->pluck('name')->all() === ['Kaufland 60', 'Lidl 40', 'Tesco 30']));
});

it('ukázka akcí je od nejvyšší slevy, i když druhé kolo přidá obchod podruhé', function (): void {
    config(['letaky.landing.top_offers' => 3]);
    $discount = fn (string $name, Chain $chain, int $percent): Offer => Offer::factory()->create([
        'name' => $name, 'chain' => $chain, 'discount_percent' => $percent, 'offer_type' => OfferType::Discount, 'image_url' => 'https://example.com/a.jpg',
    ]);
    $discount('Kaufland 60', Chain::Kaufland, 60);
    $discount('Kaufland 55', Chain::Kaufland, 55);
    $discount('Lidl 40', Chain::Lidl, 40);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('topOffers', fn ($offers): bool => collect($offers)->pluck('name')->all() === ['Kaufland 60', 'Kaufland 55', 'Lidl 40']));
});

it('ukázka akcí nebere stejnou akci obchodu dvakrát a hlásí nejvyšší slevu (R90)', function (): void {
    config(['letaky.landing.top_offers' => 3]);
    $discount = fn (string $name, Chain $chain, int $percent): Offer => Offer::factory()->create([
        'name' => $name, 'chain' => $chain, 'discount_percent' => $percent, 'offer_type' => OfferType::Discount, 'image_url' => 'https://example.com/a.jpg',
    ]);
    // Kaufland má akci po prodejnách jako víc řádků (R49)
    $discount('Vepřová kýta', Chain::Kaufland, 70);
    $discount('Vepřová kýta', Chain::Kaufland, 65);
    $discount('Vepřová plec', Chain::Kaufland, 66);
    $discount('Cuketa', Chain::Globus, 57);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('topOffers', fn ($offers): bool => collect($offers)->pluck('name')->all() === ['Vepřová kýta', 'Vepřová plec', 'Cuketa'])
        ->where('stats.topDiscount', 70));
});

it('ukázka hlídání nabídne produkty s nejvíc akcemi a předvybrané spočítá (R90)', function (): void {
    config(['letaky.landing.demo_preselected' => 1]);
    $beer = Product::factory()->create(['name' => 'Pivo']);
    $butter = Product::factory()->create(['name' => 'Máslo']);
    foreach ([[$beer, 2], [$butter, 1]] as [$product, $count]) {
        foreach (Offer::factory()->count($count)->create() as $offer) {
            OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
        }
    }

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('demo.products.0.name', 'Pivo')
        ->where('demo.products.1.name', 'Máslo')
        ->where('demo.preselected', [$beer->id])
        ->where('demo.initial.count', 2)
        ->has('demo.initial.offers', 1));
});
