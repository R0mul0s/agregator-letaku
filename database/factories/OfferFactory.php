<?php

/**
 * Továrna nabídek pro testy přehledu a hledání.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Chain;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Výchozí nabídka — sleva v Kauflandu platná od dneška týden.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = CarbonImmutable::today();

        return [
            'chain' => Chain::Kaufland,
            'leaflet_id' => Leaflet::factory(),
            'scrape_run_id' => ScrapeRun::factory(),
            'external_id' => fake()->unique()->numerify('########'),
            'name' => fake()->words(3, asText: true),
            'quantity' => 500,
            'unit' => PackageUnit::Gram,
            'price' => 2990,
            'original_price' => 3990,
            'discount_percent' => 25,
            'offer_type' => OfferType::Discount,
            'valid_from' => $today,
            'valid_to' => $today->addWeek(),
            'raw' => [],
        ];
    }
}
