<?php

/**
 * Leták Tesco z API letáků — data pro uložení a údaje pro stažení jeho obsahu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Tesco;

use App\Domain\Offers\Data\LeafletData;

final readonly class TescoLeaflet
{
    /**
     * @param  string  $slug  Společný pro letáky HM, SM i katalog téhož týdne
     * @param  string  $type  HM / SM — rozlišuje leták se stejným slugem
     */
    public function __construct(
        public LeafletData $data,
        public string $slug,
        public string $type,
    ) {}
}
