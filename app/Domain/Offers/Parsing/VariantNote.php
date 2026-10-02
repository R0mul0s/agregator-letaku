<?php

/**
 * Pozná souhrnné nabídky („různé druhy“), u kterých není jisté, jestli zahrnují konkrétní variantu (R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

final class VariantNote
{
    /** Obraty, kterými obchody označují nabídku na víc variant zboží. */
    private const PATTERN = '/(různé|vybrané|více|další)\s+(druhy|druhů|příchutě|příchutí|varianty|variant)/iu';

    /**
     * Vrátí nalezený obrat (malými písmeny) z prvního textu, který ho obsahuje, jinak null.
     */
    public function detect(?string ...$texts): ?string
    {
        foreach ($texts as $text) {
            if ($text !== null && preg_match(self::PATTERN, $text, $matches) === 1) {
                return mb_strtolower($matches[0]);
            }
        }

        return null;
    }
}
