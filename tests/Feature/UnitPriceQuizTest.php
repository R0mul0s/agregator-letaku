<?php

/**
 * Hra „Co je levnější?“ na úvodní stránce (R90): dvojice dnes platných akcí stejného produktu
 * z různých obchodů s cenou za kilo nebo litr a správná odpověď.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Offers\UnitPriceQuiz;
use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Akce s obrázkem přiřazená k produktu katalogu.
 *
 * @param  array<string, mixed>  $attributes
 */
function quizOffer(Product $product, array $attributes): Offer
{
    $offer = Offer::factory()->create(['image_url' => 'https://example.com/a.jpg', ...$attributes]);
    OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    return $offer;
}

it('dá dvojici z různých obchodů a přednost má past — levnější za kilo stojí víc', function (): void {
    $butter = Product::factory()->create(['name' => 'Máslo']);
    // 250 g za 49,90 = 199,60 Kč/kg; 500 g za 89,90 = 179,80 Kč/kg (past); 250 g za 59,90 = 239,60 Kč/kg
    $small = quizOffer($butter, ['chain' => Chain::Kaufland, 'price' => 4990, 'quantity' => 250, 'unit' => PackageUnit::Gram]);
    $big = quizOffer($butter, ['chain' => Chain::Lidl, 'price' => 8990, 'quantity' => 500, 'unit' => PackageUnit::Gram]);
    quizOffer($butter, ['chain' => Chain::Tesco, 'price' => 5990, 'quantity' => 250, 'unit' => PackageUnit::Gram]);

    $rounds = app(UnitPriceQuiz::class)->rounds();

    expect($rounds)->toHaveCount(1)
        ->and($rounds[0]['product'])->toBe('Máslo')
        ->and(collect($rounds[0]['offers'])->pluck('id')->sort()->values()->all())->toBe([$small->id, $big->id])
        ->and($rounds[0]['cheaperId'])->toBe($big->id);
});

it('vynechá stejný obchod, malý rozdíl ceny za kilo, kusy, akce na více kusů a akce bez obrázku', function (): void {
    $product = Product::factory()->create();
    quizOffer($product, ['chain' => Chain::Kaufland, 'price' => 4990, 'quantity' => 250]);
    // Stejný obchod (a se stejnou cenou za kilo, ať netvoří dvojici s Teskem)
    quizOffer($product, ['chain' => Chain::Kaufland, 'price' => 9980, 'quantity' => 500]);
    // Rozdíl pod 10 %: 199,60 vs. 209,60 Kč/kg
    quizOffer($product, ['chain' => Chain::Tesco, 'price' => 5240, 'quantity' => 250]);
    quizOffer($product, ['chain' => Chain::Lidl, 'price' => 990, 'quantity' => 4, 'unit' => PackageUnit::Piece]);
    quizOffer($product, ['chain' => Chain::Penny, 'price' => 990, 'offer_type' => OfferType::Multibuy]);
    quizOffer($product, ['chain' => Chain::Albert, 'price' => 990, 'image_url' => null]);

    expect(app(UnitPriceQuiz::class)->rounds())->toBe([]);
});

it('každý den stejná kola a nejvýš nastavený počet', function (): void {
    config(['letaky.landing.quiz_rounds' => 2]);
    foreach (range(1, 3) as $index) {
        $product = Product::factory()->create();
        quizOffer($product, ['chain' => Chain::Kaufland, 'price' => 4990, 'quantity' => 250]);
        quizOffer($product, ['chain' => Chain::Lidl, 'price' => 8990, 'quantity' => 500]);
    }

    $first = app(UnitPriceQuiz::class)->rounds();

    expect($first)->toHaveCount(2)
        ->and(app(UnitPriceQuiz::class)->rounds())->toBe($first);
});
