<?php

/**
 * Odběr akcí přes RSS (R149): nejnovější neskončené akce jednoho obchodu (`/akce/lidl/rss`)
 * nebo produktu katalogu (`/akce/pivo/rss`) — stejné akce jako výpis na čisté adrese (R94),
 * jen od nejnověji zveřejněných. Čtečka RSS, Telegram nebo Discord je pak ohlásí samy.
 * Čas změny kanálu je poslední přibytí nebo stažení akce (OfferPageChanges, R122).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Models\Offer;
use App\Support\PriceFormatter;
use App\Support\ShortDate;
use Carbon\CarbonImmutable;

final readonly class OfferFeed
{
    /** Část adresy kanálu za stránkou výpisu (`/akce/lidl/rss`). */
    public const PATH_SUFFIX = 'rss';

    /** Název routy kanálu. */
    public const ROUTE = 'offers.feed';

    private const SECONDS_PER_MINUTE = 60;

    public function __construct(
        private OfferSearch $search,
        private OfferPages $pages,
        private OfferPageChanges $changes,
        private PriceFormatter $prices,
        private ShortDate $dates,
    ) {}

    /**
     * Adresa kanálu pro obchod nebo produkt (null = výpis bez kanálu — víc obchodů, bez výběru).
     */
    public static function url(?Chain $chain, ?int $productId, OfferPages $pages, bool $absolute = false): ?string
    {
        $slug = $pages->slug(new OfferFilters($chain === null ? [] : [$chain], $productId));

        return $slug === null ? null : route(self::ROUTE, ['slug' => $slug], $absolute);
    }

    /**
     * Kanál pro obchod nebo produkt: název, adresa stránky, čas poslední změny a akce.
     *
     * @return array{title: string, description: string, link: string, self: string, updatedAt: CarbonImmutable|null, ttl: int, items: list<array{title: string, link: string, guid: string, description: string, pubDate: string}>}
     */
    public function build(?Chain $chain, ?int $productId): array
    {
        $filters = new OfferFilters($chain === null ? [] : [$chain], $productId);
        $offers = $this->search->query(null, $filters)
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(config()->integer('letaky.feed.items'))
            ->get();
        $link = $this->pages->url($filters, absolute: true);
        $productName = $productId === null ? null : (string) $this->pages->productName($productId);

        return [
            'title' => $this->title($chain, $productId),
            'description' => $productName === null
                ? __('app.feed.chain_description', ['chain' => $chain?->genitive() ?? ''])
                : __('app.feed.product_description', ['product' => $productName]),
            'link' => $link,
            'self' => (string) self::url($chain, $productId, $this->pages, absolute: true),
            'updatedAt' => $productId === null
                ? ($chain === null ? null : $this->changes->byChain()[$chain->value] ?? null)
                : $this->changes->byProduct([$productId])[$productId] ?? null,
            // Jak často se čtečka má ptát (minuty) — stejně dlouho platí odpověď v cache
            'ttl' => intdiv(config()->integer('letaky.feed.cache_seconds'), self::SECONDS_PER_MINUTE),
            'items' => array_values($offers->map(fn (Offer $offer): array => $this->item($offer, $link, withChain: $productId !== null))->all()),
        ];
    }

    /**
     * Název kanálu — i pro odkaz v hlavičce stránky (SeoMeta).
     */
    public function title(?Chain $chain, ?int $productId): string
    {
        return $productId === null
            ? __('app.feed.chain_title', ['chain' => $chain?->genitive() ?? ''])
            : __('app.feed.product_title', ['product' => (string) $this->pages->productName($productId)]);
    }

    /**
     * Položka kanálu: název s cenou (u produktu i s obchodem), odkaz k obchodu nebo na stránku
     * výpisu a popis s původní cenou, cenou s kartou a platností.
     *
     * @return array{title: string, link: string, guid: string, description: string, pubDate: string}
     */
    private function item(Offer $offer, string $pageLink, bool $withChain): array
    {
        $price = $offer->price ?? $offer->loyalty_price;
        $priceText = $price === null ? '' : $this->prices->format($price);
        $replace = ['name' => $offer->name, 'price' => $priceText, 'chain' => $offer->chain->label()];
        $title = match (true) {
            $price === null => $offer->name,
            $withChain => __('app.feed.item_title_chain', $replace),
            default => __('app.feed.item_title', $replace),
        };

        $lines = [];
        $discount = $offer->effectiveDiscountPercent();
        if ($discount !== null && $offer->original_price !== null) {
            $lines[] = __('app.feed.item_discount', ['percent' => $discount, 'original' => $this->prices->format($offer->original_price)]);
        }
        if ($offer->loyalty_price !== null && $offer->price !== null && $offer->loyalty_program !== null) {
            $lines[] = __('app.feed.item_loyalty', ['program' => $offer->loyalty_program->label(), 'price' => $this->prices->format($offer->loyalty_price)]);
        }
        $lines[] = __('app.feed.item_validity', ['from' => $this->dates->format($offer->valid_from), 'to' => $this->dates->format($offer->valid_to)]);
        $lines[] = __('app.feed.item_chain', ['chain' => $offer->chain->label()]);

        return [
            'title' => $title,
            'link' => $offer->source_url ?? $pageLink,
            'guid' => 'slevohlidka-offer-'.$offer->id,
            'description' => implode(' · ', $lines),
            'pubDate' => $offer->created_at->toRssString(),
        ];
    }
}
