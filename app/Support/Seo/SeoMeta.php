<?php

/**
 * Hlavička HTML pro vyhledávače a sdílení (R45): titulek, popis, canonical, robots,
 * Open Graph a schema.org. Aplikace je SPA bez SSR (hosting nemá Node, R20) — co má
 * vidět robot bez JavaScriptu nebo náhled odkazu, musí být v šabloně ze serveru.
 *
 * Indexovat se smí jen veřejné stránky: úvodní stránka, Všechny akce (bez hledání)
 * a právní stránky (R51), kontakt (R72). Výpis obchodu jen se zmínkami bez cen (Albert) ne — je prázdný.
 * Přihlášení a registrace „noindex, follow“, vše za přihlášením „noindex, nofollow“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\Chain;
use App\Http\Requests\OffersRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class SeoMeta
{
    private const INDEX = 'index, follow';

    private const NOINDEX_FOLLOW = 'noindex, follow';

    private const NOINDEX = 'noindex, nofollow';

    /** Stránky přihlášení a registrace — odkazy z nich ano, samy do výsledků ne. */
    private const AUTH_ROUTES = ['login', 'register', 'password.request', 'password.reset'];

    /** Obrázek pro sdílení (1200 × 630, zdroj resources/brand/og-image.html). */
    private const OG_IMAGE_PATH = 'images/brand/og-image.png';

    private const OG_IMAGE_WIDTH = 1200;

    private const OG_IMAGE_HEIGHT = 630;

    /** Logo pro schema.org Organization. */
    private const LOGO_PATH = 'images/brand/icon-512.png';

    /**
     * Metadata stránky podle routy a parametrů.
     *
     * @return array{title: string, description: string, canonical: string, robots: string, image: array{url: string, width: int, height: int, alt: string}, jsonLd: list<array<string, mixed>>}
     */
    public function forRequest(Request $request): array
    {
        $routeName = (string) $request->route()?->getName();
        $chain = $this->chain($request, $routeName);
        $page = $this->page($request, $routeName, $chain);
        $robots = $this->robots($routeName, $page, $chain, $request);

        return [
            'title' => $this->title($request),
            'description' => __("app.seo.pages.{$page}.description", ['chain' => $chain?->genitive() ?? '']),
            'canonical' => $this->canonical($request, $routeName),
            'robots' => $robots,
            'image' => [
                'url' => asset(self::OG_IMAGE_PATH),
                'width' => self::OG_IMAGE_WIDTH,
                'height' => self::OG_IMAGE_HEIGHT,
                'alt' => __('app.seo.og_image_alt'),
            ],
            // Strukturovaná data jen na indexovaných stránkách
            'jsonLd' => $robots === self::INDEX ? $this->jsonLd() : [],
        ];
    }

    /**
     * Titulek stránky. Veřejné stránky ho dostávají i do Vue (sdílená vlastnost seoTitle,
     * R68) — jinak by ho po načtení přepsal obecný titulek z <Head> a Google by viděl ten.
     */
    public function title(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $chain = $this->chain($request, $routeName);

        return __("app.seo.pages.{$this->page($request, $routeName, $chain)}.title", ['chain' => $chain?->genitive() ?? '']);
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
    private function page(Request $request, string $routeName, ?Chain $chain): string
    {
        return match (true) {
            $routeName === 'home' && $request->user() === null => 'home',
            $routeName === 'offers' && $chain !== null => 'offers_chain',
            $routeName === 'offers' => 'offers',
            $routeName === 'legal.terms' => 'terms',
            $routeName === 'legal.privacy' => 'privacy',
            $routeName === 'contact' => 'contact',
            default => 'default',
        };
    }

    /**
     * Obchod vybraný ve Všech akcích, jinak null.
     */
    private function chain(Request $request, string $routeName): ?Chain
    {
        return $routeName === 'offers' ? $request->enum('chain', Chain::class) : null;
    }

    /**
     * Pravidlo pro roboty: veřejné stránky indexovat, výsledky hledání ne (nekonečně
     * kombinací, slabý obsah), přihlášení a registraci ne, vše ostatní ani sledovat.
     * Obchod jen se zmínkami v letácích (Albert, R36) má výpis akcí prázdný — neindexovat (R68).
     */
    private function robots(string $routeName, string $page, ?Chain $chain, Request $request): string
    {
        if ($page === 'home' || $page === 'offers_chain' || $page === 'offers') {
            $emptyListing = $chain !== null && $chain->mentionsOnly();

            return $this->isFiltered($request) || $emptyListing ? self::NOINDEX_FOLLOW : self::INDEX;
        }
        if (in_array($page, ['terms', 'privacy', 'contact'], true)) {
            return self::INDEX;
        }

        return in_array($routeName, self::AUTH_ROUTES, true) ? self::NOINDEX_FOLLOW : self::NOINDEX;
    }

    /**
     * Kanonická adresa: bez parametrů kromě obchodu a stránky ve Všech akcích. Rozsah
     * „Načíst další“ (?od=) je stejný obsah jako jeho poslední stránka. Výsledky hledání
     * (noindex) odkazují samy na sebe — noindex a canonical jinam jsou protichůdné signály.
     * Adresa z APP_URL (url()->current()), ne z požadavku — s kořenovým .htaccess by
     * nesla /public (R67).
     */
    private function canonical(Request $request, string $routeName): string
    {
        if ($routeName !== 'offers') {
            // Úvodní stránka s koncovým lomítkem ("https://…/"), ostatní bez
            return $request->is('/') ? self::homeUrl() : url()->current();
        }

        $parameters = $this->isFiltered($request)
            ? $request->query()
            : array_filter([
                'chain' => $request->enum('chain', Chain::class)?->value,
                OffersRequest::PAGE => $request->integer(OffersRequest::PAGE) > 1 ? $request->integer(OffersRequest::PAGE) : null,
            ]);
        $query = Arr::query($parameters);

        return url()->current().($query === '' ? '' : '?'.$query);
    }

    /**
     * Výpis zúžený hledáním, produktem z našeptávače (R71), jen budoucími akcemi (R76), bez
     * e-shopu nebo víc obchody najednou (R82) — nekonečně kombinací, do výsledků hledání
     * nepatří. Indexuje se jen celý výpis a výpis jednoho obchodu.
     */
    private function isFiltered(Request $request): bool
    {
        $multipleChains = $request->filled(OffersRequest::CHAIN) && $request->enum(OffersRequest::CHAIN, Chain::class) === null;

        return $request->filled('q') || $request->filled(OffersRequest::PRODUCT) || $request->boolean(OffersRequest::UPCOMING)
            || $request->boolean(OffersRequest::WITHOUT_ESHOP) || $multipleChains;
    }

    /**
     * schema.org: provozovatel (Organization) a web s vyhledáváním v akcích (WebSite
     * + SearchAction — vyhledávač může nabídnout pole hledání přímo ve výsledcích).
     *
     * @return list<array<string, mixed>>
     */
    private function jsonLd(): array
    {
        $home = self::homeUrl();
        $name = __('app.ui.app_name');

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $home.'#organization',
                'name' => $name,
                'url' => $home,
                'logo' => asset(self::LOGO_PATH),
                'description' => __('app.seo.organization_description'),
                // Kontakt na provozovatele (R51)
                'email' => config('letaky.operator.email'),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => $name,
                'alternateName' => __('app.ui.brand.tagline'),
                'url' => $home,
                'inLanguage' => str_replace('_', '-', app()->getLocale()),
                'publisher' => ['@id' => $home.'#organization'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => route('offers').'?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
