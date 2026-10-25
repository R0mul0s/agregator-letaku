<?php

/**
 * Hlavička HTML pro vyhledávače a sdílení (R45): titulek, popis, canonical, robots,
 * Open Graph a schema.org. Aplikace je SPA bez SSR (hosting nemá Node, R20) — co má
 * vidět robot bez JavaScriptu nebo náhled odkazu, musí být v šabloně ze serveru.
 *
 * Indexovat se smí jen veřejné stránky: úvodní stránka, Všechny akce (bez hledání), akce
 * obchodu a produktu katalogu na čisté adrese (R94), Nejlepší slevy týdne (R128), kontakt (R72).
 * Přihlášení a registrace „noindex, follow“, vše za přihlášením „noindex, nofollow“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Offers\IsoWeek;
use App\Domain\Offers\OfferFeed;
use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPages;
use App\Domain\Offers\OfferSearch;
use App\Domain\Offers\ProductOfferSummary;
use App\Domain\Offers\WeeklyDeals;
use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use App\Support\PriceFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class SeoMeta
{
    /** Routy výpisu akcí — `/akce`, čistá adresa obchodu nebo produktu (R94) a prodejny (R172). */
    private const OFFERS_ROUTES = ['offers', OfferPages::PAGE_ROUTE, OfferPages::STORE_ROUTE];

    public function __construct(
        private readonly OfferPages $pages,
        private readonly OfferSearch $search,
        private readonly StructuredData $structuredData,
        private readonly OfferFeed $feed,
        private readonly ProductOfferSummary $summaries,
        private readonly PriceFormatter $prices,
    ) {}

    private const INDEX = 'index, follow';

    private const NOINDEX_FOLLOW = 'noindex, follow';

    private const NOINDEX = 'noindex, nofollow';

    /** Stránky přihlášení a registrace — odkazy z nich ano, samy do výsledků ne. */
    private const AUTH_ROUTES = ['login', 'register', 'password.request', 'password.reset'];

    /** Přihlášení a registrace — vlastní texty v app.seo.pages (název routy = druh stránky, R123). */
    private const AUTH_PAGES = ['login', 'register'];

    /** Obrázek pro sdílení (1200 × 630, zdroj resources/brand/og-image.html), i pro schema.org. */
    public const OG_IMAGE_PATH = 'images/brand/og-image.png';

    private const OG_IMAGE_WIDTH = 1200;

    private const OG_IMAGE_HEIGHT = 630;

    /** Konec platnosti v popisu produktu (R158): „28. 10.“ — rok je zřejmý. */
    private const SHORT_DATE_FORMAT = 'j. n.';

    /**
     * Metadata stránky podle routy a parametrů. `heading` je nadpis stránky pro obsah
     * pro roboty bez JavaScriptu (resources/views/seo/content.blade.php, R94). `$inertiaPage`
     * je stránka Inertie z kořenové šablony — z jejích props jsou akce ve schema.org (R99).
     *
     * `feed` je kanál RSS akcí obchodu nebo produktu (R149), jinde null.
     *
     * @param  array<string, mixed>  $inertiaPage
     * @return array{title: string, heading: string, description: string, canonical: string, robots: string, feed: array{url: string, title: string}|null, image: array{url: string, width: int, height: int, alt: string}, jsonLd: array<string, mixed>|null}
     */
    public function forRequest(Request $request, array $inertiaPage = []): array
    {
        $routeName = (string) $request->route()?->getName();
        [$chain, $productId] = $this->target($request, $routeName);
        $storeCode = $this->storeCode($request, $routeName);
        $page = $this->page($request, $routeName, $chain, $productId, $storeCode);
        $props = is_array($inertiaPage['props'] ?? null) ? $inertiaPage['props'] : [];
        $robots = $this->robots($routeName, $page, $request, $productId, $props);
        $replace = $this->replacements($chain, $productId, $this->week($request, $routeName), $storeCode);
        $title = $this->text($request, $page, 'title', $replace, $productId);
        $heading = __("app.seo.pages.{$page}.heading", $replace);
        $description = $this->text($request, $page, 'description', $replace, $productId);
        $canonical = $this->canonical($request, $routeName, $chain, $productId, $storeCode);

        return [
            'title' => $title,
            'heading' => $heading,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'feed' => $this->feedLink($page, $chain, $productId),
            'image' => [
                'url' => asset(self::OG_IMAGE_PATH),
                'width' => self::OG_IMAGE_WIDTH,
                'height' => self::OG_IMAGE_HEIGHT,
                'alt' => __('app.seo.og_image_alt'),
            ],
            // Strukturovaná data jen na indexovaných stránkách
            'jsonLd' => $robots === self::INDEX
                ? $this->structuredData->graph($page, $title, $heading, $description, $canonical, $chain, $productId, $props)
                : null,
        ];
    }

    /**
     * Titulek stránky. Veřejné stránky ho dostávají i do Vue (sdílená vlastnost seoTitle,
     * R68) — jinak by ho po načtení přepsal obecný titulek z <Head> a Google by viděl ten.
     */
    public function title(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        [$chain, $productId] = $this->target($request, $routeName);
        $storeCode = $this->storeCode($request, $routeName);

        return $this->text($request, $this->page($request, $routeName, $chain, $productId, $storeCode), 'title', $this->replacements($chain, $productId, $this->week($request, $routeName), $storeCode), $productId);
    }

    /**
     * Nadpis stránky (h1) — výpis akcí ho ukazuje i ve Vue („Pivo v akci“, „Akce z letáku
     * Lidlu“, R94), aby nadpis seděl s titulkem pro vyhledávače.
     */
    public function heading(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        [$chain, $productId] = $this->target($request, $routeName);
        $storeCode = $this->storeCode($request, $routeName);

        return __("app.seo.pages.{$this->page($request, $routeName, $chain, $productId, $storeCode)}.heading", $this->replacements($chain, $productId, $this->week($request, $routeName), $storeCode));
    }

    /**
     * Adresa úvodní stránky s koncovým lomítkem („https://slevohlidka.cz/“) —
     * jednotně v canonical, sitemap.xml, llms.txt a schema.org.
     */
    public static function homeUrl(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    /**
     * Druh stránky pro texty v app.seo.pages.
     */
    private function page(Request $request, string $routeName, ?Chain $chain, ?int $productId, ?string $storeCode = null): string
    {
        return match (true) {
            $routeName === 'home' && $request->user() === null => 'home',
            $storeCode !== null => 'offers_store',
            $this->isOffers($routeName) && $productId !== null => 'offers_product',
            $this->isOffers($routeName) && $chain !== null => 'offers_chain',
            $this->isOffers($routeName) => 'offers',
            $routeName === WeeklyDeals::ROUTE => 'weekly',
            // Vlastní titulek a nadpis i v obsahu bez JS (R123) — noindex, ale robot je projde
            in_array($routeName, self::AUTH_PAGES, true) => $routeName,
            default => PublicPages::kind($routeName) ?? 'default',
        };
    }

    /**
     * Obchod a produkt katalogu výpisu akcí — z čisté adresy (`/akce/lidl`, `/akce/pivo`)
     * nebo z parametru jednoho obchodu či produktu; jinde [null, null].
     *
     * @return array{0: Chain|null, 1: int|null}
     */
    private function target(Request $request, string $routeName): array
    {
        if (! $this->isOffers($routeName)) {
            return [null, null];
        }

        $slug = $request->route('slug');
        $store = $request->route('store');
        $path = match (true) {
            is_string($slug) && is_string($store) => $this->pages->resolveStore($slug, $store),
            is_string($slug) => $this->pages->resolve($slug),
            default => null,
        };
        $chain = $request->enum(OffersRequest::CHAIN, Chain::class) ?? $path['chain'] ?? null;
        $productId = ($request->integer(OffersRequest::PRODUCT) ?: null) ?? $path['productId'] ?? null;

        return [$chain, $productId !== null && $this->pages->productName($productId) !== null ? $productId : null];
    }

    /**
     * Prodejna stránky prodejny `/akce/kaufland/trutnov` (R172); jinde null.
     */
    private function storeCode(Request $request, string $routeName): ?string
    {
        $slug = $request->route('slug');
        $store = $request->route('store');

        return $routeName === OfferPages::STORE_ROUTE && is_string($slug) && is_string($store)
            ? $this->pages->resolveStore($slug, $store)['storeCode'] ?? null
            : null;
    }

    /**
     * Týden stránky Nejlepší slevy týdne z adresy (R128); jinde null.
     */
    private function week(Request $request, string $routeName): ?IsoWeek
    {
        $slug = $request->route(WeeklyDeals::WEEK_PARAMETER);

        return $routeName === WeeklyDeals::ROUTE && is_string($slug) ? IsoWeek::fromSlug($slug) : null;
    }

    /**
     * Titulek nebo popis stránky. Produkt s dnes platnou akcí (R158) má v obou aktuální cenu
     * a počet akcí — proklik z výsledků hledání byl s obecným textem kolem 1 %. Jen
     * nefiltrovaný výpis produktu; filtrovaný se neindexuje a čísla by s ním neseděla.
     *
     * @param  'title'|'description'  $field
     * @param  array<string, string>  $replace
     */
    private function text(Request $request, string $page, string $field, array $replace, ?int $productId): string
    {
        $summary = $page === 'offers_product' && $productId !== null && ! $this->isFiltered($request)
            ? $this->summaries->for($productId)
            : null;
        if ($summary === null) {
            return __("app.seo.pages.{$page}.{$field}", $replace);
        }

        if ($field === 'title') {
            return trans_choice('app.seo.pages.offers_product.title_offers', $summary['count'], [
                ...$replace,
                'price' => $this->prices->format($summary['lowestPrice']),
            ]);
        }

        $best = $summary['best'];
        $others = array_values(array_filter($summary['chains'], fn (Chain $chain): bool => $chain !== $best['chain']));

        return __('app.seo.pages.offers_product.description_offers', [
            ...$replace,
            'chain' => $best['chain']->label(),
            'price' => $this->prices->format($best['price']),
            'unit_price' => $best['unitPrice'] === null ? '' : __('app.seo.pages.offers_product.description_unit_price', [
                'price' => $this->prices->format($best['unitPrice']),
                'unit' => __('app.ui.unit_price_units.'.$best['unitKey']),
            ]),
            'date' => $best['validTo']->format(self::SHORT_DATE_FORMAT),
            'others' => $others === [] ? '' : __('app.seo.pages.offers_product.description_others', [
                'chains' => self::listOf(array_map(fn (Chain $chain): string => $chain->label(), $others)),
            ]),
        ]);
    }

    /**
     * Výčet „Kaufland, Tesco a Billa“.
     *
     * @param  list<string>  $items
     */
    private static function listOf(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).__('app.seo.list_last_separator').$last;
    }

    /**
     * Doplňované hodnoty textů: obchod ve 2. pádě i v 1. pádě, název produktu, prodejna (R172) a číslo, rok a dny týdne.
     *
     * @return array<string, string>
     */
    private function replacements(?Chain $chain, ?int $productId, ?IsoWeek $week = null, ?string $storeCode = null): array
    {
        $store = $storeCode === null ? null : $this->pages->store($storeCode);

        return [
            'chain' => $chain?->genitive() ?? '',
            // Stránka prodejny (R172): „Kaufland Trutnov“, „Globus Brno“
            'chain_name' => $chain?->label() ?? '',
            'store' => $store['name'] ?? '',
            'product' => $productId === null ? '' : (string) $this->pages->productName($productId),
            'number' => (string) $week?->number,
            'year' => (string) $week?->year,
            'range' => $week?->range() ?? '',
        ];
    }

    /**
     * Kanál RSS stránky obchodu nebo produktu (R149) pro odkaz v hlavičce — čtečka ho na
     * stránce najde sama; jinde null.
     *
     * @return array{url: string, title: string}|null
     */
    private function feedLink(string $page, ?Chain $chain, ?int $productId): ?array
    {
        if (! in_array($page, ['offers_chain', 'offers_product'], true)) {
            return null;
        }
        $url = OfferFeed::url($productId === null ? $chain : null, $productId, $this->pages, absolute: true);

        return $url === null ? null : [
            'url' => $url,
            'title' => $this->feed->title($productId === null ? $chain : null, $productId),
        ];
    }

    /**
     * Je to výpis akcí (`/akce` nebo čistá adresa)?
     */
    private function isOffers(string $routeName): bool
    {
        return in_array($routeName, self::OFFERS_ROUTES, true);
    }

    /**
     * Pravidlo pro roboty: veřejné stránky indexovat (kromě podmínek a zásad, R121), výsledky hledání ne (nekonečně
     * kombinací, slabý obsah), produkt bez akcí ne (prázdná stránka), přihlášení
     * a registraci ne, vše ostatní ani sledovat. Týden bez slev (jen aktuální, archiv je jen se
     * slevami — R128) ne.
     *
     * @param  array<string, mixed>  $props  Props stránky Inertie
     */
    private function robots(string $routeName, string $page, Request $request, ?int $productId, array $props): string
    {
        if ($page === 'weekly') {
            return ($props['top'] ?? []) === [] && ($props['chainSections'] ?? []) === [] ? self::NOINDEX_FOLLOW : self::INDEX;
        }
        if ($page === 'home' || $this->isOffers($routeName)) {
            if ($this->isFiltered($request)) {
                return self::NOINDEX_FOLLOW;
            }

            return $productId !== null && ! $this->search->query(null, new OfferFilters(productId: $productId))->exists()
                ? self::NOINDEX_FOLLOW
                : self::INDEX;
        }
        if (PublicPages::isPublic($page)) {
            // Podmínky a zásady veřejné, ale ne ve výsledcích hledání (R121)
            return PublicPages::isIndexed($page) ? self::INDEX : self::NOINDEX_FOLLOW;
        }

        return in_array($routeName, self::AUTH_ROUTES, true) ? self::NOINDEX_FOLLOW : self::NOINDEX;
    }

    /**
     * Kanonická adresa: výpis akcí na čisté adrese obchodu nebo produktu (R94), z parametrů
     * jen stránka. Rozsah „Načíst další“ (?od=) je stejný obsah jako jeho poslední stránka.
     * Výsledky hledání (noindex) odkazují samy na sebe — noindex a canonical jinam jsou
     * protichůdné signály. Adresa z APP_URL (url()->current()), ne z požadavku — s kořenovým
     * .htaccess by nesla /public (R67).
     */
    private function canonical(Request $request, string $routeName, ?Chain $chain, ?int $productId, ?string $storeCode = null): string
    {
        if (! $this->isOffers($routeName)) {
            // Úvodní stránka s koncovým lomítkem ("https://…/"), ostatní bez
            return $request->is('/') ? self::homeUrl() : url()->current();
        }

        if ($this->isFiltered($request)) {
            $query = Arr::query($request->query());

            return url()->current().($query === '' ? '' : '?'.$query);
        }

        $page = $request->integer(OffersRequest::PAGE);

        return $this->pages->url(
            new OfferFilters($chain === null ? [] : [$chain], $productId, storeCodes: $storeCode === null ? [] : [$storeCode], storeChoice: $storeCode !== null),
            [OffersRequest::PAGE => $page > 1 ? $page : null],
            absolute: true,
        );
    }

    /**
     * Výpis zúžený hledáním, jen budoucími akcemi (R76), bez e-shopu nebo víc obchody
     * najednou (R82), končícími, novými, slevami od procent (R101), oddělením (R102) nebo prodejnou (R164),
     * produkt omezený na obchod — nekonečně kombinací, do výsledků hledání nepatří. Indexuje
     * se celý výpis, výpis jednoho obchodu a výpis produktu (R94).
     */
    private function isFiltered(Request $request): bool
    {
        $multipleChains = $request->filled(OffersRequest::CHAIN) && $request->enum(OffersRequest::CHAIN, Chain::class) === null;
        $slug = $request->route('slug');
        $path = is_string($slug) ? $this->pages->resolve($slug) : null;
        // Produkt v adrese i s obchodem (/akce/pivo?chain=lidl), nebo obchod v adrese s jiným v parametru
        $combined = $path !== null && $request->filled(OffersRequest::CHAIN)
            || $request->filled(OffersRequest::PRODUCT) && $request->filled(OffersRequest::CHAIN);

        return $request->filled('q') || $request->boolean(OffersRequest::UPCOMING)
            || $request->boolean(OffersRequest::WITHOUT_ESHOP) || $multipleChains || $combined
            || $request->boolean(OffersRequest::ENDING_SOON) || $request->boolean(OffersRequest::FRESH)
            || $request->filled(OffersRequest::MIN_DISCOUNT) || $request->filled(OffersRequest::DEPARTMENT)
            || $request->filled(OffersRequest::STORE_FORMAT) || $request->filled(OffersRequest::STORES);
    }
}
