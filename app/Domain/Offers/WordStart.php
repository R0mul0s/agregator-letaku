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
     * Znaky, po kterých začíná slovo („Coca-Cola“, „Fanta/Sprite“, „(bio)“, „PIZZA+COLA“,
     * „Sýr "Gouda"“, „Kofola.Original“). Stejná interpunkce, kterou párování hlídaných položek
     * (TextNormalizer) mění na mezeru, ať hledání a Moje slevy najdou pro slovo totéž (R113);
     * čárka ne — „0,5 l“ je jedno číslo i tam.
     */
    public const SEPARATORS = [' ', '-', '/', '(', ')', '+', '.', '&', ':', ';', '!', '?', '"', "'", '%', '*', '„', '“', '’'];

    /**
     * Podmínka „slovo začíná některé slovo sloupce“ jako SQL s otazníky a vazbami (pro řazení).
     *
     * @param  literal-string  $column
     * @return array{literal-string, list<string>}
     */
    public static function sql(string $column, string $word): array
    {
        // Nejdřív jedno LIKE „obsahuje“ — řádek bez slova nemusí zkoušet všechny začátky
        $sql = '('.$column.' LIKE ? AND (';
        foreach (array_keys(self::prefixes()) as $index) {
            $sql .= ($index === 0 ? '' : ' OR ').$column.' LIKE ?';
        }

        return [$sql.'))', [self::containsPattern($word), ...self::patterns($word)]];
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
                $query->orWhere(fn (Builder $query) => $query
                    ->where($column, 'like', self::containsPattern($word))
                    ->where(function (Builder $query) use ($column, $word): void {
                        foreach (self::patterns($word) as $pattern) {
                            $query->orWhere($column, 'like', $pattern);
                        }
                    }));
            }
        });
    }

    /**
     * Vzor LIKE „sloupec slovo obsahuje“ — levný předvýběr před začátky slov.
     */
    private static function containsPattern(string $word): string
    {
        return '%'.self::escape($word).'%';
    }

    /**
     * Vzory LIKE pro slovo — po jednom pro každý možný začátek slova.
     *
     * @return list<string>
     */
    private static function patterns(string $word): array
    {
        $escaped = self::escape($word);

        return array_map(fn (string $prefix): string => $prefix.$escaped.'%', self::prefixes());
    }

    /**
     * Vzory začátku slova v LIKE: začátek textu, nebo po některém z oddělovačů.
     *
     * @return list<string>
     */
    private static function prefixes(): array
    {
        return ['', ...array_map(fn (string $separator): string => '%'.self::escape($separator), self::SEPARATORS)];
    }

    /**
     * Text pro LIKE se zástupnými znaky jako obyčejnými („%“ v „1,5 %“).
     */
    private static function escape(string $text): string
    {
        return addcslashes($text, '%_\\');
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
