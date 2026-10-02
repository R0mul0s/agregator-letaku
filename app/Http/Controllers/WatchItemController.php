<?php

/**
 * Stránka „Hlídám“ — hlídané položky uživatele a jejich úpravy (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CategoryPaths;
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
    /**
     * Seznam položek, formulář nové položky a produkty katalogu.
     */
    public function index(Request $request, CategoryPaths $categories): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('WatchItems', [
            'urls' => ['store' => route('watch-items.store', absolute: false)],
            'watchItems' => $user->watchItems()->with('product')->orderBy('name')->get()->map(fn (WatchItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'productId' => $item->product_id,
                'productName' => $item->product?->name,
                'keywords' => $item->keywords,
                'variantKeywords' => $item->variant_keywords,
                'excludeKeywords' => $item->exclude_keywords,
                'updateUrl' => route('watch-items.update', $item, absolute: false),
                'deleteUrl' => route('watch-items.destroy', $item, absolute: false),
            ]),
            // Katalog nahradil šablony (R31) — produkt jde hlídat jedním klepnutím
            'products' => Product::query()->orderBy('name')->get()->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'categoryLabel' => $categories->label($product->category_id),
            ]),
        ]);
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
