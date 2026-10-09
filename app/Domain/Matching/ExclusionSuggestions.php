<?php

/**
 * Návrhy slov pro „Tohle ne“ (R125) — klepnutím na slovo z názvu akce ho uživatel přidá mezi
 * vyloučená slova hlídané položky a podobné akce už neuvidí („Máslová dýně“ u Másla → „dýně“).
 *
 * Nenavrhuje slova, přes která položka akce hledá (hledané slovo, jeho začátek i tvar —
 * „avokádo“ u Avokáda), čísla a balení („500 g“), krátká slova ani slova, která už vyloučená jsou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Models\Offer;

final readonly class ExclusionSuggestions
{
    /** Hranice slov v názvu a značce — vše kromě písmen (s diakritikou). */
    private const WORD_SEPARATOR = '/[^\p{L}]+/u';

    public function __construct(private TextNormalizer $normalizer) {}

    /**
     * Slova z názvu a značky akce, která jde vyloučit, v pořadí výskytu, malými písmy
     * s diakritikou (jak je uživatel uvidí i v poli Vyloučit).
     *
     * @return list<string>
     */
    public function for(WatchRule $rule, Offer $offer): array
    {
        $suggestions = [];
        foreach (preg_split(self::WORD_SEPARATOR, $offer->name.' '.$offer->brand, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $normalized = $this->normalizer->word($word);
            if (isset($suggestions[$normalized]) || ! $this->isSuggestible($rule, $normalized)) {
                continue;
            }
            $suggestions[$normalized] = mb_strtolower($word);
        }

        return array_slice(array_values($suggestions), 0, config()->integer('letaky.watch.exclusion_suggestions'));
    }

    /**
     * Jde slovo vyloučit — je dost dlouhé a není to slovo, přes které položka akce hledá?
     */
    public function isAllowed(WatchRule $rule, string $word): bool
    {
        $normalized = $this->normalizer->word($word);

        return mb_strlen($normalized) >= config()->integer('letaky.watch.exclusion_min_length')
            && ! $rule->isSearchedWord($normalized);
    }

    /**
     * Patří normalizované slovo mezi návrhy: jde ho vyloučit a vyloučené ještě není?
     */
    private function isSuggestible(WatchRule $rule, string $normalized): bool
    {
        return $this->isAllowed($rule, $normalized) && ! in_array($normalized, $rule->exclude, true);
    }
}
