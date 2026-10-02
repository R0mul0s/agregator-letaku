<?php

/**
 * Kategorie ze stromu obchodu před uložením (R28).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Data;

final readonly class CategoryData
{
    /**
     * @param  string|null  $parentSourceId  ID nadřazeného uzlu u obchodu; null u oddělení
     */
    public function __construct(
        public string $sourceId,
        public string $name,
        public int $depth,
        public int $position,
        public ?string $parentSourceId = null,
    ) {}
}
