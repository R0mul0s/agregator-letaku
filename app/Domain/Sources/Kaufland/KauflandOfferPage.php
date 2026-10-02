<?php

/**
 * Výsledek převodu jedné stránky nabídky Kauflandu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Offers\Data\SourceBatch;

final readonly class KauflandOfferPage
{
    /**
     * @param  bool  $nextWeekPublished  Stránka uvádí dny příštího týdne — jde stáhnout i ten
     */
    public function __construct(
        public SourceBatch $batch,
        public bool $nextWeekPublished,
    ) {}
}
