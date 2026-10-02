<?php

/**
 * Sledovaný obchod z formuláře nastavení (R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Chains;

use App\Enums\Chain;
use App\Enums\StoreFormat;

final readonly class FollowedChainData
{
    /**
     * @param  StoreFormat|null  $storeFormat  null = všechny typy prodejen
     */
    public function __construct(
        public Chain $chain,
        public ?StoreFormat $storeFormat = null,
        public bool $includeOnlineOnly = true,
    ) {}
}
