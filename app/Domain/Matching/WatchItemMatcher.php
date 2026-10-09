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
        return $this->matchPrepared($rule, $offer, $this->prepare($offer));
    }

    /**
     * Normalizovaný text nabídky a příznak krmiva (R50) — pro párování jedné nabídky s více
     * pravidly (matchPrepared), kde by normalizace pro každé pravidlo znovu stála nejvíc času.
     *
     * @return array{text: string, isPetFood: bool}
     */
    public function prepare(Offer $offer): array
    {
        $text = $this->offerText($offer);

        return ['text' => $text, 'isPetFood' => $this->petFood->isPetOffer($offer->source_category, $text)];
    }

    /**
     * Stav shody nabídky s pravidly nad výsledkem prepare.
     *
     * @param  array{text: string, isPetFood: bool}  $prepared
     */
    public function matchPrepared(WatchRule $rule, Offer $offer, array $prepared): ?MatchStatus
    {
        return $this->matchText($rule, $prepared['text'], $offer->variant_note !== null, $prepared['isPetFood']);
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
     * Začíná v normalizovaném textu nabídky některé slovo kterýmkoli z vyloučených slov? Vlastní
     * vyloučení položky z katalogu nad uloženým přiřazením k produktu („Tohle ne“, R125).
     *
     * @param  list<string>  $words  Normalizovaná slova (WatchRule::words)
     */
    public function containsAnyWord(string $text, array $words): bool
    {
        return array_any($words, fn (string $word): bool => $this->containsWord($text, $word));
    }

    /**
     * Stav zmínky na stránce letáku (R27); null = stránka položku nezmiňuje.
     *
     * Stránka je směs desítek produktů, proto se hledají jen celá slova („máslo“ nenajde
     * „Dýně máslová“) a vyloučená slova platí jen v okolí hledaného slova (R107, „Orion Kofila
     * Banány v čokoládě“) — na celé stránce by ji vyřadila kvůli jinému produktu. Stačí jeden
     * výskyt bez vyloučeného slova v okolí. Bez varianty („Coca-Cola“ bez „Zero“) je zmínka „možná“.
     *
     * @param  string  $text  Text stránky z TextNormalizer::normalize
     */
    public function mention(WatchRule $rule, string $text): ?MatchStatus
    {
        if ($rule->keywords === []) {
            return null;
        }

        $window = config()->integer('letaky.mentions.exclude_window_words');
        foreach ($rule->keywords as $alternatives) {
            if (! array_any($alternatives, fn (string $word): bool => $this->hasCleanMention($text, $word, $rule->exclude, $window))) {
                return null;
            }
        }

        return $this->containsAll($text, $rule->variant, wholeWords: true) ? MatchStatus::Match : MatchStatus::Maybe;
    }

    /**
     * Je slovo v textu stránky jako celé slovo aspoň jednou bez vyloučeného slova v okolí?
     *
     * @param  list<string>  $exclude
     * @param  int  $window  Kolik slov před výskytem a za ním se prohledá
     */
    private function hasCleanMention(string $text, string $word, array $exclude, int $window): bool
    {
        $needle = ' '.$word.' ';
        $offset = 0;
        while (($position = strpos($text, $needle, $offset)) !== false) {
            $before = array_slice(explode(' ', trim(substr($text, 0, $position))), -$window);
            $after = array_slice(explode(' ', trim(substr($text, $position + strlen($needle)))), 0, $window);
            $context = ' '.implode(' ', [...$before, ...$after]).' ';
            if (! array_any($exclude, fn (string $excluded): bool => $this->containsWord($context, $excluded))) {
                return true;
            }
            $offset = $position + 1;
        }

        return false;
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
