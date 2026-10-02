<?php

/**
 * Katalog produktů (R29, R33, R37) — produkty z database/seeders/data/catalog-products.php.
 * Existující produkt podle názvu přepíše a přiřadí k produktům akce. Kategorie se přiřadí,
 * jen když je strom stažený (`letaky:import-categories`) a cesta v něm existuje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Actions\AssignProducts;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /** Produkty s pravidly a cestou ke kategorii (data mimo kód, ať se seznam dobře čte a rozšiřuje). */
    private const PRODUCTS_FILE = __DIR__.'/data/catalog-products.php';

    /**
     * Založí výchozí produkty (existující podle názvu přepíše) a přiřadí k nim nabídky.
     */
    public function run(AssignProducts $assign): void
    {
        /** @var list<array{name: string, keywords: string, variant_keywords: string|null, exclude_keywords: string|null, category: list<string>}> $products */
        $products = require self::PRODUCTS_FILE;

        foreach ($products as $data) {
            $product = Product::query()->updateOrCreate(['name' => $data['name']], [
                'keywords' => $data['keywords'],
                'variant_keywords' => $data['variant_keywords'],
                'exclude_keywords' => $data['exclude_keywords'],
                'category_id' => $this->categoryId($data['category']),
            ]);
            $assign->forProduct($product);
        }
    }

    /**
     * Kategorie podle cesty názvů od oddělení; null, když strom není stažený nebo cesta neexistuje.
     *
     * @param  list<string>  $path
     */
    private function categoryId(array $path): ?int
    {
        $parentId = null;
        foreach ($path as $name) {
            $parentId = Category::query()->where('parent_id', $parentId)->where('name', $name)->value('id');
            if ($parentId === null) {
                return null;
            }
        }

        return $parentId;
    }
}
