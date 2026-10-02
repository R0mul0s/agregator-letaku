<?php

/**
 * Množství v balení v základní jednotce (g, ml, ks).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

use App\Enums\PackageUnit;

final readonly class PackageSize
{
    public function __construct(
        public float $quantity,
        public PackageUnit $unit,
    ) {}
}
