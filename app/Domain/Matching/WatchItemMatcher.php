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
    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly PetFood $petFood,
    ) {}

    /**
     * Stav shody nabídky s pravidly; null = nabídka nepatří.
     */
    public function match(WatchRule $rule, Offer $offer): ?MatchStatus
    {
        $text = $this->offerText($offer);

        return $this->matchText($rule, $text, $offer->variant_note !== null, $this->petFood->isPetOffer($offer->source_category, $text));
    }

    /**
     * Text nabídky, ve kterém se hledá (název, značka, popis), normalizovaný pro párování.
     */
    public function offerText(Offer $offer): string
    {
        return $this->normalizer->normalize($offer->name, $offer->brand, $offer->description);
    }

    /**
     * Stav shody nad už normalizovaným textem nabídky — pro přepočet tisíců nabídek
     * proti více pravidlům, kde se text normalizuje jen jednou.
     *
     * @param  bool  $hasVariantNote  Nabídka je souhrnná („různé druhy“)
     * @param  bool  $isPetFood  Nabídka je krmivo pro zvířata (R50) — patří jen k hlídání o zvířatech
     */
    public function matchText(WatchRule $rule, string $text, bool $hasVariantNote, bool $isPetFood = false): ?MatchStatus
    {
        if ($rule->keywords === [] || ($isPetFood && ! $this->petFood->isPetRule($rule))) {
            return null;
        }

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

        return $hasVariantNote ? MatchStatus::Maybe : null;
    }

    /**
     * Stav zmínky na stránce letáku (R27); null = stránka položku nezmiňuje.
     *
     * Stránka je směs desítek produktů, proto se hledají jen celá slova („máslo“ nenajde
     * „Dýně máslová“) a vyloučená slova se nepoužijí — vyřadila by celou stránku kvůli
     * jinému produktu. Bez varianty („Coca-Cola“ bez „Zero“) je zmínka „možná“.
     *
     * @param  string  $text  Text stránky z TextNormalizer::normalize
     */
    public function mention(WatchRule $rule, string $text): ?MatchStatus
    {
        if ($rule->keywords === [] || ! $this->containsAll($text, $rule->keywords, wholeWords: true)) {
            return null;
        }

        return $this->containsAll($text, $rule->variant, wholeWords: true) ? MatchStatus::Match : MatchStatus::Maybe;
    }

    /**
     * Obsahuje text každé slovo (aspoň jednu jeho alternativu)?
     *
     * @param  list<list<string>>  $terms
     */
    private function containsAll(string $text, array $terms, bool $wholeWords = false): bool
    {
        foreach ($terms as $alternatives) {
            $found = array_filter($alternatives, fn (string $word): bool => $this->containsWord($text, $word, $wholeWords));
            if ($found === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Začíná v textu některé slovo hledaným slovem (případně je jím celé)? Text má mezery
     * na krajích (TextNormalizer).
     */
    private function containsWord(string $text, string $word, bool $wholeWords = false): bool
    {
        return str_contains($text, $wholeWords ? ' '.$word.' ' : ' '.$word);
    }
}
