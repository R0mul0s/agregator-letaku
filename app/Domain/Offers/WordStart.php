<?php

/**
 * Hledání slova jako začátku slova v textu přes LIKE (R71) — „cola“ najde „Coca-Cola“,
 * „kola“ ale ne „čokoláda“. Bez ohledu na diakritiku a velikost písmen díky collation
 * utf8mb4_unicode_ci tabulek. Sdílí ho hledání ve Všech akcích a našeptávač.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use Illuminate\Database\Eloquent\Builder;

final class WordStart
{
    /**
     * Vzory začátku slova v LIKE: začátek textu, po mezeře, pomlčce, lomítku, závorce
     * a plusu („Coca-Cola“, „Fanta/Sprite“, „(bio)“, „PIZZA+COLA“).
     */
    private const PREFIXES = ['', '% ', '%-', '%/', '%(', '%+'];

    /**
     * Podmínka „slovo začíná některé slovo sloupce“ jako SQL s otazníky a vazbami (pro řazení).
     *
     * @param  literal-string  $column
     * @return array{literal-string, list<string>}
     */
    public static function sql(string $column, string $word): array
    {
        $sql = '(';
        foreach (array_keys(self::PREFIXES) as $index) {
            $sql .= ($index === 0 ? '' : ' OR ').$column.' LIKE ?';
        }

        return [$sql.')', self::patterns($word)];
    }

    /**
     * Omezí dotaz: slovo začíná některé slovo aspoň v jednom ze sloupců.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     */
    public static function where(Builder $query, array $columns, string $word): void
    {
        $query->where(function (Builder $query) use ($columns, $word): void {
            foreach ($columns as $column) {
                foreach (self::patterns($word) as $pattern) {
                    $query->orWhere($column, 'like', $pattern);
                }
            }
        });
    }

    /**
     * Vzory LIKE pro slovo — po jednom pro každý možný začátek slova.
     *
     * @return list<string>
     */
    private static function patterns(string $word): array
    {
        $escaped = addcslashes($word, '%_\\');

        return array_map(fn (string $prefix): string => $prefix.$escaped.'%', self::PREFIXES);
    }

    /**
     * Slova hledaného textu (oddělená mezerami).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        return preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
