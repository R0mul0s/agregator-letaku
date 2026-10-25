<?php

/**
 * Stažení od obchodu trvalo tak dlouho, že by zápis nestihl doběhnout, než vyprší zámek
 * (R57, R170). Čekání na síť se do časového limitu PHP nepočítá — bez kontroly by zámek
 * vypršel, mohlo začít druhé stažení stejného obchodu a běžící záznam by se označil jako
 * nedoběhlý, takže jeho akce by nikdy nebyly „nové“. Nic se neuloží, příští cron to zkusí znovu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Offers\Exceptions;

use App\Enums\Chain;
use RuntimeException;

final class ImportTookTooLong extends RuntimeException
{
    /**
     * Výjimka pro obchod s dobou stahování v sekundách.
     */
    public static function for(Chain $chain, int $seconds): self
    {
        return new self("{$chain->label()}: stahování trvalo {$seconds} s, zápis by nestihl doběhnout před vypršením zámku — nic se neuložilo.");
    }
}
