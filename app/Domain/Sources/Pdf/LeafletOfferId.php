<?php

/**
 * Stabilní ID akce z dlaždice PDF letáku (R16): otisk názvu, balení a ceny. Stejná dlaždice dá
 * při dalším stažení i na jiné straně letáku stejný klíč; cena odliší různé výrobky se stejným
 * názvem a změněná cena je nová akce. Text projde `TextNormalizer` — jiná mezera nebo diakritika
 * z nové verze `pdftotext` ID nezmění (TODO.md, Albert a Penny mají otisk `mb_strtolower`).
 *
 * Postup je převzatý z letáku Lidlu (R86) beze změny — jeho ID zůstávají stejná.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

use App\Domain\Matching\TextNormalizer;

final class LeafletOfferId
{
    /**
     * Předpona ID akcí z letáku — jako u ostatních PDF letáků (Albert, Penny, Globus). Podle ní
     * historie cen pozná ID s cenou v otisku a stejnou položku hledá podle názvu (R170).
     */
    public const PREFIX = 'letak-';

    private const HASH_LENGTH = 24;

    public function __construct(private readonly TextNormalizer $normalizer) {}

    /**
     * ID akce z názvu, balení a ceny v haléřích.
     */
    public function for(string $name, ?string $packageText, int $price): string
    {
        $fingerprint = trim($this->normalizer->normalize($name, $packageText)).'|'.$price;

        return self::PREFIX.substr(hash('sha256', $fingerprint), 0, self::HASH_LENGTH);
    }
}
