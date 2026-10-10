<?php

/**
 * Nákupní seznam (R61): akce z Mých slev a Všech akcí a vlastní položky bez akce (R130),
 * seřazené podle obchodu, aby se daly v obchodě odškrtávat. Skončené akce zůstávají, jen se
 * označí. Seznam jde sdílet veřejným odkazem (R130, SharedShoppingListController).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\LocalCalendar;
use App\Domain\Shopping\ShoppingListView;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Http\Requests\ShoppingListCustomRequest;
use App\Http\Requests\ShoppingListQuantityRequest;
use App\Http\Requests\ShoppingListRequest;
use App\Http\Requests\ShoppingListSyncRequest;
use App\Models\ShoppingListItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingListController extends Controller
{
    /** Kódy stavu pro toast (R47, lang: ui.toast.messages). */
    public const STATUS_ADDED = 'shopping-added';

    public const STATUS_REMOVED = 'shopping-removed';

    public const STATUS_CLEARED = 'shopping-cleared';

    /** Skončené akce smazané ze seznamu (R130). */
    public const STATUS_EXPIRED_CLEARED = 'shopping-expired-cleared';

    /** Nový odkaz ke sdílení, starý přestal fungovat (R130). */
    public const STATUS_SHARE_RENEWED = 'shopping-share-renewed';

    /**
     * Seznam po obchodech (ShoppingListView) s přidáním akce nebo vlastní položky a odkazem
     * ke sdílení (R130).
     */
    public function index(Request $request, ShoppingListView $view, SourceRegistry $sources, LocalCalendar $calendar): Response
    {
        $user = $this->user($request);
        $items = $view->items($user);
        $today = $calendar->today();

        return Inertia::render('ShoppingList', [
            'groups' => $view->groups(
                $user,
                $items,
                fn (ShoppingListItem $item): string => route('shopping-list.update', $item, absolute: false),
                fn (ShoppingListItem $item): string => route('shopping-list.destroy', $item, absolute: false),
                fn (ShoppingListItem $item): string => route('shopping-list.quantity', $item, absolute: false),
            ),
            'maxQuantity' => config()->integer('letaky.shopping_list.max_quantity'),
            'clearCheckedUrl' => route('shopping-list.clear-checked', absolute: false),
            'hasChecked' => $items->contains(fn (ShoppingListItem $item): bool => $item->checked_at !== null),
            // Úklid skončených akcí (R130) — vlastní položky neskončí
            'clearExpiredUrl' => route('shopping-list.clear-expired', absolute: false),
            'hasExpired' => $items->contains(fn (ShoppingListItem $item): bool => $item->offer !== null && $item->offer->valid_to->lessThan($today)),
            // Přidání (R130): našeptávač akcí, vlastní položka a obchody, kde ji koupit
            'add' => [
                'customUrl' => route('shopping-list.custom', absolute: false),
                'suggestionsUrl' => route('offers.suggestions', absolute: false),
                'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
                'nameMaxLength' => ShoppingListCustomRequest::NAME_MAX_LENGTH,
                'chains' => array_map(fn (Chain $chain): string => $chain->value, $sources->chainsWithOffers()),
            ],
            // Veřejný odkaz (R130): kdo ho má, seznam vidí a odškrtává; nový odkaz zneplatní starý
            'share' => [
                'url' => route('shopping-list.shared', ['token' => $user->shoppingShareToken()]),
                'renewUrl' => route('shopping-list.share.renew', absolute: false),
            ],
        ]);
    }

    /**
     * Vlastní položka bez akce (R130): název a nepovinně obchod. Stejná nekoupená položka
     * se nepřidá podruhé.
     */
    public function storeCustom(ShoppingListCustomRequest $request): RedirectResponse
    {
        $items = $this->user($request)->shoppingListItems();
        $exists = (clone $items)
            ->whereNull('offer_id')
            ->whereNull('checked_at')
            ->where('custom_name', $request->itemName())
            ->where('chain', $request->chain())
            ->exists();
        if (! $exists) {
            $items->create(['custom_name' => $request->itemName(), 'chain' => $request->chain(), 'quantity' => $request->quantity()]);
        }

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_ADDED);
    }

    /**
     * Nový odkaz ke sdílení (R130) — dosavadní odkazy přestanou fungovat.
     */
    public function renewShare(Request $request): RedirectResponse
    {
        $this->user($request)->renewShoppingShareToken();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_SHARE_RENEWED);
    }

    /**
     * Tlačítko na kartě akce: akci v seznamu odebere, jinak ji přidá; vrátí se, odkud uživatel přišel.
     */
    public function toggle(ShoppingListRequest $request): RedirectResponse
    {
        $items = $this->user($request)->shoppingListItems();
        $removed = (clone $items)->where('offer_id', $request->offerId())->delete() > 0;
        if (! $removed) {
            $items->create(['offer_id' => $request->offerId(), 'quantity' => $request->quantity()]);
        }

        return back(fallback: route('shopping-list.index'))->with('status', $removed ? self::STATUS_REMOVED : self::STATUS_ADDED);
    }

    /**
     * Odškrtne položku v obchodě, nebo odškrtnutí zruší.
     */
    public function update(Request $request, ShoppingListItem $item): RedirectResponse
    {
        Gate::authorize('update', $item);
        $item->update(['checked_at' => $request->boolean('checked') ? CarbonImmutable::now() : null]);

        return back(fallback: route('shopping-list.index'));
    }

    /**
     * Odškrtnutí udělaná v obchodě bez signálu (R66) — prohlížeč je pošle najednou, až je
     * připojení. Položky, které mezitím zmizely (smazané na jiném zařízení), přeskočí.
     */
    public function sync(ShoppingListSyncRequest $request): RedirectResponse
    {
        $items = $this->user($request)->shoppingListItems();
        $changes = $request->changes();

        (clone $items)->whereKey($changes['checked'])->whereNull('checked_at')->update(['checked_at' => CarbonImmutable::now()]);
        (clone $items)->whereKey($changes['unchecked'])->update(['checked_at' => null]);

        return back(fallback: route('shopping-list.index'));
    }

    /**
     * Změní množství položky (R133) — počet kusů nebo balení; bez toastu, stránka zůstane.
     */
    public function quantity(ShoppingListQuantityRequest $request, ShoppingListItem $item): RedirectResponse
    {
        Gate::authorize('update', $item);
        $item->update(['quantity' => $request->quantity()]);

        return back(fallback: route('shopping-list.index'));
    }

    /**
     * Smaže položku ze seznamu.
     */
    public function destroy(ShoppingListItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);
        $item->delete();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_REMOVED);
    }

    /**
     * Smaže akce, které skončily (R130) — za akční cenu už nejsou; vlastní položky zůstanou.
     */
    public function clearExpired(Request $request, LocalCalendar $calendar): RedirectResponse
    {
        $this->user($request)->shoppingListItems()
            ->whereHas('offer', fn ($query) => $query->where('valid_to', '<', $calendar->today()->toDateString()))
            ->delete();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_EXPIRED_CLEARED);
    }

    /**
     * Smaže všechny odškrtnuté položky — po nákupu.
     */
    public function clearChecked(Request $request): RedirectResponse
    {
        $this->user($request)->shoppingListItems()->whereNotNull('checked_at')->delete();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_CLEARED);
    }
}
