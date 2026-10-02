<?php

/**
 * Úprava textů od obchodů — zalomení řádků a vícenásobné mezery na jednu mezeru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

final class Text
{
    /**
     * Sloučí bílé znaky do jedné mezery; prázdný text je null.
     */
    public static function clean(?string $text): ?string
    {
        $cleaned = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Spojí neprázdné části mezerou; když jsou všechny prázdné, vrátí null.
     */
    public static function join(?string ...$parts): ?string
    {
        return self::clean(implode(' ', array_filter($parts, fn (?string $part): bool => self::clean($part) !== null)));
    }
}
