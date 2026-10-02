<?php

/**
 * Zdroj seznamu prodejen jednoho obchodu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Domain\Sources\Data\StoreData;
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
     * Stáhne seznam prodejen.
     *
     * @return list<StoreData>
     *
     * @throws SourceResponseChanged Odpověď nemá očekávaný tvar
     * @throws RequestException Obchod odpověděl chybou
     */
    public function fetch(): array;
}
