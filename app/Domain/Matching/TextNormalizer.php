<?php

/**
 * Text pro párování — malá písmena bez diakritiky, interpunkce jako mezera.
 *
 * „Coca-Cola Zero“ → „ coca cola zero “ (mezery na krajích kvůli hledání začátků slov).
 * Čárka zůstává kvůli desetinným číslům („tuk 1,5 %“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use Illuminate\Support\Str;

final class TextNormalizer
{
    /**
     * Normalizovaný text s mezerou na začátku a na konci.
     */
    public function normalize(?string ...$texts): string
    {
        $text = Str::lower(Str::ascii(implode(' ', array_filter($texts, fn (?string $text): bool => $text !== null))));
        $text = preg_replace('/[^a-z0-9,]+/', ' ', $text) ?? '';

        return ' '.trim(preg_replace('/\s+/', ' ', $text) ?? '').' ';
    }

    /**
     * Normalizované hledané slovo — bez mezer na krajích a bez čárek na krajích („maggi,“).
     */
    public function word(string $word): string
    {
        return trim($this->normalize($word), ' ,');
    }
}
