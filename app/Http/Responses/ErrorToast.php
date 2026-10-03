<?php

/**
 * Chyby, které se u požadavku Inertie neukazují jako chybová stránka, ale jako toast (R47, R51):
 * vypršelá relace (419) a překročený limit požadavků (429). Chybová stránka by se u formuláře
 * otevřela v modálním okně přes celou aplikaci; návrat zpět zachová rozepsaný formulář.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ErrorToast
{
    /** Kód chyby HTTP => kód stavu toastu (lang: ui.toast.messages). */
    public const STATUSES = [
        419 => 'session-expired',
        429 => 'too-many-requests',
    ];

    /**
     * Požadavek Inertie s chybou ze seznamu vrátí zpět s toastem, ostatní odpovědi nechá být
     * (běžné načtení stránky dostane českou chybovou stránku z resources/views/errors).
     */
    public static function respond(Response $response, Request $request): Response
    {
        $status = self::STATUSES[$response->getStatusCode()] ?? null;

        return $status !== null && $request->hasHeader('X-Inertia')
            ? back()->with('status', $status)
            : $response;
    }
}
