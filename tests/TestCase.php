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

use App\Domain\Push\PushDelivery;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSender;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testy nepotřebují sestavené assety — Vite se vypíná. Žádný test nesmí
     * volat skutečný obchod ani LLM — jen přes Http::fake() (R11). Upozornění v telefonu
     * posílá vlastní klient Guzzle mimo Http::fake — bez podvrženého PushSender test spadne,
     * místo aby poslal požadavek push službě (R170).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        $this->app->instance(PushSender::class, new class implements PushSender
        {
            /**
             * Test poslal upozornění bez podvrženého odesílače.
             */
            public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
            {
                throw new LogicException('Test posílá upozornění v telefonu bez podvrženého PushSender (app()->instance).');
            }
        });
    }
}
