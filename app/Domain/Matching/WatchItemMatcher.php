<?php

/**
 * Párování hlídané položky s nabídkou podle slov (R18) se stavem „možná“ pro souhrnné nabídky (R9).
 *
 * Hledá se v názvu, značce a popisu nabídky, od začátku slov a bez ohledu na diakritiku:
 * „vejce“ najde „Vejce“ i „vejcem“, ale „cola“ nenajde „Pepsicola“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Enums\MatchStatus;
use App\Models\Offer;

final class WatchItemMatcher
{
    public function __construct(private readonly TextNormalizer $normalizer) {}

    /**
     * Stav shody nabídky s pravidly; null = nabídka nepatří.
     */
    public function match(WatchRule $rule, Offer $offer): ?MatchStatus
    {
        if ($rule->keywords === []) {
            return null;
        }

        $text = $this->normalizer->normalize($offer->name, $offer->brand, $offer->description);

        foreach ($rule->exclude as $word) {
            if ($this->containsWord($text, $word)) {
                return null;
            }
        }

        if (! $this->containsAll($text, $rule->keywords)) {
            return null;
        }

        if ($this->containsAll($text, $rule->variant)) {
            return MatchStatus::Match;
        }

        return $offer->variant_note !== null ? MatchStatus::Maybe : null;
    }

    /**
     * Obsahuje text každé slovo (aspoň jednu jeho alternativu)?
     *
     * @param  list<list<string>>  $terms
     */
    private function containsAll(string $text, array $terms): bool
    {
        foreach ($terms as $alternatives) {
            $found = array_filter($alternatives, fn (string $word): bool => $this->containsWord($text, $word));
            if ($found === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Začíná v textu některé slovo hledaným slovem? Text má mezery na krajích (TextNormalizer).
     */
    private function containsWord(string $text, string $word): bool
    {
        return str_contains($text, ' '.$word);
    }
}
