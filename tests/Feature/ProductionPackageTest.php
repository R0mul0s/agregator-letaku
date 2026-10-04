<?php

/**
 * Aplikace musí běžet z nasazovacího balíčku (deploy/build-upload.ps1),
 * který nenese zdrojové soubory frontendu (resources/js).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

it('vykreslí stránku i bez zdrojových souborů Vue (resources/js na produkci není)', function (): void {
    config(['inertia.pages.paths' => [storage_path('framework/testing/missing-pages')]]);

    $this->get('/login')->assertOk();
});

it('balíček nese právní texty, které aplikace čte za běhu (R51)', function (): void {
    $script = (string) file_get_contents(base_path('deploy/build-upload.ps1'));

    expect($script)->toContain("'resources\\".config('letaky.legal.directory')."'");
});

it('balíček nese service worker, který server posílá na /sw.js (R66)', function (): void {
    $script = (string) file_get_contents(base_path('deploy/build-upload.ps1'));

    expect($script)->toContain("'resources\\pwa'")
        ->and(resource_path('pwa/service-worker.js'))->toBeFile();
});
