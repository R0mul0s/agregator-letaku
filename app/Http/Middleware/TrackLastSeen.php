<?php

/**
 * Zapíše poslední aktivitu přihlášeného uživatele (R84) — přehled uživatelů pro admina
 * ukazuje, kdy byl kdo naposledy online. Zapisuje se před vyřízením požadavku: odhlášení
 * se ještě počítá jako aktivita a admin v přehledu vidí online i sebe.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Account\UserPresence;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    public function __construct(private readonly UserPresence $presence) {}

    /**
     * Zapíše aktivitu přihlášeného uživatele a vyřídí požadavek.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->presence->touch($user);
        }

        return $next($request);
    }
}
