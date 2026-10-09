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
use App\Domain\Catalog\Actions\DeleteProduct;
use App\Domain\Catalog\CatalogTable;
use App\Domain\Catalog\CategoryPaths;
use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
use App\Enums\MatchStatus;
use App\Http\Requests\CatalogIndexRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductExclusion;
use App\Models\Product;
use App\Support\Pagination\PaginationLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /** Parametr hledání nabídek k ručnímu přiřazení. */
    private const SEARCH_PARAMETER = 'hledat';

    /** Kódy stavu pro toast po uložení (R47, lang: ui.toast.messages). */
    public const STATUS_PRODUCT_SAVED = 'product-saved';

    public const STATUS_PRODUCT_DELETED = 'product-deleted';

    public const STATUS_ASSIGNMENT_CHANGED = 'assignment-changed';

    public function __construct(
        private readonly CategoryPaths $categories,
        private readonly AssignProducts $assign,
        private readonly CatalogTable $table,
    ) {}

    /**
     * Tabulka produktů (oddělení, kategorie, slova, počet přiřazených neskončených nabídek
     * a hlídajících uživatelů) s hledáním, oddělením, řazením a stránkováním na serveru (R43)
     * a formulář nového produktu.
     */
    public function index(CatalogIndexRequest $request, TextNormalizer $normalizer): Response
    {
        $query = $this->table->query($request->searchWords(), $request->department());
        $total = $query->count();
        $window = $request->pageWindow(config()->integer('letaky.catalog.per_page'));
        $window = $window->within($window->lastPage($total));
        $products = $this->table->page($query, $request->sort(), $request->descending(), $window);

        return Inertia::render('Catalog/Index', [
            'urls' => [
                'store' => route('catalog.store', absolute: false),
                'index' => route('catalog.index', absolute: false),
            ],
            'categories' => $this->categoryOptions(),
            'hasProducts' => Product::query()->exists(),
            'departments' => $this->categories->departmentsOf(
                Product::query()->distinct()->pluck('category_id')->map(fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null)->values()->all(),
                $normalizer,
            ),
            'filters' => [
                'q' => implode(' ', $request->searchWords()),
                'department' => $request->department() ?? '',
                'sort' => $request->sort(),
                'descending' => $request->descending(),
            ],
            'total' => $total,
            'products' => $products->map(fn (Product $product): array => [
                ...$this->productData($product),
                'matchCount' => (int) $product->getAttribute('match_count'),
                'maybeCount' => (int) $product->getAttribute('maybe_count'),
                'watchersCount' => (int) $product->getAttribute('watchers_count'),
                'showUrl' => route('catalog.show', $product, absolute: false),
            ]),
            'pagination' => PaginationLinks::for($window, $total, fn (int $page, ?int $from): string => route('catalog.index', [
                ...$request->listParameters(),
                ...PaginationLinks::parameters($page, $from, CatalogIndexRequest::PAGE, CatalogIndexRequest::FROM_PAGE),
            ], absolute: false)),
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

        return to_route('catalog.show', $product)->with('status', self::STATUS_PRODUCT_SAVED);
    }

    /**
     * Detail produktu: pravidla, přiřazené a vyřazené neskončené nabídky a hledání nabídek
     * k ručnímu přiřazení.
     */
    public function show(Request $request, Product $product, OfferPresenter $presenter, OfferSearch $search): Response
    {
        $assignments = $this->table->withCurrentOffer($product->assignments()->getQuery())
            ->with(['offer' => fn ($query) => $query->withoutRaw()])
            ->get()
            ->sortBy(fn (OfferProduct $assignment): array => [
                $assignment->status === MatchStatus::Maybe,
                $assignment->offer->chain->value,
                $assignment->offer->name,
            ]);

        $exclusions = $this->table->withCurrentOffer($product->exclusions()->getQuery())
            ->with(['offer' => fn ($query) => $query->withoutRaw()])
            ->get();

        $query = trim($request->string(self::SEARCH_PARAMETER)->toString());
        $assignedIds = $assignments->pluck('offer_id')->all();
        $results = $query === '' ? [] : $search->search($query, new OfferFilters, config()->integer('letaky.catalog.search_results'))
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

        return to_route('catalog.show', $product)->with('status', self::STATUS_PRODUCT_SAVED);
    }

    /**
     * Smaže produkt; hlídané položky dostanou jeho pravidla jako vlastní slova (R31).
     */
    public function destroy(Product $product, DeleteProduct $delete): RedirectResponse
    {
        $delete->handle($product);

        return to_route('catalog.index')->with('status', self::STATUS_PRODUCT_DELETED);
    }

    /**
     * Ručně přiřadí nabídku k produktu („sem patří“).
     */
    public function includeOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->include($product, $offer);

        return back()->with('status', self::STATUS_ASSIGNMENT_CHANGED);
    }

    /**
     * Vyřadí nabídku z produktu („sem nepatří“).
     */
    public function excludeOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->exclude($product, $offer);

        return back()->with('status', self::STATUS_ASSIGNMENT_CHANGED);
    }

    /**
     * Zruší vyřazení nabídky — o přiřazení zase rozhodnou pravidla.
     */
    public function restoreOffer(Product $product, Offer $offer, CorrectAssignment $correct): RedirectResponse
    {
        $correct->restore($product, $offer);

        return back()->with('status', self::STATUS_ASSIGNMENT_CHANGED);
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
}
