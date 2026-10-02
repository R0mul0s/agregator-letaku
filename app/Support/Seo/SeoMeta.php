<?php

/**
 * Hlavička HTML pro vyhledávače a sdílení (R45): titulek, popis, canonical, robots,
 * Open Graph a schema.org. Aplikace je SPA bez SSR (hosting nemá Node, R20) — co má
 * vidět robot bez JavaScriptu nebo náhled odkazu, musí být v šabloně ze serveru.
 *
 * Indexovat se smí jen veřejné stránky: úvodní stránka a Všechny akce (bez hledání).
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
        $chain = $routeName === 'offers' ? $request->enum('chain', Chain::class) : null;
        $page = match (true) {
            $routeName === 'home' && $request->user() === null => 'home',
            $routeName === 'offers' && $chain !== null => 'offers_chain',
            $routeName === 'offers' => 'offers',
            default => 'default',
        };
        $robots = $this->robots($routeName, $page, $request);
        $replacements = ['chain' => $chain?->label() ?? ''];

        return [
            'title' => __("app.seo.pages.{$page}.title", $replacements),
            'description' => __("app.seo.pages.{$page}.description", $replacements),
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
     * Adresa úvodní stránky s koncovým lomítkem („https://slevohlidka.rhsoft.cz/“) —
     * jednotně v canonical, sitemap.xml, llms.txt a schema.org.
     */
    public static function homeUrl(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    /**
     * Pravidlo pro roboty: veřejné stránky indexovat, výsledky hledání ne (nekonečně
     * kombinací, slabý obsah), přihlášení a registraci ne, vše ostatní ani sledovat.
     */
    private function robots(string $routeName, string $page, Request $request): string
    {
        if ($page === 'home' || $page === 'offers_chain' || $page === 'offers') {
            return $request->filled('q') ? self::NOINDEX_FOLLOW : self::INDEX;
        }

        return in_array($routeName, self::AUTH_ROUTES, true) ? self::NOINDEX_FOLLOW : self::NOINDEX;
    }

    /**
     * Kanonická adresa: bez parametrů kromě obchodu a stránky ve Všech akcích. Rozsah
     * „Načíst další“ (?od=) je stejný obsah jako jeho poslední stránka.
     */
    private function canonical(Request $request, string $routeName): string
    {
        if ($routeName !== 'offers') {
            // Úvodní stránka s koncovým lomítkem ("https://…/"), ostatní bez
            return $request->is('/') ? self::homeUrl() : $request->url();
        }

        $query = http_build_query(array_filter([
            'chain' => $request->enum('chain', Chain::class)?->value,
            OffersRequest::PAGE => $request->integer(OffersRequest::PAGE) > 1 ? $request->integer(OffersRequest::PAGE) : null,
        ]));

        return $request->url().($query === '' ? '' : '?'.$query);
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
