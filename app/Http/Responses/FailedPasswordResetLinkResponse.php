<?php

/**
 * Odpověď na žádost o obnovu hesla, kterou Fortify nevyřídil (účet neexistuje, odkaz poslaný
 * před chvílí). Vypadá stejně jako úspěch, aby nešlo zjistit, kdo u nás má účet (R170).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FailedPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponseContract
{
    /**
     * Stejné potvrzení jako po odeslání odkazu. Kód důvodu, který Fortify předává kontejneru
     * (`passwords.user`, `passwords.throttled`), se záměrně nepřebírá.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = __('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
