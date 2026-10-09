<?php

/**
 * Stáhne strom kategorií e-shopu Tesco a uloží ho jako kategorie katalogu (R28).
 *
 * Kategorie se nemažou — produkt může ukazovat i na kategorii, kterou Tesco mezitím zrušilo.
 * Opakované stažení podle ID uzlu přepíše název, pořadí a nadřazenou kategorii.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\CategoryData;
use App\Domain\Sources\Tesco\TescoCategorySource;
use App\Enums\CronTask;
use App\Models\Category;
use App\Support\Exceptions\AlreadyRunning;
use App\Support\ExclusiveRun;
use App\Support\TaskHeartbeats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ImportCategories
{
    private const LOCK_KEY = 'import-categories';

    public function __construct(private readonly TescoCategorySource $source) {}

    /**
     * Stáhne a uloží kategorie; vrátí jejich počet. Jen jedno stažení najednou (R113),
     * výsledek se zapíše pro /health/tasks (R115).
     *
     * @throws AlreadyRunning
     */
    public function __invoke(): int
    {
        return ExclusiveRun::run(self::LOCK_KEY, fn (): int => TaskHeartbeats::run(CronTask::Categories, $this->import(...)));
    }

    /**
     * Stáhne a uloží kategorie v transakci; vrátí jejich počet.
     */
    private function import(): int
    {
        $categories = $this->source->fetch();

        DB::transaction(function () use ($categories): void {
            // Po úrovních — potomek potřebuje ID rodiče, které vznikne až uložením
            $byDepth = [];
            foreach ($categories as $category) {
                $byDepth[$category->depth][] = $category;
            }
            ksort($byDepth);

            $ids = [];
            foreach ($byDepth as $level) {
                $ids += $this->storeLevel($level, $ids);
            }
        });

        return count($categories);
    }

    /**
     * Uloží kategorie jedné úrovně; vrátí jejich ID podle ID uzlu u obchodu.
     *
     * @param  list<CategoryData>  $level
     * @param  array<string, int>  $ids  ID už uložených kategorií (rodičů) podle ID uzlu u obchodu
     * @return array<string, int>
     */
    private function storeLevel(array $level, array $ids): array
    {
        $now = CarbonImmutable::now();
        $rows = array_map(fn (CategoryData $category): array => [
            'source_id' => $category->sourceId,
            'parent_id' => $category->parentSourceId === null ? null : $ids[$category->parentSourceId],
            'name' => $category->name,
            'depth' => $category->depth,
            'position' => $category->position,
            'created_at' => $now,
            'updated_at' => $now,
        ], $level);

        Category::query()->upsert($rows, ['source_id'], ['parent_id', 'name', 'depth', 'position', 'updated_at']);

        /** @var array<string, int> $stored */
        $stored = Category::query()
            ->whereIn('source_id', array_column($rows, 'source_id'))
            ->pluck('id', 'source_id')
            ->all();

        return $stored;
    }
}
