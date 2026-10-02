<?php

/**
 * Popisky kategorií jako cesta stromem („Mléčné, vejce a margaríny › Mléko… › Trvanlivé mléko
 * › Polotučné mléko“). Regál a police se stejným názvem („Máslo › Máslo“) se zobrazí jednou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\Category;

final class CategoryPaths
{
    /** Oddělovač úrovní v popisku. */
    public const SEPARATOR = ' › ';

    /** @var array<int, string>|null Popisky podle ID kategorie, načtené při prvním použití */
    private ?array $labels = null;

    /**
     * Popisky všech kategorií v pořadí stromu (rodič před potomky, sourozenci podle Tesca).
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        return $this->labels ??= $this->build();
    }

    /**
     * Popisek jedné kategorie; null pro produkt bez kategorie.
     */
    public function label(?int $categoryId): ?string
    {
        return $categoryId === null ? null : ($this->all()[$categoryId] ?? null);
    }

    /**
     * Oddělení (první úroveň cesty) kategorie; null pro produkt bez kategorie.
     */
    public function department(?int $categoryId): ?string
    {
        $label = $this->label($categoryId);

        return $label === null ? null : explode(self::SEPARATOR, $label)[0];
    }

    /**
     * Poslední úroveň cesty kategorie („Kolové nápoje bez cukru“); null bez kategorie.
     */
    public function name(?int $categoryId): ?string
    {
        $label = $this->label($categoryId);
        $parts = $label === null ? [] : explode(self::SEPARATOR, $label);

        return $parts === [] ? null : $parts[count($parts) - 1];
    }

    /**
     * Sestaví popisky průchodem stromu do hloubky.
     *
     * @return array<int, string>
     */
    private function build(): array
    {
        $children = [];
        foreach (Category::query()->orderBy('position')->get(['id', 'parent_id', 'name']) as $category) {
            $children[$category->parent_id ?? 0][] = $category;
        }

        $labels = [];
        $walk = function (int $parentId, array $path) use (&$walk, &$labels, $children): void {
            foreach ($children[$parentId] ?? [] as $category) {
                $categoryPath = end($path) === $category->name ? $path : [...$path, $category->name];
                $labels[$category->id] = implode(self::SEPARATOR, $categoryPath);
                $walk($category->id, $categoryPath);
            }
        };
        $walk(0, []);

        return $labels;
    }
}
