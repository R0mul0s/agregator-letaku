<?php

/**
 * Relace jen pro čtení (R71) — pro JSON dotazy při psaní (našeptávač Všech akcí, náhled
 * vlastních slov v Hlídám). Takový dotaz běží souběžně s uložením formuláře: načte relaci
 * dřív, uloží ji později a přepíše tak zprávu, kterou uložení do relace dalo (toast, „Vrátit“)
 * — v databázi vyhrává poslední zápis. Dotaz relaci jen čte (přihlášení), zapisovat nemusí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Symfony\Component\HttpFoundation\Response;

final class ReadOnlySession
{
    /**
     * Po zpracování dotazu vymění úložiště relace za prázdné — StartSession pak nic nezapíše.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession()) {
            $request->session()->setHandler(new NullSessionHandler);
        }

        return $response;
    }
}
