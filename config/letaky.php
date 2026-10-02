<?php

/**
 * Konstanty aplikace Agregátor letáků.
 *
 * Do .env patří jen infrastruktura a tajemství; tady jsou výchozí hodnoty
 * a vše, co se v kódu nesmí objevit jako magic number.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

return [

    /*
    | Časová zóna pro zobrazení a pro „místní datum“ platnosti akcí (R7).
    | Aplikace i databáze běží v UTC.
    */
    'display_timezone' => env('LETAKY_DISPLAY_TIMEZONE', 'Europe/Prague'),

    /*
    | Barva lišty prohlížeče na mobilu podle režimu (= --color-bg v base/_tokens.scss).
    */
    'theme_colors' => [
        'light' => '#f4f6f8',
        'dark' => '#11161d',
    ],

    /*
    | Přihlášení (R12) — pokusy za minutu pro dvojici e-mail + IP.
    */
    'auth' => [
        'login_attempts_per_minute' => 5,
    ],

];
