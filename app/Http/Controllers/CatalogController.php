<?php

/**
 * Katalog produktů pro admina (R29, R30) — produkty, jejich pravidla a kategorie,
 * přiřazené nabídky a ruční opravy přiřazení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Catalog\Actions\CorrectAssignment;
use App\Domain\Catalog\CategoryPaths;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
use App\Enums\MatchStatus;
use App\Http\Requests\ProductRequest;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductExclusion;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /** Parametr hledání nabídek k ručnímu přiřazení. */
    private const SEARCH_PARAMETER = 'hledat';

    public function __construct(
        private readonly CategoryPaths $categories,
        private readonly AssignProducts $assign,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Tabulka produktů (oddělení, kategorie, slova, počet přiřazených neskončených nabídek
     * a hlídajících uživatelů) a formulář nového produktu.
     */
    public function index(): Response
    {
        $products = Product::query()
            ->withCount([
                'assignments as match_count' => fn (Builder $query) => $this->currentAssignments($query, MatchStatus::Match),
                'assignments as maybe_count' => fn (Builder $query) => $this->currentAssignments($query, MatchStatus::Maybe),
                'watchItems as watchers_count',
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('Catalog/Index', [
            'urls' => ['store' => route('catalog.store', absolute: false)],
            'categories' => $this->categoryOptions(),
            'products' => $products->map(fn (Product $product): array => [
                ...$this->productData($product),
                'matchCount' => (int) $product->getAttribute('match_count'),
                'maybeCount' => (int) $product->getAttribute('maybe_count'),
                'watchersCount' => (int) $product->getAttribute('watchers_count'),
                'showUrl' => route('catalog.show', $product, absolute: false),
            ]),
        ]);
    }

    /**
     * Založí produkt a přiřadí k němu nabídky.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::query()->create($request->validated());
            $this->assign->forProduct($product);

            return $product;
        });

        return to_route('catalog.show', $product);
    }

    /**
     * Detail produktu: pravidla, přiřazené a vyřazené neskončené nabídky a hledání nabídek
     * k ručnímu přiřazení.
     */
    public function show(Request $request, Product $product, OfferPresenter $presenter, OfferSearch $search): Response
    {
        $assignments = $product->assignments()
            ->whereHas('offer', fn (Builder $query) => $this->current($query))
            ->with('offer')
            ->get()
            ->sortBy(fn (OfferProduct $assignment): array => [
                $assignment->status === MatchStatus::Maybe,
                $assignment->offer->chain->value,
                $assignment->offer->name,
            ]);

        $exclusions = $product->exclusions()
            ->whereHas('offer', fn (Builder $query) => $this->current($query))
            ->with('offer')
            ->get();

        $query = trim($request->string(self::SEARCH_PARAMETER)->toString());
        $assignedIds = $assignments->pluck('offer_id')->all();
        $results = $query === '' ? [] : $search->search($query, null, config()->integer('letaky.catalog.search_results'))
            ->getCollection()
            ->reject(fn (Offer $offer): bool => in_array($offer->id, $assignedIds, true))
            ->map(fn (Offer $offer): array => [
                ...$presenter->toPage($offer),
                'includeUrl' => route('catalog.offers.include', [$product, $offer], absolute: false),
            ])
            ->values()
            ->all();

        return Inertia::render('Catalog/Show', [
            'product' => [
                ...$this->productData($product),
                'updateUrl' => route('catalog.update', $product, absolute: false),
                'deleteUrl' => route('catalog.destroy', $product, absolute: false),
            ],
            'categories' => $this->categoryOptions($product->category_id),
            'assigned' => $assignments->map(fn (OfferProduct $assignment): array => [
                ...$presenter->toPage($assignment->offer),
                'matchStatus' => $assignment->status->value,
                'isManual' => $assignment->is_manual,
                'excludeUrl' => route('catalog.offers.exclude', [$product, $assignment->offer_id], absolute: false),
            ])->values(),
            'excluded' => $exclusions->map(fn (OfferProductExclusion $exclusion): array => [
                ...$presenter->toPage($exclusion->offer),
                'restoreUrl' => route('catalog.offers.restore', [$product, $exclusion->offer_id], absolute: false),
            ])->values(),
            'search' => ['parameter' => self::SEARCH_PARAMETER, 'query' => $query, 'results' => $results],
            'urls' => [
                'index' => route('catalog.index', absolute: false),
                'show' => route('catalog.show', $product, absolute: false),
            ],
        ]);
    }

    /**
     * Uloží změny produktu a přepočítá přiřazení jeho nabídek.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($request->validated());
            $this->assign->forProduct($product);
        });

        return to_route('catalog.show', $product);
    }

    /**
     * Smaže produkt i s přiřazeními a vyřazeními. Hlídané položky, které ho hlídají,
     * dostanou jeho pravidla jako vlastní slova — uživatelům nic nezmizí (R31).
     */
    public function destroy(Product $product): RedirectResponse
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

        return to_route('catalog.index');
    }

    /**
     * Ručně přiřadí nabídku k produktu („sem patří“).
     */
    public function includeOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->include($product, $offer);

        return back();
    }

    /**
     * Vyřadí nabídku z produktu („sem nepatří“).
     */
    public function excludeOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->exclude($product, $offer);

        return back();
    }

    /**
     * Zruší vyřazení nabídky — o přiřazení zase rozhodnou pravidla.
     */
    public function restoreOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->restore($product, $offer);

        return back();
    }

    /**
     * Data produktu společná pro seznam i detail.
     *
     * @return array<string, mixed>
     */
    private function productData(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'categoryId' => $product->category_id,
            'categoryLabel' => $this->categories->label($product->category_id),
            // Do tabulky katalogu: oddělení (první úroveň) a kategorie (poslední úroveň)
            'department' => $this->categories->department($product->category_id),
            'categoryName' => $this->categories->name($product->category_id),
            'keywords' => $product->keywords,
            'variantKeywords' => $product->variant_keywords,
            'excludeKeywords' => $product->exclude_keywords,
        ];
    }

    /**
     * Kategorie pro výběr ve formuláři, v pořadí stromu. Regál a police se stejným názvem
     * („Balené chleby › Balené chleby“) mají stejný popisek — ve výběru zůstane jen první
     * (regál); kategorie, kterou už produkt má, se ukáže vždy.
     *
     * @return list<array{id: int, label: string}>
     */
    private function categoryOptions(?int $selectedId = null): array
    {
        $options = [];
        $labels = [];
        foreach ($this->categories->all() as $id => $label) {
            if (! isset($labels[$label]) || $id === $selectedId) {
                $labels[$label] = true;
                $options[] = ['id' => $id, 'label' => $label];
            }
        }

        return $options;
    }

    /**
     * Přiřazení se stavem k neskončeným nabídkám — pro počty v seznamu produktů.
     *
     * @param  Builder<OfferProduct>  $query
     * @return Builder<OfferProduct>
     */
    private function currentAssignments(Builder $query, MatchStatus $status): Builder
    {
        return $query->where('status', $status)->whereHas('offer', fn (Builder $offer) => $this->current($offer));
    }

    /**
     * Neskončené nabídky, které obchod nestáhl.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function current(Builder $query): Builder
    {
        return $query->active()->notExpired($this->calendar->today());
    }
}
