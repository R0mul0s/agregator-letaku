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
