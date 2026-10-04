<?php

/**
 * Katalog k procházení v Hlídám (R47): oddělení → pododdělení → produkty, jako kategorie
 * e-shopu. Oddělení a pododdělení jsou první dvě úrovně stromu Tesca v jeho pořadí; produkt
 * přímo v oddělení je v pododdělení se stejným názvem, produkt bez kategorie v „Ostatní“ na konci.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\Product;

final class CatalogBrowseTree
{
    /** Ikona oddělení, které nemá vlastní (config letaky.catalog.department_icons). */
    public const FALLBACK_ICON = 'other';

    public function __construct(private readonly CategoryPaths $paths) {}

    /**
     * Oddělení s pododděleními a ID produktů; produkty v pořadí, v jakém přišly (podle názvu).
     *
     * @param  iterable<Product>  $products
     * @return list<array{name: string, icon: string, aisles: list<array{name: string, productIds: list<int>}>}>
     */
    public function build(iterable $products): array
    {
        // Pořadí kategorií ve stromu (rodič před potomky) — oddělení i pododdělení podle Tesca
        $order = array_flip(array_keys($this->paths->all()));
        $other = __('app.ui.watch.other_department');

        /** @var array<string, array{rank: int, aisles: array<string, array{rank: int, productIds: list<int>}>}> $departments */
        $departments = [];
        foreach ($products as $product) {
            $label = $this->paths->label($product->category_id);
            $parts = $label === null ? [$other] : explode(CategoryPaths::SEPARATOR, $label);
            $department = $parts[0];
            $aisle = $parts[1] ?? $parts[0];
            $rank = $product->category_id === null ? PHP_INT_MAX : ($order[$product->category_id] ?? PHP_INT_MAX);

            $departments[$department] ??= ['rank' => $rank, 'aisles' => []];
            $departments[$department]['rank'] = min($departments[$department]['rank'], $rank);
            $departments[$department]['aisles'][$aisle] ??= ['rank' => $rank, 'productIds' => []];
            $departments[$department]['aisles'][$aisle]['rank'] = min($departments[$department]['aisles'][$aisle]['rank'], $rank);
            $departments[$department]['aisles'][$aisle]['productIds'][] = $product->id;
        }

        uasort($departments, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        $tree = [];
        foreach ($departments as $name => $department) {
            uasort($department['aisles'], fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
            $tree[] = [
                'name' => (string) $name,
                'icon' => self::icon((string) $name),
                'aisles' => array_map(
                    fn (int|string $aisle, array $data): array => ['name' => (string) $aisle, 'productIds' => $data['productIds']],
                    array_keys($department['aisles']),
                    array_values($department['aisles']),
                ),
            ];
        }

        return $tree;
    }

    /**
     * Klíč ikony oddělení (DepartmentIcon.vue) — i pro našeptávač v Hlídám (R71).
     */
    public static function icon(?string $department): string
    {
        $icon = $department === null ? null : (config()->array('letaky.catalog.department_icons')[$department] ?? null);

        return is_string($icon) ? $icon : self::FALLBACK_ICON;
    }
}
