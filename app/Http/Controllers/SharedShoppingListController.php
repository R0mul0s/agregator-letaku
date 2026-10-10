<?php

/**
 * Nákupní seznam sdílený odkazem (R130, `/seznam/s/{token}`) — kdo odkaz má (partner bez
 * účtu), vidí aktuální seznam vlastníka a odškrtává, co koupil; vlastník to po načtení vidí
 * u sebe. Přidávat a mazat může jen vlastník. Neplatný token (vlastník vytvořil nový odkaz)
 * je 404. Stránka se neindexuje a analytika ji vidí bez tokenu (`redacted_paths`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shopping\ShoppingListView;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SharedShoppingListController extends Controller
{
    /** Tvar tokenu v adrese (User::shoppingShareToken). */
    public const TOKEN_PATTERN = '[A-Za-z0-9]{40}';

    /**
     * Zobrazí sdílený seznam.
     */
    public function show(string $token, ShoppingListView $view): Response
    {
        $owner = $this->owner($token);

        return Inertia::render('SharedShoppingList', [
            // Jen křestní jméno — celé jméno ani e-mail se cizím neukazují
            'ownerName' => strtok($owner->name, ' ') ?: $owner->name,
            'groups' => $view->groups(
                $owner,
                $view->items($owner),
                fn (ShoppingListItem $item): string => route('shopping-list.shared.update', ['token' => $token, 'item' => $item->id], absolute: false),
                null,
            ),
            'registerUrl' => route('register', absolute: false),
        ]);
    }

    /**
     * Odškrtne položku, nebo odškrtnutí zruší — jen položku seznamu tohoto odkazu.
     */
    public function update(Request $request, string $token, int $item): RedirectResponse
    {
        $owner = $this->owner($token);
        $owner->shoppingListItems()->whereKey($item)->firstOrFail()
            ->update(['checked_at' => $request->boolean('checked') ? CarbonImmutable::now() : null]);

        return back(fallback: route('shopping-list.shared', ['token' => $token]));
    }

    /**
     * Vlastník seznamu podle tokenu odkazu; neplatný = 404.
     */
    private function owner(string $token): User
    {
        $owner = User::query()->where('shopping_share_token', $token)->first();
        abort_if($owner === null, HttpResponse::HTTP_NOT_FOUND);

        return $owner;
    }
}
