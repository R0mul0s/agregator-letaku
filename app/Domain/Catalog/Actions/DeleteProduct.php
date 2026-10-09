<?php

/**
 * Smazání produktu katalogu (R29, R31) i s přiřazeními a vyřazeními. Hlídané položky, které
 * produkt hlídají, dostanou jeho pravidla jako vlastní slova — uživatelům nic nezmizí.
 * Dřív v CatalogController (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class DeleteProduct
{
    /**
     * Zkopíruje pravidla do hlídaných položek a produkt smaže, obojí v jedné transakci.
     */
    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->watchItems()->update([
                'product_id' => null,
                'keywords' => $product->keywords,
                'variant_keywords' => $product->variant_keywords,
                'exclude_keywords' => $product->exclude_keywords,
            ]);
            $product->delete();
        });
    }
}
