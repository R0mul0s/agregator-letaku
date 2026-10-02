<?php

/**
 * Omezení počtu požadavků (R45). Za proxy Websupportu je IP klienta z X-Forwarded-For
 * (trustProxies v bootstrap/app.php) — bez toho by všichni sdíleli jeden limit proxy.
 *
 * - PUBLIC: veřejné stránky a soubory pro roboty, podle IP.
 * - SUGGESTIONS: našeptávač (dotaz při psaní), podle IP.
 * - CRON: cron URL — zkoušení tokenu, podle IP.
 * - WRITES: všechny měnící požadavky (POST/PUT/DELETE) skupiny web, podle uživatele nebo IP;
 *   formuláře, které ověřují heslo nebo posílají e-mail, přísněji podle IP. Přihlášení má
 *   vlastní limit Fortify (login, FortifyServiceProvider).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class RateLimits
{
    public const PUBLIC = 'public';

    public const SUGGESTIONS = 'suggestions';

    public const CRON = 'cron';

    public const WRITES = 'writes';

    /**
     * Formuláře, které posílají e-mail nebo ověřují heslo — terč hádání hesel a spamu.
     * Názvy rout Fortify a účtu (R40).
     */
    private const SENSITIVE_ROUTES = [
        'register.store',
        'password.email',
        'password.update',
        'user-password.update',
        'account.destroy',
        'account.devices.logout',
    ];

    /**
     * Zaregistruje pojmenované limity (AppServiceProvider::boot).
     */
    public static function register(): void
    {
        RateLimiter::for(self::PUBLIC, fn (Request $request): Limit => Limit::perMinute(self::limit('public_per_minute'))->by((string) $request->ip()));

        RateLimiter::for(self::SUGGESTIONS, fn (Request $request): Limit => Limit::perMinute(self::limit('suggestions_per_minute'))->by((string) $request->ip()));

        RateLimiter::for(self::CRON, fn (Request $request): Limit => Limit::perMinute(self::limit('cron_per_minute'))->by((string) $request->ip()));

        RateLimiter::for(self::WRITES, function (Request $request): Limit {
            if ($request->isMethodSafe()) {
                return Limit::none();
            }

            if ($request->routeIs(...self::SENSITIVE_ROUTES)) {
                return Limit::perMinute(self::limit('sensitive_writes_per_minute'))->by('sensitive|'.$request->ip());
            }

            $user = $request->user();

            return Limit::perMinute(self::limit('writes_per_minute'))->by($user === null ? 'ip|'.$request->ip() : 'user|'.$user->getAuthIdentifier());
        });
    }

    /**
     * Počet požadavků za minutu z konfigurace letaky.rate_limits.
     */
    private static function limit(string $key): int
    {
        return config()->integer('letaky.rate_limits.'.$key);
    }
}
