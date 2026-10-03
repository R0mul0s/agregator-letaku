<?php

/**
 * Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
 * a ukázka skutečných akcí s nejvyšší slevou. Přihlášený má na stejné adrese Moje slevy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly OfferPresenter $presenter,
        private readonly SourceRegistry $sources,
    ) {}

    /**
     * Zobrazí úvodní stránku s počty a ukázkou akcí.
     */
    public function show(): Response
    {
        return Inertia::render('Landing', [
            'urls' => [
                'register' => route('register', absolute: false),
                'login' => route('login', absolute: false),
                'offers' => route('offers', absolute: false),
            ],
            'stats' => [
                'offers' => $this->currentOffers()->count(),
                'chains' => count($this->sources->chainsWithOffers()),
                'products' => Product::query()->count(),
            ],
            'chains' => array_map(fn (Chain $chain): string => $chain->value, $this->sources->chainsWithOffers()),
            'topOffers' => array_map(fn (Offer $offer): array => $this->presenter->toPage($offer), $this->topOffers()),
        ]);
    }

    /**
     * Ukázka: akce s nejvyšší slevou a obrázkem, z každého obchodu nejdřív po jedné
     * (ať ukázka neukazuje šest jogurtů z jednoho letáku).
     *
     * @return list<Offer>
     */
    private function topOffers(): array
    {
        $limit = config()->integer('letaky.landing.top_offers');
        $candidates = $this->currentOffers()
            ->where('offer_type', OfferType::Discount)
            ->whereNotNull('discount_percent')
            ->whereNotNull('image_url')
            ->orderByDesc('discount_percent')
            ->orderBy('id')
            ->limit($limit * config()->integer('letaky.landing.top_offers_candidates_factor'))
            ->get();

        $picked = [];
        $usedChains = [];
        // Dvě kola: v prvním jen obchody, které v ukázce ještě nejsou, ve druhém kdokoli
        foreach ([true, false] as $distinctChains) {
            foreach ($candidates as $offer) {
                if (count($picked) >= $limit) {
                    break 2;
                }
                if (isset($picked[$offer->id]) || ($distinctChains && isset($usedChains[$offer->chain->value]))) {
                    continue;
                }
                $picked[$offer->id] = $offer;
                $usedChains[$offer->chain->value] = true;
            }
        }

        return array_values($picked);
    }

    /**
     * Neskončené a obchodem nestažené akce (R16).
     *
     * @return Builder<Offer>
     */
    private function currentOffers(): Builder
    {
        return Offer::query()->active()->notExpired($this->calendar->today())->with('stores');
    }
}
