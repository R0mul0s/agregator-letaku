<?php

/**
 * Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
 * a ukázka skutečných akcí s nejvyšší slevou. Přihlášený má na stejné adrese Moje slevy.
 * Hledání v hlavním pruhu, živá ukázka hlídání a hra „Co je levnější?“ (R90).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\OfferHighlights;
use App\Domain\Offers\OfferPages;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\UnitPriceQuiz;
use App\Domain\Offers\WatchDemo;
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
        private readonly WatchDemo $demo,
        private readonly UnitPriceQuiz $quiz,
        private readonly OfferPages $pages,
    ) {}

    /**
     * Zobrazí úvodní stránku s počty, ukázkou akcí, ukázkou hlídání a hrou.
     */
    public function show(): Response
    {
        $chains = $this->highlights->chains();
        $topOffers = $this->highlights->topDiscounts(config()->integer('letaky.landing.top_offers'));
        $demoProducts = $this->demo->products();
        $preselected = array_slice(array_column($demoProducts, 'id'), 0, config()->integer('letaky.landing.demo_preselected'));

        return Inertia::render('Landing', [
            'urls' => [
                'register' => route('register', absolute: false),
                'login' => route('login', absolute: false),
                'offers' => route('offers', absolute: false),
                'suggestions' => route('offers.suggestions', absolute: false),
            ],
            'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
            'stats' => [
                'offers' => $this->highlights->currentCount(),
                'chains' => count($chains),
                'products' => Product::query()->count(),
                // Nejvyšší sleva z ukázky akcí — ta je od nejvyšší slevy
                'topDiscount' => $topOffers === [] ? null : $topOffers[0]->effectiveDiscountPercent(),
            ],
            'chains' => array_map(fn (Chain $chain): string => $chain->value, $chains),
            // Logo obchodu odkazuje na jeho akce (R68) — robot jinak stránky obchodů najde jen
            // v sitemap (výběr obchodu ve Všech akcích jsou tlačítka)
            'chainUrls' => array_reduce(
                $chains,
                fn (array $urls, Chain $chain): array => [...$urls, $chain->value => $this->pages->chainUrl($chain)],
                [],
            ),
            'topOffers' => array_map(fn (Offer $offer): array => $this->presenter->toPage($offer), $topOffers),
            'demo' => [
                'url' => route('watch-demo', absolute: false),
                'products' => $demoProducts,
                'preselected' => $preselected,
                'maxProducts' => config()->integer('letaky.landing.demo_max_products'),
                // Výsledek pro předvybrané produkty a všechny obchody — bez dotazu po načtení
                'initial' => $this->demo->result($preselected, []),
            ],
            'historyWeeks' => config()->integer('letaky.price_history.weeks'),
            'quiz' => $this->quiz->rounds(),
        ]);
    }
}
