<?php

/**
 * Konfigurace Laravel Fortify — přihlášení, registrace, obnova hesla (R12).
 *
 * Fortify dodává jen backend; stránky jsou Inertia + Vue a registruje je
 * App\Providers\FortifyServiceProvider.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Kam po přihlášení a registraci — seznam slev uživatele
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    // Fortify registruje GET routy stránek (login, register…); obsah dodá FortifyServiceProvider
    'views' => true,

    /*
    | Dvoufázové ověření, passkeys a ověření e-mailu záměrně vypnuté — aplikace
    | je zatím jen pro vlastní použití (R5), stačí heslo s omezením pokusů.
    */
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
    ],

];
