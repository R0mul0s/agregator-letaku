<?php

/**
 * Zdroj nabídek (leták, akční stránka, e-shop) tak, jak ho vrátil obchod.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

use App\Enums\LeafletKind;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;

final readonly class LeafletData
{
    /**
     * @param  CarbonImmutable|null  $validFrom  Místní datum; null u průběžných akcí e-shopu
     * @param  CarbonImmutable|null  $validTo  Místní datum včetně
     */
    public function __construct(
        public LeafletKind $kind,
        public string $externalId,
        public ?string $title = null,
        public ?StoreFormat $format = null,
        public ?CarbonImmutable $validFrom = null,
        public ?CarbonImmutable $validTo = null,
        public ?string $sourceUrl = null,
    ) {}
}
