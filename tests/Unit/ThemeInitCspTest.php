<?php

/**
 * Vložený skript vzhledu (public/theme-init.js, app.blade.php) pouští CSP v public/.htaccess
 * jen podle otisku SHA-256 — změna souboru bez nového otisku by skript na produkci zablokovala
 * a stránka by problikla špatným režimem a ukázala obsah pro roboty.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

it('otisk vloženého theme-init.js v CSP sedí s obsahem souboru', function (): void {
    $script = trim((string) file_get_contents(dirname(__DIR__, 2).'/public/theme-init.js'));
    $hash = base64_encode(hash('sha256', $script, true));

    expect((string) file_get_contents(dirname(__DIR__, 2).'/public/.htaccess'))
        ->toContain("script-src 'self' 'sha256-{$hash}'");
});
