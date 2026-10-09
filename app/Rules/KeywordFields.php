<?php

/**
 * Pravidla polí hledaných slov (slova, varianty, vyloučená) — stejná pro produkt katalogu,
 * hlídanou položku i náhled vlastních slov (R18, R28, R71, R113). Dřív byla ve třech kopiích.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Rules;

final class KeywordFields
{
    /**
     * Pravidla polí `keywords`, `variant_keywords` a `exclude_keywords`.
     *
     * @param  list<string>  $keywordsPresence  Povinnost pole slov (`required`, nebo `required_without:product_id`, `nullable`)
     * @return array<string, list<mixed>>
     */
    public static function rules(array $keywordsPresence = ['required']): array
    {
        $max = 'max:'.config()->integer('letaky.watch.keywords_max_length');

        return [
            'keywords' => [...$keywordsPresence, 'string', $max, new SearchableKeywords],
            'variant_keywords' => ['nullable', 'string', $max],
            'exclude_keywords' => ['nullable', 'string', $max],
        ];
    }
}
