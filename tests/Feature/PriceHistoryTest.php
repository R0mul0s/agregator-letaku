<?php

/**
 * „Je to opravdu sleva?“ (R59) — srovnání ceny akce s dřívějšími akcemi stejné položky u obchodu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Domain\Offers\PriceHistory;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Models\Offer;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Dřívější akce stejné položky, která skončila před tolika týdny.
 *
 * @param  array<string, mixed>  $attributes
 */
function earlierOffer(int $weeksAgo, array $attributes = []): Offer
{
    $end = CarbonImmutable::today()->subWeeks($weeksAgo);

    return Offer::factory()->create(['external_id' => '111', 'valid_from' => $end->subWeek(), 'valid_to' => $end, ...$attributes]);
}

it('akce je nejlevnější za období, stejně levná, nebo byla dřív levnější', function (int $earlierPrice, string $status): void {
    earlierOffer(3, ['price' => $earlierPrice]);
    $current = Offer::factory()->create(['external_id' => '111', 'price' => 2990]);

    expect(app(PriceHistory::class)->forOffers([$current])[$current->id])->toBe([
        'status' => $status,
        'price' => $earlierPrice,
        'weeks' => 12,
        'weeksAgo' => 3,
    ]);
})->with([
    'nejlevněji' => [3990, PriceHistory::LOWEST],
    'stejně' => [2990, PriceHistory::SAME],
    'dřív levněji' => [1990, PriceHistory::CHEAPER_BEFORE],
]);

it('bere nejnižší dřívější cenu a z dvou stejných tu poslední', function (): void {
    earlierOffer(8, ['price' => 1990]);
    earlierOffer(5, ['price' => 2490]);
    earlierOffer(2, ['price' => 1990]);
    $current = Offer::factory()->create(['external_id' => '111', 'price' => 2990]);

    expect(app(PriceHistory::class)->forOffers([$current])[$current->id])
        ->toMatchArray(['status' => PriceHistory::CHEAPER_BEFORE, 'price' => 1990, 'weeksAgo' => 2]);
});

it('nepočítá akce mimo období, jiného obchodu, souběžné ani akci na více kusů', function (): void {
    earlierOffer(20, ['price' => 990]);
    earlierOffer(2, ['price' => 990, 'chain' => Chain::Lidl]);
    // Souběžná akce (třeba příští týden) není „dřív“
    Offer::factory()->create(['external_id' => '111', 'price' => 990, 'valid_from' => CarbonImmutable::today()->addWeek(), 'valid_to' => CarbonImmutable::today()->addWeeks(2)]);
    $current = Offer::factory()->create(['external_id' => '111', 'price' => 2990]);
    $multibuy = Offer::factory()->create(['external_id' => '222', 'offer_type' => OfferType::Multibuy]);
    earlierOffer(2, ['external_id' => '222', 'price' => 990]);

    expect(app(PriceHistory::class)->forOffers([$current, $multibuy]))->toBe([]);
});

it('u akce jen s kartou porovná cenu s kartou', function (): void {
    earlierOffer(1, ['offer_type' => OfferType::LoyaltyOnly, 'price' => 4990, 'loyalty_price' => 3490]);
    $current = Offer::factory()->create(['external_id' => '111', 'offer_type' => OfferType::LoyaltyOnly, 'price' => 4990, 'loyalty_price' => 2990]);

    expect(app(PriceHistory::class)->forOffers([$current])[$current->id])
        ->toMatchArray(['status' => PriceHistory::LOWEST, 'price' => 3490, 'weeksAgo' => 1]);
});

it('Všechny akce posílají ke kartě srovnání s dřívějšími akcemi', function (): void {
    earlierOffer(3, ['price' => 3990]);
    Offer::factory()->create(['external_id' => '111', 'price' => 2990, 'name' => 'Máslo']);

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('offers.data.0.name', 'Máslo')
        ->where('offers.data.0.priceHistory.status', PriceHistory::LOWEST));
});
