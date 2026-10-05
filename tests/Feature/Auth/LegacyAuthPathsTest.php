<?php

/**
 * České adresy přihlášení a registrace a přesměrování starých anglických (R73).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

it('má přihlášení, registraci a obnovu hesla na českých adresách', function (string $route, string $path): void {
    expect(route($route, absolute: false))->toBe($path);
})->with([
    'přihlášení' => ['login', '/prihlaseni'],
    'odhlášení' => ['logout', '/odhlasit'],
    'registrace' => ['register', '/registrace'],
    'zapomenuté heslo' => ['password.request', '/zapomenute-heslo'],
    'ověření e-mailu' => ['verification.notice', '/overeni-emailu'],
]);

it('přesměruje starou anglickou adresu trvale na českou', function (string $legacy, string $target): void {
    $this->get($legacy)->assertStatus(301)->assertRedirect($target);
})->with([
    'přihlášení' => ['/login', '/prihlaseni'],
    'registrace s produktem k hlídání' => ['/register?hlidat=12', '/registrace?hlidat=12'],
    'zapomenuté heslo' => ['/forgot-password', '/zapomenute-heslo'],
    'odkaz na obnovu hesla z e-mailu' => ['/reset-password/abc123?email=nekdo%40example.com', '/nove-heslo/abc123?email=nekdo%40example.com'],
    'ověření e-mailu' => ['/email/verify', '/overeni-emailu'],
]);
