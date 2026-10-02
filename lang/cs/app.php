<?php

/**
 * Texty aplikace Agregátor letáků.
 *
 * Skupina `ui` se sdílí do Vue přes Inertia (HandleInertiaRequests) a čte se
 * helperem `t()` z resources/js/lib/i18n.js.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

return [

    'meta' => [
        'description' => 'Hlídání akčních nabídek z letáků obchodů Kaufland, Tesco, Albert, Lidl a Penny.',
    ],

    // App\Enums\Chain
    'chains' => [
        'kaufland' => 'Kaufland',
        'tesco' => 'Tesco',
        'albert' => 'Albert',
        'lidl' => 'Lidl',
        'penny' => 'Penny',
    ],

    // App\Enums\StoreFormat
    'store_formats' => [
        'hypermarket' => 'Hypermarket',
        'supermarket' => 'Supermarket',
    ],

    'ui' => [
        'app_name' => 'Agregátor letáků',
        'skip_to_content' => 'Přeskočit na obsah',

        'nav' => [
            'label' => 'Hlavní navigace',
            'home' => 'Moje slevy',
            'account' => 'Účet',
        ],

        'theme' => [
            'label' => 'Vzhled',
            'light' => 'Světlý',
            'dark' => 'Tmavý',
            'system' => 'Podle systému',
        ],

        'auth' => [
            'logout' => 'Odhlásit se',
            'name' => 'Jméno',
            'email' => 'E-mail',
            'password' => 'Heslo',
            'password_confirmation' => 'Heslo znovu',
            'remember' => 'Zapamatovat si mě',

            'login' => [
                'title' => 'Přihlášení',
                'submit' => 'Přihlásit se',
                'forgot' => 'Zapomenuté heslo',
                'no_account' => 'Ještě nemáte účet?',
                'register' => 'Zaregistrujte se',
            ],

            'register' => [
                'title' => 'Registrace',
                'submit' => 'Zaregistrovat se',
                'has_account' => 'Už máte účet?',
                'login' => 'Přihlaste se',
            ],

            'forgot' => [
                'title' => 'Zapomenuté heslo',
                'intro' => 'Zadejte e-mail účtu a pošleme vám odkaz pro nastavení nového hesla.',
                'submit' => 'Poslat odkaz',
                'back' => 'Zpět na přihlášení',
            ],

            'reset' => [
                'title' => 'Nové heslo',
                'submit' => 'Nastavit heslo',
            ],
        ],

        'home' => [
            'title' => 'Moje slevy',
            'empty' => 'Zatím nesledujete žádné položky. Výběr prodejen a hlídaných položek brzy přibude.',
        ],

        'account' => [
            'title' => 'Účet',
            'profile' => 'Osobní údaje',
            'password' => 'Změna hesla',
            'current_password' => 'Současné heslo',
            'new_password' => 'Nové heslo',
            'save' => 'Uložit',
            // Kódy stavu, které Fortify vrací po uložení
            'status' => [
                'profile-information-updated' => 'Osobní údaje jsou uložené.',
                'password-updated' => 'Heslo je změněné.',
            ],
        ],
    ],

];
