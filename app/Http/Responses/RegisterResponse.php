<?php

/**
 * Odpověď po registraci (R55, R153): do průvodce prvním nastavením s uvítáním v toastu (R47)
 * — nový účet už sleduje všechny obchody, průvodce s ním projde hlídané položky, obchody, karty
 * a upozornění. Přišel-li uživatel z karty akce („Hlídat“ ve Všech akcích, R60) nebo z ukázky
 * hlídání na úvodní stránce (R90, víc produktů najednou), produkty katalogu se rovnou začnou
 * hlídat a v průvodci jsou vybrané. Registraci ohlásí Google Analytics událostí sign_up (R134).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\AnalyticsEvent;
use App\Models\Product;
use App\Models\User;
use App\Support\AnalyticsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /** Kód stavu po registraci — toast (R47, lang: ui.toast.messages). */
    public const STATUS_REGISTERED = 'registered';

    /** Parametr adresy registrace s produkty k hlídání (`/registrace?hlidat=12`, z ukázky `?hlidat=12,34`; R60, R73, R153). */
    public const WATCH_PARAMETER = 'hlidat';

    /** Oddělovač produktů v parametru adresy. */
    public const WATCH_SEPARATOR = ',';

    /** Klíč v relaci, kde produkty čekají na dokončení registrace. */
    public const SESSION_KEY = 'watch_after_register';

    /** Atribut požadavku se způsobem registrace pro Google Analytics (R134) — poskytovatel přihlášení. */
    public const METHOD_ATTRIBUTE = 'sign_up_method';

    /** Způsob registrace heslem (parametr `method` události sign_up). */
    public const METHOD_PASSWORD = 'email';

    /**
     * Zapamatuje si existující produkty z adresy registrace, nejvýš `letaky.onboarding.max_prefill`
     * (volá zobrazení registrace).
     */
    public static function rememberProduct(Request $request): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(intval(...), explode(self::WATCH_SEPARATOR, $request->string(self::WATCH_PARAMETER)->toString())),
            fn (int $id): bool => $id > 0,
        )));
        $existing = $ids === [] ? [] : Product::query()->whereKey($ids)->pluck('id')->all();
        // V pořadí z adresy (výběr v ukázce), neexistující se do limitu nepočítá
        $remembered = array_slice(array_values(array_intersect($ids, $existing)), 0, config()->integer('letaky.onboarding.max_prefill'));
        if ($remembered !== []) {
            $request->session()->put(self::SESSION_KEY, $remembered);
        }
    }

    /**
     * Začne hlídat zapamatované produkty, odloží události pro Google Analytics a přesměruje
     * do průvodce prvním nastavením s uvítáním.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $remembered = $request->session()->pull(self::SESSION_KEY);
        // Relace z doby před R153 nese jedno číslo
        $productIds = is_array($remembered) ? $remembered : (is_int($remembered) ? [$remembered] : []);
        $user = $request->user();
        $method = $request->attributes->get(self::METHOD_ATTRIBUTE, self::METHOD_PASSWORD);
        AnalyticsEvents::flash($request, AnalyticsEvent::SignUp, ['method' => is_string($method) ? $method : self::METHOD_PASSWORD]);
        if ($user instanceof User && $productIds !== []) {
            foreach (Product::query()->whereKey($productIds)->get() as $product) {
                $item = $user->watchItems()->firstOrCreate(['product_id' => $product->id], ['name' => $product->name]);
                if ($item->wasRecentlyCreated) {
                    AnalyticsEvents::flash($request, AnalyticsEvent::AddWatchItem, ['item_type' => 'product']);
                }
            }
        }

        return $request->wantsJson()
            ? new JsonResponse('', Response::HTTP_CREATED)
            : redirect()->route('onboarding')->with('status', self::STATUS_REGISTERED);
    }
}
