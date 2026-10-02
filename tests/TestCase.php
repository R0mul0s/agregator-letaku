<?php

/**
 * Základní třída testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testy nepotřebují sestavené assety — Vite se vypíná. Žádný test nesmí
     * volat skutečný obchod ani LLM — jen přes Http::fake() (R11).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
