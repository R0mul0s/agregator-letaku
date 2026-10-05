<?php

/**
 * Nákupní seznam (R61): akce z Mých slev a Všech akcí, seřazené podle obchodu, aby se daly
 * v obchodě odškrtávat. Skončené akce zůstávají, jen se označí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Matching\MyOffers;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPresenter;
use App\Enums\Chain;
use App\Http\Requests\ShoppingListRequest;
use App\Http\Requests\ShoppingListSyncRequest;
use App\Models\ShoppingListItem;
use App\Models\User;
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

    /**
     * Seznam po obchodech v pořadí výčtu obchodů; v obchodě nejdřív, co zbývá koupit, a z toho
     * nejdřív akce, které už platí — budoucí (R76) se zatím za akční cenu koupit nedají.
     */
    public function index(Request $request, OfferPresenter $presenter, MyOffers $myOffers, LocalCalendar $calendar): Response
    {
        $user = $this->user($request);
        $today = $calendar->today();
        $items = $user->shoppingListItems()->with('offer.stores')->get();
        $storeCodes = $user->selectedStoreCodes();

        $groups = [];
        foreach (Chain::cases() as $chain) {
            $inChain = $items
                ->filter(fn (ShoppingListItem $item): bool => $item->offer->chain === $chain)
                ->sortBy(fn (ShoppingListItem $item): array => [$item->checked_at !== null, $item->offer->isUpcoming($today), $item->offer->name])
                ->values();
            if ($inChain->isEmpty()) {
                continue;
            }

            $groups[] = [
                'chain' => $chain->value,
                'chainName' => $chain->label(),
                'items' => $inChain->map(fn (ShoppingListItem $item): array => [
                    'id' => $item->id,
                    'checked' => $item->checked_at !== null,
                    'expired' => $item->offer->valid_to->lessThan($today),
                    'userPrice' => $myOffers->userPrice($user, $item->offer),
                    'offer' => $presenter->toPage($item->offer, $storeCodes),
                    'updateUrl' => route('shopping-list.update', $item, absolute: false),
                    'deleteUrl' => route('shopping-list.destroy', $item, absolute: false),
                ])->all(),
            ];
        }

        return Inertia::render('ShoppingList', [
            'groups' => $groups,
            'clearCheckedUrl' => route('shopping-list.clear-checked', absolute: false),
            'hasChecked' => $items->contains(fn (ShoppingListItem $item): bool => $item->checked_at !== null),
        ]);
    }

    /**
     * Tlačítko na kartě akce: akci v seznamu odebere, jinak ji přidá; vrátí se, odkud uživatel přišel.
     */
    public function toggle(ShoppingListRequest $request): RedirectResponse
    {
        $items = $this->user($request)->shoppingListItems();
        $removed = (clone $items)->where('offer_id', $request->offerId())->delete() > 0;
        if (! $removed) {
            $items->create(['offer_id' => $request->offerId()]);
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
     * Smaže položku ze seznamu.
     */
    public function destroy(ShoppingListItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);
        $item->delete();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_REMOVED);
    }

    /**
     * Smaže všechny odškrtnuté položky — po nákupu.
     */
    public function clearChecked(Request $request): RedirectResponse
    {
        $this->user($request)->shoppingListItems()->whereNotNull('checked_at')->delete();

        return back(fallback: route('shopping-list.index'))->with('status', self::STATUS_CLEARED);
    }

    /**
     * Přihlášený uživatel (routy jsou za middleware auth).
     */
    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
