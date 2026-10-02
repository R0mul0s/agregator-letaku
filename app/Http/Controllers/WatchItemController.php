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

use App\Domain\Catalog\CategoryPaths;
use App\Domain\Matching\MyOffers;
use App\Http\Requests\WatchItemRequest;
use App\Models\Offer;
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
    /**
     * Hlídané položky s tím, co k nim teď je v akci (počet akcí, nejnižší cena, zmínky
     * v letácích — stejně jako v Mých slevách), a produkty katalogu k přidání.
     */
    public function index(Request $request, CategoryPaths $categories, MyOffers $myOffers): Response
    {
        /** @var User $user */
        $user = $request->user();
        $watchedProductIds = $user->watchItems()->whereNotNull('product_id')->pluck('product_id')->all();

        return Inertia::render('WatchItems', [
            'urls' => [
                'store' => route('watch-items.store', absolute: false),
                'home' => route('home', absolute: false),
            ],
            'watchItems' => array_map(fn (array $group): array => [
                ...$this->itemToPage($group['watchItem']),
                'offersCount' => count($group['offers']),
                'mentionsCount' => count($group['mentions']),
                'lowestPrice' => $this->lowestPrice($user, $myOffers, array_column($group['offers'], 'offer')),
            ], $myOffers->forUser($user)),
            // Katalog nahradil šablony (R31) — produkt jde hlídat jedním klepnutím, nejvýš jednou
            'products' => Product::query()->orderBy('name')->get()->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'categoryLabel' => $categories->label($product->category_id),
                'department' => $categories->department($product->category_id),
                'watched' => in_array($product->id, $watchedProductIds, true),
            ]),
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
     * Nejnižší cena, kterou uživatel za některou z akcí zaplatí (s kartou, pokud ji má);
     * null, když žádná akce cenu nemá.
     *
     * @param  list<Offer>  $offers
     */
    private function lowestPrice(User $user, MyOffers $myOffers, array $offers): ?int
    {
        $prices = array_filter(array_map(fn (Offer $offer): ?int => $myOffers->userPrice($user, $offer), $offers), fn (?int $price): bool => $price !== null);

        return $prices === [] ? null : min($prices);
    }

    /**
     * Založí položku.
     */
    public function store(WatchItemRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->watchItems()->create($request->watchItemData());

        return to_route('watch-items.index');
    }

    /**
     * Uloží změny položky.
     */
    public function update(WatchItemRequest $request, WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $watchItem->update($request->watchItemData());

        return to_route('watch-items.index');
    }

    /**
     * Smaže položku.
     */
    public function destroy(WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('delete', $watchItem);
        $watchItem->delete();

        return to_route('watch-items.index');
    }
}
