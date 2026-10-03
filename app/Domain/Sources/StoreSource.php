<?php

/**
 * Zdroj prodejen obchodu a seznamu akcí platných v každé z nich (R49). Jen stahuje —
 * ukládá ImportStores.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use Illuminate\Http\Client\RequestException;

interface StoreSource
{
    /**
     * Obchod, jehož prodejny zdroj stahuje.
     */
    public function chain(): Chain;

    /**
     * Seznam prodejen: kód => [název bez názvu obchodu, město].
     *
     * @return array<string, array{name: string, city: string}>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    public function stores(): array;

    /**
     * Akce platné v prodejně jako klíče nabídky („id|od|do“, OfferData::key).
     *
     * @return list<string>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    public function offerKeys(string $storeCode): array;
}
