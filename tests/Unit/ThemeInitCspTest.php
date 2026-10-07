<?php

/**
 * Vložený skript vzhledu (public/theme-init.js, app.blade.php) pouští CSP v public/.htaccess
 * jen podle otisku SHA-256 — změna souboru bez nového otisku by skript na produkci zablokovala
 * a stránka by problikla špatným režimem a ukázala obsah pro roboty. Do stránky jde bez
 * komentářů (InlineScript, R99), otisk se počítá z toho.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Support\InlineScript;

it('otisk vloženého theme-init.js v CSP sedí s obsahem souboru bez komentářů', function (): void {
    $script = (string) InlineScript::fromFile(dirname(__DIR__, 2).'/public/theme-init.js');
    $hash = base64_encode(hash('sha256', $script, true));

    expect((string) file_get_contents(dirname(__DIR__, 2).'/public/.htaccess'))
        ->toContain("script-src 'self' 'sha256-{$hash}'");
});

it('vložený skript nemá komentáře ani prázdné řádky, kód zůstane', function (): void {
    $source = "/**\n * Hlavička\n * @author Roman Hlaváček\n */\n(function () {\n    // poznámka\n    var a = 1;\n\n    /* blok */ run(a);\n})();\n";

    expect(InlineScript::strip($source))->toBe("(function () {\nvar a = 1;\nrun(a);\n})();")
        ->and(InlineScript::fromFile(dirname(__DIR__, 2).'/public/theme-init.js'))->not->toContain('@author');
});
