<?php

/**
 * Výchozí produkty katalogu (R29) — dřívější šablony hlídaných položek. Vyloučená slova
 * jsou ze skutečných nabídek 2. 10. 2026 (viz config/letaky.php, sekce watch). Kategorie
 * se přiřadí, jen když je strom stažený (`letaky:import-categories`).
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
    /** Produkty s pravidly a cestou ke kategorii ve stromu Tesca. */
    private const PRODUCTS = [
        [
            'name' => 'Vejce',
            'keywords' => 'vejce',
            'variant_keywords' => null,
            'exclude_keywords' => 'maggi polévka těstoviny toust aspik pomazánka bageta ruské',
            'category' => ['Mléčné, vejce a margaríny', 'Vejce a droždí', 'Vejce'],
        ],
        [
            'name' => 'Polotučné mléko',
            'keywords' => 'mléko polotučné|1,5',
            'variant_keywords' => null,
            'exclude_keywords' => 'kefír kokos zakysané acidofil čokoláda lipánek ochucené',
            'category' => ['Mléčné, vejce a margaríny', 'Mléko, mléčné a jogurtové nápoje'],
        ],
        [
            'name' => 'Máslo',
            'keywords' => 'máslo',
            'variant_keywords' => null,
            'exclude_keywords' => 'máslov arašíd kakao bylink pomazánk sušenk',
            'category' => ['Mléčné, vejce a margaríny', 'Máslo, margaríny a pomazánky', 'Máslo'],
        ],
        [
            'name' => 'Coca-Cola Zero',
            'keywords' => 'coca cola',
            'variant_keywords' => 'zero',
            'exclude_keywords' => null,
            'category' => ['Nápoje', 'Limonády a ledové čaje', 'Kolové nápoje bez cukru'],
        ],
    ];

    /**
     * Založí výchozí produkty (existující podle názvu přepíše) a přiřadí k nim nabídky.
     */
    public function run(AssignProducts $assign): void
    {
        foreach (self::PRODUCTS as $data) {
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
