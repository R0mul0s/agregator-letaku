<?php

/**
 * Hlavička HTML pro vyhledávače a sdílení (R45): titulek, popis, canonical, robots,
 * Open Graph a schema.org. Aplikace je SPA bez SSR (hosting nemá Node, R20) — co má
 * vidět robot bez JavaScriptu nebo náhled odkazu, musí být v šabloně ze serveru.
 *
 * Indexovat se smí jen veřejné stránky: úvodní stránka, Všechny akce (bez hledání), akce
 * obchodu a produktu katalogu na čisté adrese (R94), právní stránky (R51), kontakt (R72).
 * Přihlášení a registrace „noindex, follow“, vše za přihlášením „noindex, nofollow“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPages;
use App\Domain\Offers\OfferSearch;
use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class SeoMeta
{
    /** Routy výpisu akcí — `/akce` a čistá adresa obchodu nebo produktu (R94). */
    private const OFFERS_ROUTES = ['offers', OfferPages::PAGE_ROUTE];

    public function __construct(
        private readonly OfferPages $pages,
        private readonly OfferSearch $search,
        private readonly StructuredData $structuredData,
    ) {}

    private const INDEX = 'index, follow';

    private const NOINDEX_FOLLOW = 'noindex, follow';

    private const NOINDEX = 'noindex, nofollow';

    /** Stránky přihlášení a registrace — odkazy z nich ano, samy do výsledků ne. */
    private const AUTH_ROUTES = ['login', 'register', 'password.request', 'password.reset'];

    /** Obrázek pro sdílení (1200 × 630, zdroj resources/brand/og-image.html), i pro schema.org. */
    public const OG_IMAGE_PATH = 'images/brand/og-image.png';

    private const OG_IMAGE_WIDTH = 1200;

    private const OG_IMAGE_HEIGHT = 630;

    /**
     * Metadata stránky podle routy a parametrů. `heading` je nadpis stránky pro obsah
     * pro roboty bez JavaScriptu (resources/views/seo/content.blade.php, R94). `$inertiaPage`
     * je stránka Inertie z kořenové šablony — z jejích props jsou akce ve schema.org (R99).
     *
     * @param  array<string, mixed>  $inertiaPage
     * @return array{title: string, heading: string, description: string, canonical: string, robots: string, image: array{url: string, width: int, height: int, alt: string}, jsonLd: array<string, mixed>|null}
     */
    public function forRequest(Request $request, array $inertiaPage = []): array
    {
        $routeName = (string) $request->route()?->getName();
        [$chain, $productId] = $this->target($request, $routeName);
        $page = $this->page($request, $routeName, $chain, $productId);
        $robots = $this->robots($routeName, $page, $request, $productId);
        $replace = $this->replacements($chain, $productId);
        $title = __("app.seo.pages.{$page}.title", $replace);
        $heading = __("app.seo.pages.{$page}.heading", $replace);
        $description = __("app.seo.pages.{$page}.description", $replace);
        $canonical = $this->canonical($request, $routeName, $chain, $productId);
        $props = is_array($inertiaPage['props'] ?? null) ? $inertiaPage['props'] : [];

        return [
            'title' => $title,
            'heading' => $heading,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
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

        return __("app.seo.pages.{$this->page($request, $routeName, $chain, $productId)}.title", $this->replacements($chain, $productId));
    }

    /**
     * Nadpis stránky (h1) — výpis akcí ho ukazuje i ve Vue („Pivo v akci“, „Akce z letáku
     * Lidlu“, R94), aby nadpis seděl s titulkem pro vyhledávače.
     */
    public function heading(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        [$chain, $productId] = $this->target($request, $routeName);

        return __("app.seo.pages.{$this->page($request, $routeName, $chain, $productId)}.heading", $this->replacements($chain, $productId));
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
    private function page(Request $request, string $routeName, ?Chain $chain, ?int $productId): string
    {
        return match (true) {
            $routeName === 'home' && $request->user() === null => 'home',
            $this->isOffers($routeName) && $productId !== null => 'offers_product',
            $this->isOffers($routeName) && $chain !== null => 'offers_chain',
            $this->isOffers($routeName) => 'offers',
            $routeName === 'legal.terms' => 'terms',
            $routeName === 'legal.privacy' => 'privacy',
            $routeName === 'contact' => 'contact',
            default => 'default',
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
        $path = is_string($slug) ? $this->pages->resolve($slug) : null;
        $chain = $request->enum(OffersRequest::CHAIN, Chain::class) ?? $path['chain'] ?? null;
        $productId = ($request->integer(OffersRequest::PRODUCT) ?: null) ?? $path['productId'] ?? null;

        return [$chain, $productId !== null && $this->pages->productName($productId) !== null ? $productId : null];
    }

    /**
     * Doplňované hodnoty textů: obchod ve 2. pádě a název produktu.
     *
     * @return array<string, string>
     */
    private function replacements(?Chain $chain, ?int $productId): array
    {
        return [
            'chain' => $chain?->genitive() ?? '',
            'product' => $productId === null ? '' : (string) $this->pages->productName($productId),
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
     * Pravidlo pro roboty: veřejné stránky indexovat, výsledky hledání ne (nekonečně
     * kombinací, slabý obsah), produkt bez akcí ne (prázdná stránka), přihlášení
     * a registraci ne, vše ostatní ani sledovat.
     */
    private function robots(string $routeName, string $page, Request $request, ?int $productId): string
    {
        if ($page === 'home' || $this->isOffers($routeName)) {
            if ($this->isFiltered($request)) {
                return self::NOINDEX_FOLLOW;
            }

            return $productId !== null && ! $this->search->query(null, new OfferFilters(productId: $productId))->exists()
                ? self::NOINDEX_FOLLOW
                : self::INDEX;
        }
        if (in_array($page, ['terms', 'privacy', 'contact'], true)) {
            return self::INDEX;
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
    private function canonical(Request $request, string $routeName, ?Chain $chain, ?int $productId): string
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
            new OfferFilters($chain === null ? [] : [$chain], $productId),
            [OffersRequest::PAGE => $page > 1 ? $page : null],
            absolute: true,
        );
    }

    /**
     * Výpis zúžený hledáním, jen budoucími akcemi (R76), bez e-shopu nebo víc obchody
     * najednou (R82), produkt omezený na obchod — nekonečně kombinací, do výsledků hledání
     * nepatří. Indexuje se celý výpis, výpis jednoho obchodu a výpis produktu (R94).
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
            || $request->boolean(OffersRequest::WITHOUT_ESHOP) || $multipleChains || $combined;
    }
}
