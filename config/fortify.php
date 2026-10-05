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
    | České adresy jako zbytek webu (/hlidam, /obchody, R73). Klíč je název routy Fortify
    | (password.request → password.request); názvy rout zůstávají, mění se jen cesty.
    | Staré anglické adresy přesměrovává routes/web.php. Změna cesty s tokenem nebo e-mailem
    | = upravit i letaky.cookie_consent.redacted_paths.
    */
    'paths' => [
        'login' => '/prihlaseni',
        'logout' => '/odhlasit',
        'register' => '/registrace',
        'password' => [
            'request' => '/zapomenute-heslo',
            'email' => '/zapomenute-heslo',
            'reset' => '/nove-heslo/{token}',
            'update' => '/nove-heslo',
            'confirm' => '/ucet/potvrzeni-hesla',
            'confirmation' => '/ucet/potvrzeni-hesla/stav',
        ],
        'verification' => [
            'notice' => '/overeni-emailu',
            'verify' => '/overeni-emailu/{id}/{hash}',
            'send' => '/overeni-emailu/znovu',
        ],
        'user-profile-information' => [
            'update' => '/ucet/udaje',
        ],
        'user-password' => [
            'update' => '/ucet/heslo',
        ],
    ],

    /*
    | Ověření e-mailu (R51): bez něj by šlo zaregistrovat cizí adresu a posílat na ni
    | souhrny. Aplikace jde používat i před ověřením, e-maily ale chodí jen na ověřenou
    | adresu. Dvoufázové ověření a passkeys vypnuté — stačí heslo s omezením pokusů.
    */
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
    ],

];
