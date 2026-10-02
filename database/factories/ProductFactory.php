<?php

/**
 * Továrna produktů katalogu pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Výchozí produkt — vejce bez kategorie, variant a vyloučení.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'name' => fake()->unique()->words(2, asText: true),
            'keywords' => 'vejce',
            'variant_keywords' => null,
            'exclude_keywords' => null,
        ];
    }
}
