<?php

/**
 * Konfigurace Pestu a sdílené pomocné funkce testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

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
