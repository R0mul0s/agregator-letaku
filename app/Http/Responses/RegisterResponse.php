<?php

/**
 * Odpověď po registraci (R55): rovnou do Hlídám s uvítáním v toastu (R47) — nový účet
 * už sleduje všechny obchody, chybí jen hlídané položky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /** Kód stavu po registraci — toast (R47, lang: ui.toast.messages). */
    public const STATUS_REGISTERED = 'registered';

    /**
     * Přesměruje do Hlídám s uvítáním.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', Response::HTTP_CREATED)
            : redirect()->route('watch-items.index')->with('status', self::STATUS_REGISTERED);
    }
}
