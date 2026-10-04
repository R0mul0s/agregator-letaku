<?php

/**
 * Přehled všech aktuálních akcí s hledáním. Stránkování čísly i „Načíst další“ (R43):
 * načtený rozsah stránek je v adrese (?od=1&strana=3), takže obnovení i návrat zpět
 * ukážou totéž. Hledání podle relevance, filtr produktu z našeptávače, jen slevy a oprava
 * překlepu, když text nic nenajde (R71).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\WatchTargets;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
use App\Domain\Offers\PriceHistory;
use App\Domain\Offers\SearchVocabulary;
use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use App\Http\Responses\RegisterResponse;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Support\Pagination\PaginationLinks;
use Inertia\Inertia;
use Inertia\Response;

class OffersController extends Controller
{
    /**
     * Zobrazí neskončené akce odpovídající hledání, v načteném rozsahu stránek.
     */
    public function __invoke(OffersRequest $request, OfferSearch $search, OfferPresenter $presenter, PriceHistory $priceHistory, WatchTargets $watchTargets, SearchVocabulary $vocabulary): Response
    {
        $text = $request->searchText();
        $productId = $request->productId();
        $query = $search->query($text, $request->chain(), $productId, $request->discountsOnly());
        $total = $query->count();
        // Překlep (R71): hledání nic nenašlo, ale opravený text ano — ukáže se rovnou výsledek
        // opraveného s upozorněním
        $correction = null;
        if ($total === 0 && $text !== null) {
            $corrected = $vocabulary->correct($text);
            $correctedQuery = $corrected === null ? null : $search->query($corrected, $request->chain(), $productId, $request->discountsOnly());
            $correctedTotal = $correctedQuery?->count() ?? 0;
            if ($correctedQuery !== null && $correctedTotal > 0) {
                $correction = ['original' => $text, 'corrected' => $corrected];
                $query = $correctedQuery;
                $total = $correctedTotal;
            }
        }
        // Přihlášený uvidí u akce, která neplatí všude, ve kterých jeho prodejnách platí (R49)
        $storeCodes = $request->user()?->selectedStoreCodes() ?? [];
        $window = $request->pageWindow(config()->integer('letaky.offers.per_page'));
        $window = $window->within($window->lastPage($total));
        // Hledání, které odkazy stránkování zachovají
        $filters = array_filter([
            'q' => $correction['corrected'] ?? $text,
            'chain' => $request->chain()?->value,
            OffersRequest::PRODUCT => $productId,
            OffersRequest::DISCOUNTS => $request->discountsOnly() ? 1 : null,
        ]);
        $offers = $query->offset($window->offset())->limit($window->limit())->get();
        // „Je to opravdu sleva?“ (R59) — jedním dotazem pro celou stránku
        $history = $priceHistory->forOffers($offers);
        // „Hlídat“ z karty (R60): produkt katalogu akce, nebo její název jako vlastní slova
        $user = $request->user();
        $targets = $watchTargets->forOffers($offers, $user instanceof User ? $user : null);

        return Inertia::render('Offers', [
            'searchUrl' => route('offers', absolute: false),
            'suggestUrl' => route('offers.suggestions', absolute: false),
            'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
            'watchUrls' => [
                'store' => route('watch-items.store', absolute: false),
                'index' => route('watch-items.index', absolute: false),
                'register' => route('register', absolute: false),
                'prefillParameter' => WatchItemController::PREFILL_PARAMETER,
                'watchParameter' => RegisterResponse::WATCH_PARAMETER,
                'stayField' => WatchItemController::STAY_FIELD,
            ],
            'offers' => [
                'data' => $offers
                    ->map(fn (Offer $offer): array => [
                        ...$presenter->toPage($offer, $storeCodes, $history[$offer->id] ?? null),
                        'watchTarget' => $targets[$offer->id] ?? null,
                    ])
                    ->all(),
                'total' => $total,
            ],
            'pagination' => PaginationLinks::for($window, $total, fn (int $page, ?int $from): string => route('offers', [
                ...$filters,
                ...PaginationLinks::parameters($page, $from, OffersRequest::PAGE, OffersRequest::FROM_PAGE),
            ], absolute: false)),
            'filters' => [
                'q' => $text ?? '',
                'chain' => $request->chain()->value ?? '',
                OffersRequest::PRODUCT => $productId ?? '',
                OffersRequest::DISCOUNTS => $request->discountsOnly(),
            ],
            // Filtr produktu z našeptávače jako štítek nad výsledky (R71)
            'product' => $productId === null ? null : Product::query()->whereKey($productId)->value('name'),
            'correction' => $correction,
            'chains' => array_map(fn (Chain $chain): array => [
                'value' => $chain->value,
                'name' => $chain->label(),
            ], Chain::cases()),
        ]);
    }
}
