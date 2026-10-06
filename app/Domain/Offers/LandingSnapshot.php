<?php

/**
 * Data úvodní stránky pro nepřihlášené (R44, R90) v cache (R95) — počty, obchody, akce
 * s nejvyšší slevou, živá ukázka hlídání a hra. Úvodní stránka je vstupem pro všechny nové
 * návštěvníky a bez cache stojí každé načtení ~350 ms dotazů (lokálně), přitom se data mění
 * jen se staženími akcí.
 *
 * Klíč obsahuje místní datum (hra se mění jednou denně, platnost akcí) a konec posledního
 * úspěšného stažení — po stažení se data spočítají znovu hned. Krátká platnost
 * (letaky.landing.cache_minutes) pokryje úpravy katalogu a akce stažené obchodem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Sources\ImportFreshness;
use App\Enums\Chain;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

final class LandingSnapshot
{
    /** Začátek klíče v cache; za ním datum a čas posledního stažení. */
    private const CACHE_KEY = 'landing.snapshot';

    public function __construct(
        private readonly OfferHighlights $highlights,
        private readonly OfferPresenter $presenter,
        private readonly WatchDemo $demo,
        private readonly UnitPriceQuiz $quiz,
        private readonly OfferPages $pages,
        private readonly ImportFreshness $freshness,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Data úvodní stránky z cache, nebo nově spočítaná.
     *
     * @return array{stats: array{offers: int, chains: int, products: int, topDiscount: int|null}, chains: list<string>, chainUrls: array<string, string>, topOffers: list<array<string, mixed>>, demo: array{products: list<array{id: int, name: string, icon: string}>, preselected: list<int>, initial: array{count: int, offers: list<array<string, mixed>>}}, quiz: list<array{product: string, offers: list<array<string, mixed>>, cheaperId: int}>}
     */
    public function get(): array
    {
        return Cache::remember($this->key(), now()->addMinutes(config()->integer('letaky.landing.cache_minutes')), fn (): array => $this->build());
    }

    /**
     * Klíč: datum a konec posledního úspěšného stažení (nové stažení = nový klíč).
     */
    private function key(): string
    {
        return implode('.', [
            self::CACHE_KEY,
            $this->calendar->today()->toDateString(),
            $this->freshness->lastSucceededAt()?->getTimestamp() ?? 0,
        ]);
    }

    /**
     * Spočítá data úvodní stránky.
     *
     * @return array{stats: array{offers: int, chains: int, products: int, topDiscount: int|null}, chains: list<string>, chainUrls: array<string, string>, topOffers: list<array<string, mixed>>, demo: array{products: list<array{id: int, name: string, icon: string}>, preselected: list<int>, initial: array{count: int, offers: list<array<string, mixed>>}}, quiz: list<array{product: string, offers: list<array<string, mixed>>, cheaperId: int}>}
     */
    private function build(): array
    {
        $chains = $this->highlights->chains();
        $topOffers = $this->highlights->topDiscounts(config()->integer('letaky.landing.top_offers'));
        $demoProducts = $this->demo->products();
        $preselected = array_slice(array_column($demoProducts, 'id'), 0, config()->integer('letaky.landing.demo_preselected'));

        $chainUrls = [];
        foreach ($chains as $chain) {
            $chainUrls[$chain->value] = $this->pages->chainUrl($chain);
        }

        return [
            'stats' => [
                'offers' => $this->highlights->currentCount(),
                'chains' => count($chains),
                'products' => Product::query()->count(),
                // Nejvyšší sleva z ukázky akcí — ta je od nejvyšší slevy
                'topDiscount' => $topOffers === [] ? null : $topOffers[0]->effectiveDiscountPercent(),
            ],
            'chains' => array_map(fn (Chain $chain): string => $chain->value, $chains),
            'chainUrls' => $chainUrls,
            'topOffers' => array_map(fn (Offer $offer): array => $this->presenter->toPage($offer), $topOffers),
            'demo' => [
                'products' => $demoProducts,
                'preselected' => $preselected,
                // Výsledek pro předvybrané produkty a všechny obchody — bez dotazu po načtení
                'initial' => $this->demo->result($preselected, []),
            ],
            'quiz' => $this->quiz->rounds(),
        ];
    }
}
