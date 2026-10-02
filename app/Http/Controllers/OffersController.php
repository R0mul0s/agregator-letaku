<?php

/**
 * Přehled všech aktuálních akcí s hledáním — etapa 2, před zavedením hlídání.
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
use Inertia\Inertia;
use Inertia\Response;

class OffersController extends Controller
{
    /**
     * Zobrazí neskončené akce odpovídající hledání.
     */
    public function __invoke(OffersRequest $request, OfferSearch $search, OfferPresenter $presenter): Response
    {
        $offers = $search
            ->search($request->searchText(), $request->chain(), config()->integer('letaky.offers.per_page'))
            ->withQueryString()
            ->through(fn (Offer $offer): array => $presenter->toPage($offer));

        return Inertia::render('Offers', [
            'searchUrl' => route('offers', absolute: false),
            'suggestUrl' => route('offers.suggestions', absolute: false),
            'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
            'offers' => $offers,
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
