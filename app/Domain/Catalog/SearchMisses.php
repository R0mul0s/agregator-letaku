<?php

/**
 * Hledání bez výsledků (R171): co lidé hledají ve Všech akcích a chtějí hlídat v Hlídám, a nic
 * to nenašlo. Souhrnně za text a zdroj, bez vazby na uživatele — jeden člověk (relace) se
 * u stejného textu započítá nejvýš jednou za `dedupe_hours` (otisk relace jen v cache). Admin
 * v katalogu vidí nejčastější jako podklad pro nové produkty a další obchody.
 *
 * Výsledky se hledají už při psaní, takže se zapíšou i začátky slova („kombu“, „kombuch“,
 * „kombucha“). Přehled je sloučí do nejdelšího s nejvyšším počtem — každé písmeno napsal tentýž
 * člověk, sečíst je nejde.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Matching\TextNormalizer;
use App\Enums\SearchMissSource;
use App\Models\SearchMiss;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SearchMisses
{
    /** Cache: předpona otisku „tenhle člověk už tohle dnes hledal“. */
    private const CACHE_PREFIX = 'search-miss.';

    /** Nejdelší uložený text (sloupce term a text). */
    private const MAX_LENGTH = 100;

    public function __construct(private TextNormalizer $normalizer) {}

    /**
     * Započítá hledání, které nic nenašlo; `$visitor` je identifikátor relace (jen do otisku v cache).
     */
    public function record(SearchMissSource $source, string $text, string $visitor): void
    {
        $term = Str::limit($this->normalizer->word($text), self::MAX_LENGTH, '');
        if (mb_strlen($term) < config()->integer('letaky.search_misses.min_length')) {
            return;
        }

        $seen = self::CACHE_PREFIX.sha1($visitor.'|'.$source->value.'|'.$term);
        if (! Cache::add($seen, true, CarbonImmutable::now()->addHours(config()->integer('letaky.search_misses.dedupe_hours')))) {
            return;
        }

        $now = CarbonImmutable::now();
        SearchMiss::query()->upsert(
            [[
                'source' => $source->value,
                'term' => $term,
                'text' => Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), self::MAX_LENGTH, ''),
                'hits' => 1,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['source', 'term'],
            ['hits' => DB::raw('`hits` + 1'), 'text', 'last_seen_at', 'updated_at'],
        );
    }

    /**
     * Nejčastější hledání bez výsledků pro admina: nezavřená, z doby uchování, začátky slova
     * sloučené do nejdelšího, stejný text z obou zdrojů dohromady, jen od `min_hits` výš.
     *
     * @return list<array{term: string, text: string, hits: int, sources: list<SearchMissSource>, lastSeenAt: CarbonImmutable}>
     */
    public function forAdmin(): array
    {
        $rows = SearchMiss::query()
            ->whereNull('dismissed_at')
            ->where('last_seen_at', '>=', CarbonImmutable::now()->subDays(config()->integer('letaky.search_misses.retention_days')))
            ->latest('last_seen_at')
            ->limit(config()->integer('letaky.search_misses.scan_rows'))
            ->get();

        $byTerm = [];
        foreach ($rows->groupBy(fn (SearchMiss $miss): string => $miss->source->value) as $misses) {
            foreach ($this->collapsePrefixes($misses->all()) as $miss) {
                $entry = $byTerm[$miss['term']] ?? ['term' => $miss['term'], 'text' => $miss['text'], 'hits' => 0, 'sources' => [], 'lastSeenAt' => $miss['lastSeenAt']];
                $entry['hits'] += $miss['hits'];
                $entry['sources'][] = $miss['source'];
                $entry['lastSeenAt'] = $miss['lastSeenAt']->max($entry['lastSeenAt']);
                $byTerm[$miss['term']] = $entry;
            }
        }

        $shown = array_filter($byTerm, fn (array $entry): bool => $entry['hits'] >= config()->integer('letaky.search_misses.min_hits'));
        usort($shown, fn (array $a, array $b): int => [$b['hits'], $b['lastSeenAt']] <=> [$a['hits'], $a['lastSeenAt']]);

        return array_slice($shown, 0, config()->integer('letaky.search_misses.shown'));
    }

    /**
     * Skryje hledání v přehledu — i jeho začátky, které přehled do něj sloučil. Další hledání
     * stejného textu ho nevrátí.
     */
    public function dismiss(string $term): void
    {
        $term = $this->normalizer->word($term);
        $prefixes = [];
        for ($length = config()->integer('letaky.search_misses.min_length'); $length <= mb_strlen($term); $length++) {
            $prefixes[] = mb_substr($term, 0, $length);
        }

        SearchMiss::query()->whereIn('term', $prefixes)->whereNull('dismissed_at')->update(['dismissed_at' => CarbonImmutable::now()]);
    }

    /**
     * Smaže hledání, která se neopakovala za dobu uchování (denní úklid); vrátí počet smazaných.
     */
    public function prune(): int
    {
        return SearchMiss::query()
            ->where('last_seen_at', '<', CarbonImmutable::now()->subDays(config()->integer('letaky.search_misses.retention_days')))
            ->delete();
    }

    /**
     * Sloučí začátky slova do nejdelšího textu, který jimi začíná (psaní „kombu“ → „kombucha“):
     * počet je nejvyšší z nich, poslední hledání nejnovější.
     *
     * @param  array<int, SearchMiss>  $misses  Jednoho zdroje
     * @return list<array{term: string, text: string, hits: int, source: SearchMissSource, lastSeenAt: CarbonImmutable}>
     */
    private function collapsePrefixes(array $misses): array
    {
        usort($misses, fn (SearchMiss $a, SearchMiss $b): int => mb_strlen($b->term) <=> mb_strlen($a->term));

        /** @var list<array{term: string, text: string, hits: int, source: SearchMissSource, lastSeenAt: CarbonImmutable}> $kept */
        $kept = [];
        foreach ($misses as $miss) {
            foreach ($kept as $index => $longer) {
                if (str_starts_with($longer['term'], $miss->term)) {
                    $kept[$index] = [
                        ...$longer,
                        'hits' => max($longer['hits'], $miss->hits),
                        'lastSeenAt' => $longer['lastSeenAt']->max($miss->last_seen_at),
                    ];

                    continue 2;
                }
            }
            $kept[] = ['term' => $miss->term, 'text' => $miss->text, 'hits' => $miss->hits, 'source' => $miss->source, 'lastSeenAt' => $miss->last_seen_at];
        }

        return $kept;
    }
}
