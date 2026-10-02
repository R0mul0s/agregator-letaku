<?php

/**
 * Prodejna tak, jak ji vrátil obchod.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Data;

use App\Enums\StoreFormat;

final readonly class StoreData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?StoreFormat $format = null,
        public ?string $city = null,
        public ?string $address = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
