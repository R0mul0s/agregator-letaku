<?php

/**
 * Veřejné stránky, které stažení obchodu změnilo (R105) — pro ohlášení vyhledávačům přes IndexNow.
 *
 * Změna = nová akce nebo akce stažená obchodem v tomto stažení. Pak se změnila úvodní stránka
 * (nejvyšší slevy), Všechny akce, Nejlepší slevy aktuálního týdne (R128), stránka obchodu a stránky produktů katalogu, ke kterým
 * změněné akce patří — produktů jen těch, které jsou v sitemapě (mají neskončené akce).
 * Stažení beze změny nevrátí nic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\ScrapeRun;
use App\Support\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Builder;

final class ChangedOfferPages
{
    public function __construct(
        private readonly OfferPages $pages,
        private readonly WeeklyDeals $weekly,
    ) {}

    /**
     * Absolutní adresy stránek změněných stažením.
     *
     * @return list<string>
     */
    public function forRun(ScrapeRun $run): array
    {
        $changed = Offer::query()
            ->where('chain', $run->chain)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('scrape_run_id', $run->id)->where('created_at', '>=', $run->started_at))
                ->orWhere('withdrawn_at', '>=', $run->started_at));
        if (! (clone $changed)->exists()) {
            return [];
        }

        $productIds = OfferProduct::query()
            ->whereIn('offer_id', (clone $changed)->select('id'))
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $published = array_values(array_intersect($this->pages->productsWithOffers(), $productIds));

        return [
            SeoMeta::homeUrl(),
            route('offers'),
            $this->weekly->url($this->weekly->currentWeek(), absolute: true),
            $this->pages->chainUrl($run->chain, absolute: true),
            ...array_map(fn (int $productId): string => $this->pages->productUrl($productId, absolute: true), $published),
        ];
    }
}
