<?php

/**
 * Konfigurace aplikace — routy, middleware, výjimky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Nepřihlášený jde na přihlášení, přihlášený z přihlášení na svůj seznam slev
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Výchozí chování stačí — JSON dostane, kdo ho žádá (Accept); API routy nejsou.
    })->create();
