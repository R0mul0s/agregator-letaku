<?php

/**
 * Přiřazení nabídek k produktům katalogu a ruční opravy (R30).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Catalog\Actions\CorrectAssignment;
use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Přiřazené nabídky produktu: název => stav (a „ručně“).
 *
 * @return array<string, string>
 */
function assignedTo(Product $product): array
{
    return OfferProduct::query()->where('product_id', $product->id)->with('offer')->get()
        ->mapWithKeys(fn (OfferProduct $assignment): array => [
            $assignment->offer->name => $assignment->status->value.($assignment->is_manual ? ' ručně' : ''),
        ])
        ->sortKeys()
        ->all();
}

it('přiřadí neskončené nabídky podle pravidel produktu se stavem shoda / možná', function (): void {
    $product = Product::factory()->create(['keywords' => 'coca cola', 'variant_keywords' => 'zero']);
    Offer::factory()->create(['name' => 'Coca-Cola Zero 1,5 l']);
    Offer::factory()->create(['name' => 'Coca-Cola různé druhy', 'variant_note' => 'různé druhy']);
    Offer::factory()->create(['name' => 'Coca-Cola Original']);
    Offer::factory()->create(['name' => 'Coca-Cola Zero skončená', 'valid_from' => '2026-09-20', 'valid_to' => '2026-10-01']);
    Offer::factory()->create(['name' => 'Coca-Cola Zero stažená', 'withdrawn_at' => CarbonImmutable::now()]);

    app(AssignProducts::class)->forProduct($product);

    expect(assignedTo($product))->toBe([
        'Coca-Cola Zero 1,5 l' => 'match',
        'Coca-Cola různé druhy' => 'maybe',
    ]);
});

it('po změně pravidel přiřazení přepočítá a ruční opravy zachová', function (): void {
    $product = Product::factory()->create(['keywords' => 'vejce']);
    $eggs = Offer::factory()->create(['name' => 'Čerstvá vejce M']);
    $pasta = Offer::factory()->create(['name' => 'Vaječné těstoviny']);
    $omelette = Offer::factory()->create(['name' => 'Ruské vejce']);
    app(AssignProducts::class)->forProduct($product);

    $correct = app(CorrectAssignment::class);
    $correct->include($product, $pasta);
    $correct->exclude($product, $omelette);

    $product->update(['keywords' => 'vejce|vaječné']);
    app(AssignProducts::class)->forProduct($product);

    expect(assignedTo($product))->toBe([
        'Vaječné těstoviny' => 'match ručně',
        'Čerstvá vejce M' => 'match',
    ]);

    $correct->restore($product, $omelette);

    expect(assignedTo($product))->toHaveKey('Ruské vejce')
        ->and($eggs->productAssignments()->count())->toBe(1);
});

it('po importu obchodu přiřadí jeho nabídky ke všem produktům', function (): void {
    $eggs = Product::factory()->create(['name' => 'Vejce', 'keywords' => 'vejce']);
    $butter = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    Http::fake(['https://prodejny.kaufland.cz/nabidka/prehled.html*' => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    expect(OfferProduct::query()->where('product_id', $eggs->id)->count())->toBe(1)
        ->and(OfferProduct::query()->where('product_id', $butter->id)->sole()->status)->toBe(MatchStatus::Match)
        ->and(OfferProduct::query()->whereHas('offer', fn ($query) => $query->where('chain', '!=', Chain::Kaufland))->count())->toBe(0);
});
