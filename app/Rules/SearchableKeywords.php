<?php

/**
 * Hledaná slova musí mít aspoň jedno dost dlouhé slovo (R54). Podle něj databáze předvybírá
 * nabídky (WatchRule::prefilterTerm) — „a“ nebo „7“ by pustilo skoro celou nabídku
 * a zpomalilo Moje slevy i přiřazení katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SearchableKeywords implements ValidationRule
{
    /**
     * Ověří, že nejdelší hledané slovo má aspoň minimální délku z konfigurace.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $min = config()->integer('letaky.watch.min_search_word_length');
        $rule = WatchRule::fromText($value, null, null, app(TextNormalizer::class));
        if ($rule->prefilterLength() < $min) {
            $fail(__('app.ui.watch.keywords_too_short', ['min' => $min]));
        }
    }
}
