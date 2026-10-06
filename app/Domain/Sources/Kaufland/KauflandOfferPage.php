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
     * @param  list<SourceBatch>  $batches  Nabídka po týdnech (aktuální, případně i příští)
     * @param  bool  $nextWeekMissing  Stránka ohlásila příští týden, ale jeho nabídku nemá — je ho třeba stáhnout zvlášť
     * @param  array<string, true>  $itemKeys  Klíče všech položek stránky včetně přeskočených (bez názvu nebo ceny)
     */
    public function __construct(
        public array $batches,
        public bool $nextWeekMissing,
        public array $itemKeys,
    ) {}
}
