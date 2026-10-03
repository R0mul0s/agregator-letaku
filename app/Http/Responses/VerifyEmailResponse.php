<?php

/**
 * Odpověď po ověření e-mailu odkazem (R51): Moje slevy s potvrzením v toastu (R47),
 * nebo stránka, kam uživatel mířil před přihlášením.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /** Kód stavu po ověření e-mailu — toast (R47, lang: ui.toast.messages). */
    public const STATUS_VERIFIED = 'email-verified';

    /**
     * Přesměruje na úvodní stránku (nebo zamýšlenou adresu) s potvrzením.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', Response::HTTP_NO_CONTENT)
            : redirect()->intended(route('home', absolute: false))->with('status', self::STATUS_VERIFIED);
    }
}
