<?php

/**
 * Přesměrování starých anglických adres přihlášení a registrace (/login, /register…)
 * na české (R73) — záložky, výsledky vyhledávačů a odkazy na obnovu hesla z už odeslaných
 * e-mailů. Cílová routa je ve výchozí hodnotě routy (TARGET), parametry cesty i dotazu
 * (?hlidat=…, ?email=…) se přenesou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class LegacyAuthRedirectController extends Controller
{
    /** Výchozí hodnota routy s názvem cílové routy. */
    public const TARGET = 'target';

    /**
     * Trvale přesměruje na cílovou routu se stejnými parametry cesty a dotazu.
     */
    public function __invoke(Request $request, Route $route): RedirectResponse
    {
        $target = $route->defaults[self::TARGET];
        $parameters = [...Arr::except($route->parameters(), self::TARGET), ...$request->query()];

        return redirect(route($target, $parameters, absolute: false), Response::HTTP_MOVED_PERMANENTLY);
    }
}
