<?php

/**
 * Vzory pro SQL LIKE se zástupnými znaky z textu brané doslova („33 %“, „mléko_1l“) —
 * dřív `addcslashes` na sedmi místech (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support;

final class Like
{
    /**
     * Text se zástupnými znaky LIKE (`%`, `_`) a zpětným lomítkem jako obyčejnými znaky.
     */
    public static function escape(string $text): string
    {
        return addcslashes($text, '%_\\');
    }

    /**
     * Vzor „obsahuje text“.
     */
    public static function contains(string $text): string
    {
        return '%'.self::escape($text).'%';
    }

    /**
     * Vzor „začíná textem“.
     */
    public static function startsWith(string $text): string
    {
        return self::escape($text).'%';
    }
}
