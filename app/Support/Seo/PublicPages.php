<?php

/**
 * Registr veřejných stránek mimo výpis akcí (podmínky, zásady, kontakt) — z něj berou
 * hlavička pro vyhledávače (SeoMeta: index, podmínky a zásady noindex — R121), schema.org (typ
 * stránky), sitemap.xml (jen indexované), llms.txt
 * a obsah pro roboty bez JavaScriptu (R45, R94, R99, R113). Dřív byl seznam na šesti místech
 * a stránka, na kterou se v jednom zapomnělo, dostala tiše `noindex`.
 *
 * Výpis akcí (úvodní stránka, `/akce`, obchod, produkt) má vlastní pravidla v SeoMeta a OfferPages.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support\Seo;

final class PublicPages
{
    /**
     * Název routy => druh stránky. Druh je klíč textů (`app.seo.pages.*`, `app.ui.footer.*`,
     * `app.llms.*`) — nová stránka potřebuje texty ve všech třech.
     */
    public const PAGES = [
        'legal.terms' => 'terms',
        'legal.privacy' => 'privacy',
        'contact' => 'contact',
    ];

    /**
     * Druhy stránek, které vyhledávače nemají ukazovat ve výsledcích (R121): `noindex, follow`
     * a mimo sitemap.xml. Dál jsou veřejné — odkaz v patičce, v obsahu bez JS a v llms.txt;
     * přihlášení přes Google, Facebook a Seznam potřebuje zásady jen dostupné, ne v indexu.
     */
    private const NOT_INDEXED = ['terms', 'privacy'];

    /** Typ schema.org podle druhu stránky; jiný druh je WebPage. */
    private const SCHEMA_TYPES = ['contact' => 'ContactPage'];

    /**
     * Druh veřejné stránky podle routy, nebo null — routa není v registru.
     */
    public static function kind(string $routeName): ?string
    {
        return self::PAGES[$routeName] ?? null;
    }

    /**
     * Je druh stránky veřejná stránka z registru?
     */
    public static function isPublic(string $page): bool
    {
        return in_array($page, self::PAGES, true);
    }

    /**
     * Má veřejná stránka být ve výsledcích vyhledávání (index a sitemap.xml)?
     */
    public static function isIndexed(string $page): bool
    {
        return self::isPublic($page) && ! in_array($page, self::NOT_INDEXED, true);
    }

    /**
     * Routy veřejných stránek pro sitemap.xml — jen indexované.
     *
     * @return list<string>
     */
    public static function indexedRoutes(): array
    {
        return array_keys(array_filter(self::PAGES, self::isIndexed(...)));
    }

    /**
     * Typ schema.org stránky z registru, nebo null pro jinou.
     */
    public static function schemaType(string $page): ?string
    {
        return self::isPublic($page) ? self::SCHEMA_TYPES[$page] ?? 'WebPage' : null;
    }
}
