<?php

/**
 * Továrna zdrojů nabídek pro testy.
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leaflet>
 */
class LeafletFactory extends Factory
{
    /**
     * Výchozí zdroj — akční stránka Kauflandu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chain' => Chain::Kaufland,
            'kind' => LeafletKind::Web,
            'external_id' => 'nabidka-'.fake()->unique()->date(),
            'fetched_at' => CarbonImmutable::now(),
        ];
    }
}
