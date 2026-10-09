<?php

/**
 * Tabulka produktů katalogu pro admina (R29, R43): hledání, oddělení, řazení a počty
 * přiřazených neskončených akcí a hlídajících. Dřív v CatalogController (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\LocalCalendar;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\Product;
use App\Support\Like;
use App\Support\Pagination\PageWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class CatalogTable
{
    /** Řazení podle cesty kategorie — řadí PHP, cesta v databázi není. */
    public const SORT_CATEGORY = 'category';

    private const SORT_OFFERS = 'offers';

    private const SORT_WATCHERS = 'watchers';

    public function __construct(
        private CategoryPaths $categories,
        private LocalCalendar $calendar,
        private TextNormalizer $normalizer,
    ) {}

    /**
     * Vyfiltrované produkty: každé slovo musí být v názvu, hledaných slovech nebo v cestě
     * kategorie (bez ohledu na diakritiku — collation tabulky, cesty v PHP); volitelně oddělení.
     *
     * @param  list<string>  $words
     * @return Builder<Product>
     */
    public function query(array $words, ?string $department): Builder
    {
        $query = Product::query();
        foreach ($words as $word) {
            $pattern = Like::contains($word);
            $categoryIds = $this->categories->idsContaining($word, $this->normalizer);
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('keywords', 'like', $pattern)
                ->orWhereIn('category_id', $categoryIds));
        }

        if ($department !== null) {
            $query->whereIn('category_id', $this->categories->idsInDepartment($department));
        }

        return $query;
    }

    /**
     * Produkty rozsahu stránek s počty, seřazené podle volby (název, akce, hlídající,
     * kategorie); při shodě podle názvu.
     *
     * @param  Builder<Product>  $query  Vyfiltrovaný dotaz (query)
     * @return Collection<int, Product>
     */
    public function page(Builder $query, string $sort, bool $descending, PageWindow $window): Collection
    {
        if ($sort === self::SORT_CATEGORY) {
            $ids = $this->idsSortedByCategory($query->clone(), $window, $descending);
            $position = array_flip($ids);

            return $this->withCounts($query->whereIn('id', $ids))->get()
                ->sortBy(fn (Product $product): int => $position[$product->id])
                ->values();
        }

        $direction = $descending ? 'desc' : 'asc';
        $query = $this->withCounts($query);
        match ($sort) {
            self::SORT_OFFERS => $query->orderByRaw('match_count + maybe_count '.($descending ? 'desc' : 'asc')),
            self::SORT_WATCHERS => $query->orderBy('watchers_count', $direction),
            default => $query->orderBy('name', $direction),
        };

        return $query->orderBy('name')->orderBy('id')->offset($window->offset())->limit($window->limit())->get();
    }

    /**
     * Přiřazení produktu k akcím, které obchod nestáhl a neskončily (R16).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  Přiřazení nebo vyřazení s relací `offer`
     * @return Builder<TModel>
     */
    public function withCurrentOffer(Builder $query): Builder
    {
        return $query->whereHas('offer', function (Builder $offer): void {
            /** @var Builder<Offer> $offer */
            $offer->active()->notExpired($this->calendar->today());
        });
    }

    /**
     * Počty do tabulky: přiřazené neskončené nabídky (shoda / možná) a hlídající uživatelé.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function withCounts(Builder $query): Builder
    {
        return $query->withCount([
            'assignments as match_count' => fn (Builder $query) => $this->withCurrentOffer($query->where('status', MatchStatus::Match)),
            'assignments as maybe_count' => fn (Builder $query) => $this->withCurrentOffer($query->where('status', MatchStatus::Maybe)),
            'watchItems as watchers_count',
        ]);
    }

    /**
     * ID produktů rozsahu stránek seřazená podle cesty kategorie (bez kategorie vždy na konci,
     * při shodě podle názvu). Řadí PHP nad všemi vyfiltrovanými produkty — cesta vzniká ze
     * stromu kategorií; i tisíce produktů jsou jen ID, název a kategorie.
     *
     * @param  Builder<Product>  $query
     * @return list<int>
     */
    private function idsSortedByCategory(Builder $query, PageWindow $window, bool $descending): array
    {
        $rank = array_flip($this->categories->idsByLabel($this->normalizer));
        $products = $query->get(['id', 'name', 'category_id'])->all();

        usort($products, function (Product $a, Product $b) use ($rank, $descending): int {
            $rankA = $a->category_id === null ? null : ($rank[$a->category_id] ?? null);
            $rankB = $b->category_id === null ? null : ($rank[$b->category_id] ?? null);
            if ($rankA === null || $rankB === null || $rankA === $rankB) {
                return [$rankA === null, $this->normalizer->normalize($a->name)] <=> [$rankB === null, $this->normalizer->normalize($b->name)];
            }

            return $descending ? $rankB <=> $rankA : $rankA <=> $rankB;
        });

        return array_map(fn (Product $product): int => $product->id, array_slice($products, $window->offset(), $window->limit()));
    }
}
