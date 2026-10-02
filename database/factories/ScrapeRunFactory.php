<?php

/**
 * Továrna záznamů stažení pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * Výchozí úspěšné stažení Kauflandu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chain' => Chain::Kaufland,
            'status' => ScrapeStatus::Succeeded,
            'offers_count' => 1,
            'started_at' => CarbonImmutable::now(),
            'finished_at' => CarbonImmutable::now(),
        ];
    }
}
