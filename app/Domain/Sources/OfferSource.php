<?php

/**
 * Zdroj akční nabídky jednoho obchodu. Jen stahuje a převádí na OfferData — neukládá
 * do databáze a neví o uživatelích (CODING_GUIDELINES, sekce 3).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use Illuminate\Http\Client\RequestException;

interface OfferSource
{
    /**
     * Obchod, jehož nabídku zdroj stahuje.
     */
    public function chain(): Chain;

    /**
     * Stáhne aktuální nabídku (a příští týden, když už je zveřejněný).
     *
     * @return list<SourceBatch>
     *
     * @throws SourceResponseChanged Odpověď nemá očekávaný tvar
     * @throws RequestException Obchod odpověděl chybou
     */
    public function fetch(): array;
}
