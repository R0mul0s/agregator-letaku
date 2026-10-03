<?php

/**
 * Předvýběr nabídek v databázi podle slova (SQL LIKE) — přesná pravidla pak vyhodnotí
 * WatchItemMatcher v PHP. Sloupce musí odpovídat textu WatchItemMatcher::offerText.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Builder;

final class OfferPrefilter
{
    /** Sloupce, ve kterých se hledá — stejné jako WatchItemMatcher::offerText. */
    private const SEARCHED_COLUMNS = ['name', 'brand', 'description'];

    /**
     * Omezí dotaz na nabídky, které obsahují aspoň jedno ze slov. Porovnání MariaDB
     * (utf8mb4_unicode_ci) nerozlišuje diakritiku ani velikost písmen, slova jsou normalizovaná.
     *
     * @param  Builder<Offer>  $query
     * @param  list<string>  $words
     */
    public static function containingAny(Builder $query, array $words): void
    {
        $query->where(function (Builder $query) use ($words): void {
            foreach ($words as $word) {
                foreach (self::SEARCHED_COLUMNS as $column) {
                    $query->orWhere($column, 'like', self::likePattern($word));
                }
            }
        });
    }

    /**
     * Vzor LIKE pro normalizované slovo. Interpunkce se při normalizaci mění na mezeru
     * („K-Mistři“ → „k mistri“), v databázi ale zůstává („K-Mistři“) — hledá se proto jen
     * nejdelší část slova; zbytek ověří WatchItemMatcher.
     */
    public static function likePattern(string $word): string
    {
        $parts = explode(' ', $word);
        usort($parts, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return '%'.addcslashes($parts[0], '%_\\').'%';
    }
}
