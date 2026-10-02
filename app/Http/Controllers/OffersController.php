<?php

/**
 * Přehled všech aktuálních akcí s hledáním. Stránkování čísly i „Načíst další“ (R43):
 * načtený rozsah stránek je v adrese (?od=1&strana=3), takže obnovení i návrat zpět
 * ukážou totéž.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
use App\Domain\Offers\PageWindow;
use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use App\Models\Offer;
use Inertia\Inertia;
use Inertia\Response;

class OffersController extends Controller
{
    /**
     * Zobrazí neskončené akce odpovídající hledání, v načteném rozsahu stránek.
     */
    public function __invoke(OffersRequest $request, OfferSearch $search, OfferPresenter $presenter): Response
    {
        $query = $search->query($request->searchText(), $request->chain());
        $total = $query->count();
        $window = PageWindow::of(
            $request->fromPage(),
            $request->page(),
            config()->integer('letaky.offers.per_page'),
            config()->integer('letaky.offers.max_loaded_pages'),
        );
        $lastPage = $window->lastPage($total);
        $window = $window->within($lastPage);
        $filters = array_filter([
            'q' => $request->searchText(),
            'chain' => $request->chain()?->value,
        ]);

        return Inertia::render('Offers', [
            'searchUrl' => route('offers', absolute: false),
            'suggestUrl' => route('offers.suggestions', absolute: false),
            'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
            'offers' => [
                'data' => $query->offset($window->offset())->limit($window->limit())->get()
                    ->map(fn (Offer $offer): array => $presenter->toPage($offer))
                    ->all(),
                'total' => $total,
            ],
            'pagination' => $this->pagination($window, $lastPage, $total, $filters),
            'filters' => [
                'q' => $request->searchText() ?? '',
                'chain' => $request->chain()->value ?? '',
            ],
            'chains' => array_map(fn (Chain $chain): array => [
                'value' => $chain->value,
                'name' => $chain->label(),
            ], Chain::cases()),
        ]);
    }

    /**
     * Odkazy stránkování: čísla stránek (první, poslední a okolí načteného rozsahu, mezery
     * jako „…“), předchozí a další stránka a „Načíst další“ (rozsah prodloužený o stránku).
     *
     * @param  array<string, string>  $filters  Hledání, které odkazy zachovají
     * @return array<string, mixed>
     */
    private function pagination(PageWindow $window, int $lastPage, int $total, array $filters): array
    {
        $neighbours = config()->integer('letaky.offers.page_link_neighbours');
        $numbers = array_unique([
            1,
            ...range(max(1, $window->from - $neighbours), min($lastPage, $window->to + $neighbours)),
            $lastPage,
        ]);
        sort($numbers);

        $pages = [];
        $previous = null;
        foreach ($numbers as $number) {
            if ($previous !== null && $number > $previous + 1) {
                $pages[] = ['gap' => true];
            }
            $pages[] = ['number' => $number, 'url' => $this->pageUrl($filters, $number), 'current' => $window->contains($number)];
            $previous = $number;
        }

        $hasNext = $window->to < $lastPage;

        return [
            'from' => $window->from,
            'to' => $window->to,
            'lastPage' => $lastPage,
            'pages' => $pages,
            'previousUrl' => $window->from > 1 ? $this->pageUrl($filters, $window->from - 1) : null,
            'nextUrl' => $hasNext ? $this->pageUrl($filters, $window->to + 1) : null,
            'loadMoreUrl' => $hasNext ? $this->pageUrl($filters, $window->to + 1, $window->from) : null,
            'loadMoreCount' => $hasNext ? min($window->perPage, $total - $window->to * $window->perPage) : 0,
            // „Zobrazeno 1–100 z 6 245“
            'shownFrom' => $total === 0 ? 0 : $window->offset() + 1,
            'shownTo' => min($total, $window->to * $window->perPage),
        ];
    }

    /**
     * Adresa stránky (s rozsahem od stránky `from`) se zachovaným hledáním. První stránka
     * a rozsah jedné stránky parametry nemají.
     *
     * @param  array<string, string>  $filters
     */
    private function pageUrl(array $filters, int $page, ?int $from = null): string
    {
        return route('offers', array_filter([
            ...$filters,
            OffersRequest::FROM_PAGE => $from !== null && $from < $page ? $from : null,
            OffersRequest::PAGE => $page > 1 ? $page : null,
        ]), absolute: false);
    }
}
