<?php

/**
 * Výsledek párování hlídané položky s nabídkou (R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    /** Nabídka obsahuje všechna hledaná slova i variantu. */
    case Match = 'match';

    /** Nabídka je souhrnná („různé druhy“) a variantu neuvádí — může, ale nemusí ji zahrnovat. */
    case Maybe = 'maybe';
}
