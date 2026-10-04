<?php

/**
 * Konfigurace Pestu a sdílené pomocné funkce testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Account\RegistrationGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Obsah fixture — zkrácené skutečné odpovědi obchodu (tests/Fixtures/README.md).
 */
function responseFixture(string $path): string
{
    $content = file_get_contents(__DIR__.'/Fixtures/'.$path);

    return $content !== false ? $content : throw new RuntimeException("Fixture {$path} neexistuje.");
}

/**
 * Fixture JSON jako pole.
 *
 * @return array<mixed>
 */
function jsonResponseFixture(string $path): array
{
    return json_decode(responseFixture($path), true, flags: JSON_THROW_ON_ERROR);
}

/** Silné heslo, které projde Password::default(). */
const NEW_PASSWORD = 'Nove-heslo-2026';

/** Jak dlouho „člověk“ v testu vyplňoval registraci (víc než letaky.auth.registration.min_seconds). */
const FILL_SECONDS = 30;

/**
 * Údaje registračního formuláře jako od člověka: token načtení formuláře před chvílí
 * a prázdné skryté pole (R53). Sdílené testy registrace a „Hlídat“ z karty akce (R60).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationInput(array $overrides = []): array
{
    return [
        'name' => 'Roman',
        'email' => 'roman@example.com',
        'password' => NEW_PASSWORD,
        'terms' => true,
        RegistrationGuard::TOKEN_FIELD => app(RegistrationGuard::class)->token(CarbonImmutable::now()->subSeconds(FILL_SECONDS)),
        RegistrationGuard::TRAP_FIELD => '',
        ...$overrides,
    ];
}
