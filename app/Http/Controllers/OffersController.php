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
use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use App\Models\Offer;
use App\Support\Pagination\PaginationLinks;
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
        $window = $request->pageWindow(config()->integer('letaky.offers.per_page'));
        $window = $window->within($window->lastPage($total));
        // Hledání, které odkazy stránkování zachovají
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
            'pagination' => PaginationLinks::for($window, $total, fn (int $page, ?int $from): string => route('offers', [
                ...$filters,
                ...PaginationLinks::parameters($page, $from, OffersRequest::PAGE, OffersRequest::FROM_PAGE),
            ], absolute: false)),
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
}
