<?php

/**
 * Množství položky nákupního seznamu (R133) — počet kusů nebo balení od 1 do
 * `letaky.shopping_list.max_quantity`. Pravidlo sdílí přidání akce, vlastní položky
 * i změna množství.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Shopping;

final class ShoppingQuantity
{
    /** Výchozí množství — položka bez uvedeného množství. */
    public const DEFAULT = 1;

    /**
     * Pravidla validace pole `quantity`.
     *
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'integer', 'min:'.self::DEFAULT, 'max:'.config()->integer('letaky.shopping_list.max_quantity')];
    }
}
