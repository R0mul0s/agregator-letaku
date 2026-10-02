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
use App\Enums\OfferType;
use App\Models\Offer;
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
            ->where('stats.chains', 6)
            ->where('urls.register', '/register'));

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
