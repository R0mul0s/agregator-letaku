<?php

/**
 * Živá ukázka hlídání na úvodní stránce (JSON, R90) — počet akcí a nejlevnější akce
 * vybraných produktů ve vybraných obchodech.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\WatchDemo;
use App\Http\Requests\WatchDemoRequest;
use Illuminate\Http\JsonResponse;

class WatchDemoController extends Controller
{
    /**
     * Výsledek ukázky k výběru produktů a obchodů.
     */
    public function __invoke(WatchDemoRequest $request, WatchDemo $demo): JsonResponse
    {
        return response()->json($demo->result($request->productIds(), $request->chains()));
    }
}
