<?php

/**
 * Konfigurace Inertie — jen to, co se liší od výchozí konfigurace balíčku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

return [

    /*
    | Stránky jsou v resources/js/Pages (velké P), balíček je čeká v js/pages.
    | Lokálně to nevadí — Docker na Windows sdílí složku bez rozlišení velikosti
    | písmen — ale na Linuxu (CI) by testy stránky nenašly. Konfigurace balíčku
    | se slučuje jen na nejvyšší úrovni, proto celá sekce.
    |
    | ensure_pages_exist: assertInertia() v testech ověří, že Vue stránka existuje.
    */
    'pages' => [
        'ensure_pages_exist' => true,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => ['vue'],
    ],

];
