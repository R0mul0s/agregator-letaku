<?php

/**
 * Oslovení v 5. pádě (R47) — běžná česká jména a výjimky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Support\CzechVocative;

it('skloní křestní jméno do 5. pádu', function (string $name, string $vocative): void {
    expect((new CzechVocative)->of($name))->toBe($vocative);
})->with([
    ['Roman', 'Romane'],
    ['Jan', 'Jane'],
    ['Petr', 'Petře'],
    ['Alexandr', 'Alexandře'],
    ['Igor', 'Igore'],
    ['Jaromír', 'Jaromíre'],
    ['Marek', 'Marku'],
    ['Zdeněk', 'Zdeňku'],
    ['Dominik', 'Dominiku'],
    ['Vojtěch', 'Vojtěchu'],
    ['Lukáš', 'Lukáši'],
    ['Ondřej', 'Ondřeji'],
    ['Max', 'Maxi'],
    ['Pavel', 'Pavle'],
    ['Karel', 'Karle'],
    ['Daniel', 'Danieli'],
    ['Michal', 'Michale'],
    ['Jakub', 'Jakube'],
    ['Jana', 'Jano'],
    ['Lenka', 'Lenko'],
    ['Honza', 'Honzo'],
    ['Julia', 'Julie'],
    ['Marie', 'Marie'],
    ['Jiří', 'Jiří'],
    ['Ivo', 'Ivo'],
    ['Testovací', 'Testovací'],
    ['Dagmar', 'Dagmar'],
    ['Marcel', 'Marceli'],
    ['ROMAN', 'ROMANE'],
    ['R2D2', 'R2D2'],
]);

it('z celého jména vezme křestní', function (): void {
    expect((new CzechVocative)->firstName(' Roman Hlaváček '))->toBe('Romane')
        ->and((new CzechVocative)->firstName(''))->toBe('');
});
