<?php

/**
 * Přehled všech aktuálních akcí s hledáním. Stránkování čísly i „Načíst další“ (R43):
 * načtený rozsah stránek je v adrese (?od=1&strana=3), takže obnovení i návrat zpět
 * ukážou totéž. Hledání podle relevance, filtr produktu z našeptávače a oprava
 * překlepu, když text nic nenajde (R71); jen akce, které ještě nezačaly (R76); víc obchodů
 * najednou (přihlášený má předvybrané sledované) a bez akcí jen z e-shopu (R82). Jeden
 * obchod a produkt katalogu mají čistou adresu `/akce/lidl`, `/akce/pivo` (R94). Řazení na výběr
 * a přihlášenému nastavení Mých obchodů — prodejny, karty, e-shop — s počtem skrytých akcí (R100).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\WatchTargets;
use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPages;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
use App\Domain\Offers\PriceHistory;
use App\Domain\Offers\SearchVocabulary;
use App\Enums\Chain;
use App\Enums\OfferListSort;
use App\Http\Requests\OffersRequest;
use App\Http\Responses\RegisterResponse;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Support\Pagination\PaginationLinks;
use App\Support\Seo\SeoMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OffersController extends Controller
{
    /**
     * Zobrazí neskončené akce odpovídající hledání, v načteném rozsahu stránek. Stará adresa
     * jednoho obchodu nebo produktu (`?chain=lidl`, `?produkt=12`) přesměruje na čistou (R94).
     */
    public function __invoke(OffersRequest $request, OfferSearch $search, OfferPresenter $presenter, PriceHistory $priceHistory, WatchTargets $watchTargets, SearchVocabulary $vocabulary, OfferPages $pages): Response|RedirectResponse
    {
        abort_if($request->pathTarget() === null, HttpResponse::HTTP_NOT_FOUND);
        $cleanUrl = $this->cleanUrl($request, $pages);
        if ($cleanUrl !== null) {
            return redirect($cleanUrl, HttpResponse::HTTP_MOVED_PERMANENTLY);
        }

        $text = $request->searchText();
        $productId = $request->productId();
        $offerFilters = $request->filters();
        $query = $search->query($text, $offerFilters);
        $total = $query->count();
        // Překlep (R71): hledání nic nenašlo, ale opravený text ano — ukáže se rovnou výsledek
        // opraveného s upozorněním
        $correction = null;
        if ($total === 0 && $text !== null) {
            $corrected = $vocabulary->correct($text);
            $correctedQuery = $corrected === null ? null : $search->query($corrected, $offerFilters);
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
        // Hledání a zvolené filtry, které odkazy stránkování zachovají (obchod a produkt v čisté adrese)
        $chosenFilters = $request->chosenFilters();
        $searchText = $correction['corrected'] ?? $text;
        // Kolik akcí skrylo nastavení Mých obchodů (R100) — výpis to řekne s nabídkou ukázat všechny
        $preferencesHidden = $offerFilters->preferencesOf === null ? 0
            : $search->query($searchText, $offerFilters->withPreferencesOf(null))->count() - $total;
        $sort = OfferSearch::sortFor($searchText, $offerFilters);
        $offers = $query->offset($window->offset())->limit($window->limit())->get();
        // „Je to opravdu sleva?“ (R59) — jedním dotazem pro celou stránku
        $history = $priceHistory->forOffers($offers);
        // „Hlídat“ z karty (R60): produkt katalogu akce, nebo její název jako vlastní slova
        $user = $request->user();
        $targets = $watchTargets->forOffers($offers, $user instanceof User ? $user : null);

        return Inertia::render('Offers', [
            // Nadpis podle obchodu nebo produktu, stejný jako pro vyhledávače (R94)
            'heading' => app(SeoMeta::class)->heading($request),
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
            'pagination' => PaginationLinks::for($window, $total, fn (int $page, ?int $from): string => $pages->url($chosenFilters, [
                // Zvolené „všechny obchody“ — bez parametru by přihlášený na další stránce dostal sledované
                OffersRequest::CHAIN => $request->allChainsChosen() ? OfferFilters::ALL_CHAINS : null,
                'q' => $searchText,
                ...PaginationLinks::parameters($page, $from, OffersRequest::PAGE, OffersRequest::FROM_PAGE),
            ])),
            'filters' => [
                'q' => $text ?? '',
                // Vybrané obchody (prázdné = všechny) — přihlášený bez volby vidí své sledované
                OffersRequest::CHAIN => array_map(fn (Chain $chain): string => $chain->value, $offerFilters->chains),
                OffersRequest::PRODUCT => $productId ?? '',
                OffersRequest::UPCOMING => $request->upcomingOnly(),
                OffersRequest::WITHOUT_ESHOP => $request->withoutEshop(),
                // Zvolené řazení ('' = podle situace) a zapnuté nastavení Mých obchodů (R100)
                OffersRequest::SORT => $request->sort()->value ?? '',
                OffersRequest::SHOPPING_PREFERENCES => $offerFilters->preferencesOf !== null,
            ],
            // Řazení, podle kterého výpis opravdu řadí, a na výběr (relevance jen s textem, R100)
            'sort' => $sort->value,
            'sortOptions' => array_map(
                fn (OfferListSort $option): array => ['value' => $option->value, 'label' => $option->label()],
                OfferListSort::available($searchText !== null),
            ),
            // Nastavení Mých obchodů (R100) — jen přihlášený; kolik akcí skrylo a kde se mění
            'shoppingPreferences' => $user instanceof User ? [
                'hidden' => $preferencesHidden,
                'url' => route('preferences', absolute: false),
            ] : null,
            // Filtr produktu z našeptávače jako štítek nad výsledky (R71)
            'product' => $productId === null ? null : Product::query()->whereKey($productId)->value('name'),
            'correction' => $correction,
            'chains' => array_map(fn (Chain $chain): array => [
                'value' => $chain->value,
                'name' => $chain->label(),
            ], Chain::cases()),
        ]);
    }

    /**
     * Čistá adresa pro starou adresu jednoho obchodu nebo produktu (`/akce?chain=lidl` →
     * `/akce/lidl`, `/akce?produkt=12&q=x` → `/akce/pivo?q=x`); null = adresa už je čistá.
     * Jen pro celé načtení stránky (odkaz, vyhledávač) — filtry ve Vue posílají parametry
     * na `/akce` a přesměrování by každou změnu zdvojilo.
     */
    private function cleanUrl(OffersRequest $request, OfferPages $pages): ?string
    {
        if ($request->route()?->getName() !== 'offers' || $request->hasHeader(Header::INERTIA)) {
            return null;
        }

        $filters = $request->chosenFilters();
        if ($filters->productId === null && count($filters->chains) !== 1) {
            return null;
        }

        // Hledání a stránky zůstanou jako parametry, filtry složí OfferPages
        $extra = array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $request->only(['q', OffersRequest::PAGE, OffersRequest::FROM_PAGE]));

        return $pages->url($filters, $extra);
    }
}
