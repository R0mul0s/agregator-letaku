<?php

/**
 * Továrna prodejen pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Chain;
use App\Enums\StoreFormat;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Výchozí prodejna — Kaufland s náhodným ID ve tvaru, jaký používá obchod (CZ3300).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'chain' => Chain::Kaufland,
            'external_id' => 'CZ'.fake()->unique()->numerify('####'),
            'name' => $city,
            'format' => null,
            'city' => $city,
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(48.5, 51.1),
            'longitude' => fake()->longitude(12.1, 18.9),
        ];
    }

    /**
     * Prodejna zvoleného obchodu a formátu.
     */
    public function of(Chain $chain, ?StoreFormat $format = null): static
    {
        return $this->state(fn (): array => [
            'chain' => $chain,
            'format' => $format,
        ]);
    }
}
