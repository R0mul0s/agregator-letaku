<?php

/**
 * Skript vložený přímo do HTML (theme-init.js, R97) bez komentářů a odsazení (R99).
 * Soubor má hlavičku a komentáře podle CODING_GUIDELINES; do stránky jde jen kód, aby každá
 * stránka nenesla vnitřní poznámky a zbytečné bajty. CSP pouští vložený skript podle otisku
 * SHA-256 (public/.htaccess) — otisk se počítá z výsledku této třídy (ThemeInitCspTest).
 *
 * Zjednodušení pro malé skripty: komentář je jen blok `/* … *\/` nebo celý řádek `// …`.
 * Komentář na konci řádku s kódem ani řetězce s `/*` nebo `//` neřeší — takový skript sem nepatří.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Support;

final class InlineScript
{
    /** Blokový komentář včetně víceřádkového (hlavička souboru). */
    private const BLOCK_COMMENT_PATTERN = '#/\*.*?\*/#s';

    /** Začátek řádkového komentáře. */
    private const LINE_COMMENT_PREFIX = '//';

    /**
     * Obsah skriptu ze souboru připravený k vložení do HTML; null = soubor neexistuje.
     */
    public static function fromFile(string $path): ?string
    {
        return is_file($path) ? self::strip((string) file_get_contents($path)) : null;
    }

    /**
     * Odstraní komentáře, odsazení a prázdné řádky; řádky kódu zůstanou pod sebou.
     */
    public static function strip(string $source): string
    {
        $code = (string) preg_replace(self::BLOCK_COMMENT_PATTERN, '', $source);
        $lines = array_filter(
            array_map('trim', explode("\n", str_replace("\r\n", "\n", $code))),
            fn (string $line): bool => $line !== '' && ! str_starts_with($line, self::LINE_COMMENT_PREFIX),
        );

        return implode("\n", $lines);
    }
}
