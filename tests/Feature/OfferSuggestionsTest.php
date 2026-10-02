<?php

/**
 * Našeptávač hledání ve Všech akcích.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->actingAs(User::factory()->create());
});

it('navrhne produkty katalogu a názvy aktuálních akcí bez ohledu na diakritiku', function (): void {
    Product::factory()->create(['name' => 'Máslo']);
    Offer::factory()->create(['name' => 'Tatra máslo']);
    Offer::factory()->create(['name' => 'Tatra máslo']);
    Offer::factory()->create(['name' => 'Máslo skončené', 'valid_from' => '2026-09-20', 'valid_to' => '2026-10-01']);
    Offer::factory()->create(['name' => 'Rama']);

    $this->getJson(route('offers.suggestions', ['q' => 'maslo']))
        ->assertOk()
        ->assertExactJson(['suggestions' => [
            ['type' => 'product', 'label' => 'Máslo'],
            ['type' => 'offer', 'label' => 'Tatra máslo'],
        ]]);
});

it('návrhy akcí omezí na zvolený obchod a krátký text nenašeptává', function (): void {
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);

    $this->getJson(route('offers.suggestions', ['q' => 'vejce', 'chain' => 'tesco']))
        ->assertJsonPath('suggestions', [['type' => 'offer', 'label' => 'Vejce Tesco']]);

    $this->getJson(route('offers.suggestions', ['q' => 'v']))->assertJsonPath('suggestions', []);
});

it('hledá od začátku slova a řadí dopředu názvy, které textem začínají', function (): void {
    Offer::factory()->create(['name' => 'Boom Box Chocolate granola']);
    Offer::factory()->create(['name' => 'Bacardi a Coca-Cola']);
    Offer::factory()->create(['name' => 'Cola Zero Freeway']);

    $this->getJson(route('offers.suggestions', ['q' => 'cola']))
        ->assertJsonPath('suggestions.*.label', ['Cola Zero Freeway', 'Bacardi a Coca-Cola']);
});
