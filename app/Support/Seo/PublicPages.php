<?php

/**
 * Registr veřejných stránek mimo výpis akcí (podmínky, zásady, kontakt) — z něj berou
 * hlavička pro vyhledávače (SeoMeta: index), schema.org (typ stránky), sitemap.xml, llms.txt
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
     * Typ schema.org stránky z registru, nebo null pro jinou.
     */
    public static function schemaType(string $page): ?string
    {
        return self::isPublic($page) ? self::SCHEMA_TYPES[$page] ?? 'WebPage' : null;
    }
}
