<?php

/**
 * Stránka „Hlídám“ — hlídané položky uživatele s přehledem aktuálních akcí a jejich úpravy (R18, R31).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogBrowseTree;
use App\Domain\Catalog\CategoryPaths;
use App\Domain\Matching\MyOffers;
use App\Http\Requests\WatchItemRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WatchItemController extends Controller
{
    /** Parametr adresy Hlídám, který otevře úpravu položky (odkaz z Mých slev). */
    public const EDIT_PARAMETER = 'upravit';

    /** Kódy stavu pro toast po uložení (R47, lang: ui.toast.messages). */
    public const STATUS_ADDED = 'watch-item-added';

    public const STATUS_UPDATED = 'watch-item-updated';

    public const STATUS_REMOVED = 'watch-item-removed';

    /**
     * Hlídané položky s tím, co k nim teď je v akci (počet akcí, nejnižší cena, zmínky
     * v letácích — stejně jako v Mých slevách), a produkty katalogu k přidání.
     */
    public function index(Request $request, CategoryPaths $categories, CatalogBrowseTree $browseTree, MyOffers $myOffers): Response
    {
        /** @var User $user */
        $user = $request->user();
        $watchedProductIds = $user->watchItems()->whereNotNull('product_id')->pluck('product_id')->all();
        $products = Product::query()->orderBy('name')->get();

        return Inertia::render('WatchItems', [
            'urls' => [
                'store' => route('watch-items.store', absolute: false),
                'home' => route('home', absolute: false),
            ],
            'watchItems' => array_map(fn (array $group): array => [
                ...$this->itemToPage($group['watchItem']),
                'offersCount' => count($group['offers']),
                'mentionsCount' => count($group['mentions']),
                'lowestPrice' => $myOffers->lowestPrice($user, array_column($group['offers'], 'offer')),
            ], $myOffers->forUser($user)),
            // Odkaz „Upravit“ z Mých slev (?upravit=id) otevře úpravu položky rovnou v dlaždici
            'editId' => $request->integer(self::EDIT_PARAMETER) ?: null,
            // Katalog nahradil šablony (R31) — produkt jde hlídat jedním klepnutím, nejvýš jednou
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'categoryLabel' => $categories->label($product->category_id),
                'department' => $categories->department($product->category_id),
                'watched' => in_array($product->id, $watchedProductIds, true),
            ]),
            // Procházení katalogu po odděleních jako v e-shopu (R47)
            'catalogTree' => $browseTree->build($products),
        ]);
    }

    /**
     * Položka pro stránku: pravidla a adresy úprav.
     *
     * @return array<string, mixed>
     */
    private function itemToPage(WatchItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'productId' => $item->product_id,
            'productName' => $item->product?->name,
            'keywords' => $item->keywords,
            'variantKeywords' => $item->variant_keywords,
            'excludeKeywords' => $item->exclude_keywords,
            'updateUrl' => route('watch-items.update', $item, absolute: false),
            'deleteUrl' => route('watch-items.destroy', $item, absolute: false),
        ];
    }

    /**
     * Založí položku.
     */
    public function store(WatchItemRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->watchItems()->create($request->watchItemData());

        return to_route('watch-items.index')->with('status', self::STATUS_ADDED);
    }

    /**
     * Uloží změny položky.
     */
    public function update(WatchItemRequest $request, WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $watchItem->update($request->watchItemData());

        return to_route('watch-items.index')->with('status', self::STATUS_UPDATED);
    }

    /**
     * Smaže položku a vrátí se na stránku, odkud uživatel přišel (Hlídám, nebo Moje slevy).
     */
    public function destroy(WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('delete', $watchItem);
        $watchItem->delete();

        return back(fallback: route('watch-items.index'))->with('status', self::STATUS_REMOVED);
    }
}
