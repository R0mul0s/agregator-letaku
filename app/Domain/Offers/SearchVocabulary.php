<?php

/**
 * Oprava překlepů v hledání (R71) — „pyzza“ → „pizza“, „kola“ → „cola“. Slovník jsou
 * slova z názvů a značek aktuálních akcí a z názvů produktů katalogu; hledané slovo,
 * kterým žádné slovo slovníku nezačíná, se nahradí nejbližším podle Levenshteinovy
 * vzdálenosti (u krátkých slov 1, u delších 2 změny; jiné první písmeno je změna navíc),
 * při shodě vzdálenosti častějším.
 * Hosting nemá vyhledávací server (R20) — slovník má pár tisíc slov a projde se v PHP.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Matching\TextNormalizer;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

final class SearchVocabulary
{
    /** Klíč slovníku v cache. */
    private const CACHE_KEY = 'search.vocabulary';

    /** Oddělovače slov navíc k začátkům slov hledání (WordStart) — slovník dělí i „Cola,Fanta“. */
    private const EXTRA_SEPARATORS = ',';

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Text s opravenými slovy, nebo null, když žádné slovo opravu nepotřebuje (nebo ji nemá).
     */
    public function correct(string $text): ?string
    {
        $vocabulary = $this->vocabulary();
        $changed = false;
        $words = [];
        foreach (WordStart::words($text) as $word) {
            $correction = $this->correctWord($word, $vocabulary);
            $changed = $changed || $correction !== null;
            $words[] = $correction ?? $word;
        }

        return $changed ? implode(' ', $words) : null;
    }

    /**
     * Nejbližší slovo slovníku, nebo null — slovo je v pořádku (slovník má slovo, které jím
     * začíná), je příliš krátké, nebo nic blízkého není.
     *
     * @param  array<string, array{display: string, count: int}>  $vocabulary
     */
    private function correctWord(string $word, array $vocabulary): ?string
    {
        $normalized = $this->normalizer->word($word);
        if (mb_strlen($normalized) < config()->integer('letaky.search.typo_min_length')) {
            return null;
        }

        $maxDistance = strlen($normalized) <= config()->integer('letaky.search.typo_short_word_length') ? 1 : 2;
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestCount = 0;
        foreach ($vocabulary as $candidate => $entry) {
            $candidate = (string) $candidate;
            if (str_starts_with($candidate, $normalized)) {
                return null;
            }
            if (abs(strlen($candidate) - strlen($normalized)) > $maxDistance) {
                continue;
            }
            // Překlep málokdy změní první písmeno — jiné první písmeno je změna navíc
            $distance = levenshtein($normalized, $candidate) + ($candidate[0] === $normalized[0] ? 0 : 1);
            if ($distance <= $maxDistance && ($distance < $bestDistance || ($distance === $bestDistance && $entry['count'] > $bestCount))) {
                $best = $entry['display'];
                $bestDistance = $distance;
                $bestCount = $entry['count'];
            }
        }

        return $best;
    }

    /**
     * Slovník: normalizované slovo => tvar k zobrazení (malými písmeny s diakritikou) a četnost.
     * V cache — mění se jen se staženými akcemi.
     *
     * @return array<string, array{display: string, count: int}>
     */
    private function vocabulary(): array
    {
        /** @var array<string, array{display: string, count: int}> */
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(config()->integer('letaky.search.vocabulary_cache_minutes')), function (): array {
            $texts = Offer::query()
                ->active()
                ->notExpired($this->calendar->today())
                ->get(['name', 'brand'])
                ->flatMap(fn (Offer $offer): array => [$offer->name, $offer->brand])
                ->merge(Product::query()->pluck('name'))
                ->filter();

            $minLength = config()->integer('letaky.search.typo_min_length');
            $vocabulary = [];
            foreach ($texts as $text) {
                foreach (preg_split($this->separatorsPattern(), (string) $text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                    $normalized = $this->normalizer->word($token);
                    if (strlen($normalized) < $minLength || ! preg_match('/[a-z]/', $normalized)) {
                        continue;
                    }
                    $vocabulary[$normalized] ??= ['display' => mb_strtolower($token), 'count' => 0];
                    $vocabulary[$normalized]['count']++;
                }
            }

            return $vocabulary;
        });
    }

    /**
     * Regulární výraz oddělovačů slov — stejné začátky slov jako hledání (WordStart, R113).
     */
    private function separatorsPattern(): string
    {
        return '/[\s'.preg_quote(implode('', WordStart::SEPARATORS).self::EXTRA_SEPARATORS, '/').']+/u';
    }
}
