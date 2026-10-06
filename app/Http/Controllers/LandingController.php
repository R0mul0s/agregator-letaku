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

use App\Domain\Offers\OfferHighlights;
use App\Domain\Offers\OfferPresenter;
use App\Enums\Chain;
use App\Models\Offer;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(
        private readonly OfferHighlights $highlights,
        private readonly OfferPresenter $presenter,
    ) {}

    /**
     * Zobrazí úvodní stránku s počty a ukázkou akcí.
     */
    public function show(): Response
    {
        $chains = $this->highlights->chains();

        return Inertia::render('Landing', [
            'urls' => [
                'register' => route('register', absolute: false),
                'login' => route('login', absolute: false),
                'offers' => route('offers', absolute: false),
            ],
            'stats' => [
                'offers' => $this->highlights->currentCount(),
                'chains' => count($chains),
                'products' => Product::query()->count(),
            ],
            'chains' => array_map(fn (Chain $chain): string => $chain->value, $chains),
            // Logo obchodu odkazuje na jeho akce (R68) — robot jinak stránky obchodů najde jen
            // v sitemap (výběr obchodu ve Všech akcích jsou tlačítka)
            'chainUrls' => array_reduce(
                $chains,
                fn (array $urls, Chain $chain): array => [...$urls, $chain->value => route('offers', ['chain' => $chain->value], absolute: false)],
                [],
            ),
            'topOffers' => array_map(
                fn (Offer $offer): array => $this->presenter->toPage($offer),
                $this->highlights->topDiscounts(config()->integer('letaky.landing.top_offers')),
            ),
        ]);
    }
}
