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
use App\Enums\Chain;
use App\Models\OfferProduct;
use Illuminate\Database\Eloquent\Builder;
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
     * Oddělení, ve kterých jsou teď nějaké akce, v pořadí stromu — do výběru (prázdné se nenabízí)
     * s počtem akcí (dlaždice ve Všech akcích, R141). Počet je ve vybraných obchodech, jiné
     * filtry nebere; akce přiřazená ke dvěma produktům v různých kategoriích oddělení se počítá
     * dvakrát (výjimečné, jen orientační číslo).
     *
     * @param  list<Chain>  $chains  Prázdné = všechny obchody
     * @return list<array{slug: string, name: string, icon: string, count: int}>
     */
    public function withOffers(array $chains = []): array
    {
        $rows = OfferProduct::query()
            ->joinCurrentOffers($this->calendar->today())
            ->join('products', 'products.id', '=', 'offer_product.product_id')
            ->whereNotNull('products.category_id')
            ->when($chains !== [], fn (Builder $query) => $query->whereIn('offers.chain', $chains))
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, COUNT(DISTINCT offer_product.offer_id) AS offers_count')
            ->toBase()
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $department = $this->paths->department(is_numeric($row->category_id) ? (int) $row->category_id : null);
            if ($department !== null) {
                $counts[$department] = ($counts[$department] ?? 0) + (int) $row->offers_count;
            }
        }

        $result = [];
        foreach ($this->all() as $slug => $name) {
            if (isset($counts[$name])) {
                $result[] = ['slug' => $slug, 'name' => $name, 'icon' => CatalogBrowseTree::icon($name), 'count' => $counts[$name]];
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
