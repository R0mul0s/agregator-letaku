<?php

/**
 * Stránka letáku s textem tak, jak ji vrátil zdroj — pro zmínky bez ceny (R27).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

final readonly class LeafletPageData
{
    public function __construct(
        public int $number,
        public string $text,
        public ?string $imageUrl = null,
        public ?string $pageUrl = null,
    ) {}
}
