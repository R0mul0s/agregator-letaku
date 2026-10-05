<?php

/**
 * Odpověď po registraci (R55): rovnou do Hlídám s uvítáním v toastu (R47) — nový účet
 * už sleduje všechny obchody, chybí jen hlídané položky. Přišel-li uživatel z karty akce
 * („Hlídat“ ve Všech akcích, R60), produkt katalogu se rovnou začne hlídat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /** Kód stavu po registraci — toast (R47, lang: ui.toast.messages). */
    public const STATUS_REGISTERED = 'registered';

    /** Parametr adresy registrace s produktem k hlídání (`/registrace?hlidat=12`, R60, R73). */
    public const WATCH_PARAMETER = 'hlidat';

    /** Klíč v relaci, kde produkt čeká na dokončení registrace. */
    public const SESSION_KEY = 'watch_after_register';

    /**
     * Zapamatuje si produkt z adresy registrace, pokud existuje (volá zobrazení registrace).
     */
    public static function rememberProduct(Request $request): void
    {
        $productId = $request->integer(self::WATCH_PARAMETER);
        if ($productId > 0 && Product::query()->whereKey($productId)->exists()) {
            $request->session()->put(self::SESSION_KEY, $productId);
        }
    }

    /**
     * Začne hlídat zapamatovaný produkt a přesměruje do Hlídám s uvítáním.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $productId = $request->session()->pull(self::SESSION_KEY);
        $user = $request->user();
        $product = is_int($productId) ? Product::query()->find($productId) : null;
        if ($user instanceof User && $product instanceof Product) {
            $user->watchItems()->firstOrCreate(['product_id' => $product->id], ['name' => $product->name]);
        }

        return $request->wantsJson()
            ? new JsonResponse('', Response::HTTP_CREATED)
            : redirect()->route('watch-items.index')->with('status', self::STATUS_REGISTERED);
    }
}
