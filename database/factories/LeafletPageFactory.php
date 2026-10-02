<?php

/**
 * Továrna stránek letáků pro testy zmínek.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeafletPage>
 */
class LeafletPageFactory extends Factory
{
    /**
     * Výchozí stránka letáku Lidlu platného od dneška týden.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leaflet_id' => Leaflet::factory()->state([
                'chain' => Chain::Lidl,
                'kind' => LeafletKind::Leaflet,
                'valid_from' => CarbonImmutable::today(),
                'valid_to' => CarbonImmutable::today()->addWeek(),
            ]),
            'number' => fake()->unique()->numberBetween(1, 60),
            'text' => 'Kanadské Borůvky Balení 2392 Kč -44%',
            'image_url' => 'https://imgproxy.leaflets.schwarz/thumbnail.jpg',
            'page_url' => 'https://www.lidl.cz/l/cs/letak/letak/view/flyer/page/1',
        ];
    }
}
