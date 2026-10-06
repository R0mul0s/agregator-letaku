<?php

/**
 * Předběžné ID akce Globusu z letáku (R88) — otisk názvu položky letáku a běžné ceny.
 *
 * PDF letáku nemá kód zboží (`vanr`), který má akce z API. Stejný otisk proto spočítá
 * dlaždice PDF (`GlobusLeafletParser`, je to její `externalId`) i akce z API podle názvu
 * spárované položky letáku (`actionProducts`) a ceny (`OfferData::$supersedes`). Až akce
 * začne platit a vrátí ji API, import řádek z letáku přejmenuje na ID z API — akce se
 * neohlásí podruhé jako nová (R74, R76).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Matching\TextNormalizer;

final class GlobusLeafletKey
{
    /** Předpona ID akcí z letáku — jako u ostatních PDF letáků (Lidl, Albert). */
    private const PREFIX = 'letak-';

    private const HASH_LENGTH = 16;

    /** Čárky a mezery — normalizace čárky nechává („Herkules ,“ i „Herkules,“ je totéž). */
    private const SEPARATORS_PATTERN = '/[\s,]+/';

    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Otisk názvu v letáku (bez diakritiky, velikosti písmen a interpunkce) a běžné ceny v haléřích.
     */
    public function for(string $leafletName, int $price): string
    {
        $name = trim((string) preg_replace(self::SEPARATORS_PATTERN, ' ', $this->normalizer->normalize($leafletName)));

        return self::PREFIX.substr(sha1($name.'|'.$price), 0, self::HASH_LENGTH);
    }
}
