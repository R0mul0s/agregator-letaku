<?php

/**
 * Továrna kategorií katalogu pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Výchozí kategorie — oddělení bez nadřazené kategorie.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, asText: true),
            'source_id' => 'b;'.fake()->unique()->sha1(),
            'depth' => 0,
            'position' => 0,
        ];
    }

    /**
     * Podkategorie zadané kategorie.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'depth' => $parent->depth + 1]);
    }
}
