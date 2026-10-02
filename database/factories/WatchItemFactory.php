<?php

/**
 * Továrna hlídaných položek pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchItem>
 */
class WatchItemFactory extends Factory
{
    /**
     * Výchozí položka — vejce bez variant a vyloučení.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Vejce',
            'keywords' => 'vejce',
            'variant_keywords' => null,
            'exclude_keywords' => null,
        ];
    }
}
