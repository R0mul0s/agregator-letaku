<?php

/**
 * Produkty katalogu k hlídání pro uživatele — našeptávač a katalog v Hlídám (R71) i průvodce
 * prvním nastavením (R153): kategorie, ikona oddělení, jestli ho už hlídá a kolik akcí by teď
 * v Mých slevách viděl s nejnižší cenou, kterou zaplatí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Matching\MyOffers;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class WatchableProducts
{
    public function __construct(
        private readonly CategoryPaths $categories,
        private readonly MyOffers $myOffers,
    ) {}

    /**
     * Produkty seřazené podle názvu.
     *
     * @return Collection<int, Product>
     */
    public function all(): Collection
    {
        return Product::query()->orderBy('name')->get();
    }

    /**
     * Produkty pro stránku.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array{id: int, name: string, categoryLabel: string|null, categoryName: string|null, department: string|null, icon: string, watched: bool, offersCount: int, lowestPrice: int|null}>
     */
    public function toPage(User $user, Collection $products): array
    {
        $watchedProductIds = $user->watchItems()->whereNotNull('product_id')->pluck('product_id')->all();
        $stats = $this->myOffers->productStats($user);

        return array_values($products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'categoryLabel' => $this->categories->label($product->category_id),
            'categoryName' => $this->categories->name($product->category_id),
            'department' => $this->categories->department($product->category_id),
            'icon' => CatalogBrowseTree::icon($this->categories->department($product->category_id)),
            'watched' => in_array($product->id, $watchedProductIds, true),
            'offersCount' => $stats[$product->id]['count'] ?? 0,
            'lowestPrice' => $stats[$product->id]['lowestPrice'] ?? null,
        ])->all());
    }
}
