<?php

/**
 * Oddělení katalogu jako filtr Všech akcí (R102): Maso a lahůdky, Nápoje, Drogerie… — první
 * úroveň stromu kategorií Tesca (R28), jako dlaždice v Hlídám (R47). Akce patří do oddělení přes
 * produkt katalogu, ke kterému je přiřazená (`offer_product`); akce bez produktu (7. 10. 2026
 * zhruba třetina) v žádném oddělení není a při filtru se neukáže. V adrese je oddělení jako
 * část bez diakritiky (`?kategorie=maso-a-lahudky`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Catalog\CatalogBrowseTree;
use App\Domain\Catalog\CategoryPaths;
use App\Models\OfferProduct;
use Illuminate\Support\Str;

final class OfferDepartments
{
    public function __construct(
        private readonly CategoryPaths $paths,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Všechna oddělení stromu v jeho pořadí: část adresy => název.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $departments = [];
        foreach ($this->paths->all() as $label) {
            $name = explode(CategoryPaths::SEPARATOR, $label)[0];
            $departments[self::slug($name)] ??= $name;
        }

        return $departments;
    }

    /**
     * Oddělení, ve kterých jsou teď nějaké akce, v pořadí stromu — do výběru (prázdné se nenabízí).
     *
     * @return list<array{slug: string, name: string, icon: string}>
     */
    public function withOffers(): array
    {
        $categoryIds = OfferProduct::query()
            ->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->join('products', 'products.id', '=', 'offer_product.product_id')
            ->whereNull('offers.withdrawn_at')
            ->where('offers.valid_to', '>=', $this->calendar->today()->toDateString())
            ->whereNotNull('products.category_id')
            ->distinct()
            ->pluck('products.category_id')
            ->all();
        $present = array_flip(array_filter(array_map(fn (mixed $id): ?string => $this->paths->department(is_numeric($id) ? (int) $id : null), $categoryIds)));

        $result = [];
        foreach ($this->all() as $slug => $name) {
            if (isset($present[$name])) {
                $result[] = ['slug' => $slug, 'name' => $name, 'icon' => CatalogBrowseTree::icon($name)];
            }
        }

        return $result;
    }

    /**
     * Název oddělení podle části adresy; neznámá null.
     */
    public function nameFor(string $slug): ?string
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * ID kategorií oddělení (i jeho podkategorií) — akce produktů v nich do oddělení patří.
     *
     * @return list<int>
     */
    public function categoryIds(string $name): array
    {
        return $this->paths->idsInDepartment($name);
    }

    /**
     * Část adresy oddělení („Mléčné, vejce a margaríny“ → „mlecne-vejce-a-margariny“).
     */
    public static function slug(string $name): string
    {
        return Str::slug($name);
    }
}
