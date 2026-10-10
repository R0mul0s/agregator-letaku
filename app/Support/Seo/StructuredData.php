<?php

/**
 * Strukturovaná data schema.org pro vyhledávače (R99) — jeden graf (`@graph`) na stránku:
 * provozovatel (Organization), web s hledáním (WebSite), stránka (WebPage / CollectionPage /
 * ContactPage), drobečková navigace (BreadcrumbList), akce na stránce jako nabídky obchodů
 * (ItemList z Offer) a časté otázky kontaktu (FAQPage).
 *
 * Uzly se odkazují přes `@id` (adresa + kotva), aby je vyhledávač spojil v jeden celek.
 * Akce nejsou Product na stránce produktu — výpis „Pivo v akci“ je kategorie, ne jeden výrobek,
 * a Google značku Product na výpisech nepovoluje. Ani `itemOffered` typu Product v nabídce ne
 * (R111): Google ho čte jako samostatný produktový úryvek a hlásí „Je třeba zadat buď offers,
 * review, nebo aggregateRating“ — název a obrázek nese přímo Offer. Aplikaci (WebApplication) neznačíme:
 * Google ji bez hodnocení uživatelů hlásí jako chybu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPages;
use App\Enums\Chain;
use App\Support\Operator;

final class StructuredData
{
    private const CONTEXT = 'https://schema.org';

    private const CURRENCY = 'CZK';

    /** Logo pro Organization (Google chce aspoň 112 × 112 px). */
    private const LOGO_PATH = 'images/brand/icon-512.png';

    /** Kód země pro sídlo a oblast služby. */
    private const COUNTRY = 'CZ';

    /** Haléřů v koruně — ceny jsou v haléřích (R7), schema.org chce Kč s desetinnou tečkou. */
    private const HALER_PER_CROWN = 100;

    /** Kódy UN/CEFACT pro cenu za jednotku (PackageUnit::unitPriceKey). */
    private const UNIT_CODES = ['kg' => 'KGM', 'l' => 'LTR', 'ks' => 'H87'];

    /** PSČ a obec z druhého řádku adresy („503 03 Smiřice“). */
    private const POSTAL_LINE_PATTERN = '/^(\d{3}\s?\d{2})\s+(.+)$/u';

    /** Typ stránky schema.org výpisu akcí (SeoMeta::page); ostatní stránky z PublicPages. */
    private const PAGE_TYPES = [
        'offers' => 'CollectionPage',
        'offers_chain' => 'CollectionPage',
        'offers_product' => 'CollectionPage',
        'weekly' => 'CollectionPage',
    ];

    public function __construct(
        private readonly OfferPages $pages,
        private readonly Operator $operator,
    ) {}

    /**
     * Graf schema.org stránky. `$props` jsou props Inertie (akce výpisu, nejvyšší slevy úvodní
     * stránky); `$heading` je nadpis stránky pro poslední článek drobečkové navigace.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function graph(string $page, string $title, string $heading, string $description, string $canonical, ?Chain $chain, ?int $productId, array $props): array
    {
        $home = SeoMeta::homeUrl();
        $breadcrumb = $this->breadcrumb($page, $heading, $canonical, $chain, $productId);
        $offers = $this->offerList($page, $canonical, $props);
        $faq = $page === 'contact' ? $this->faq($canonical) : null;

        $webPage = array_filter([
            '@type' => self::PAGE_TYPES[$page] ?? PublicPages::schemaType($page) ?? 'WebPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'inLanguage' => $this->language(),
            'isPartOf' => ['@id' => $home.'#website'],
            'about' => $page === 'home' ? ['@id' => $home.'#organization'] : null,
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => asset(SeoMeta::OG_IMAGE_PATH)],
            'breadcrumb' => $breadcrumb === null ? null : ['@id' => $breadcrumb['@id']],
            'mainEntity' => $offers === null ? null : ['@id' => $offers['@id']],
        ], fn (mixed $value): bool => $value !== null);

        return [
            '@context' => self::CONTEXT,
            '@graph' => array_values(array_filter([
                $this->organization($home),
                $this->website($home),
                $webPage,
                $breadcrumb,
                $offers,
                $faq,
            ])),
        ];
    }

    /**
     * Provozovatel: značka Slevohlídka s logem, kontaktem a sídlem provozovatele (R51).
     *
     * @return array<string, mixed>
     */
    private function organization(string $home): array
    {
        $email = config('letaky.operator.email');
        $phone = $this->operator->phoneHref();

        return array_filter([
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => __('app.ui.app_name'),
            'alternateName' => __('app.ui.brand.tagline'),
            'url' => $home,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset(self::LOGO_PATH),
            ],
            'image' => asset(SeoMeta::OG_IMAGE_PATH),
            'description' => __('app.seo.organization_description'),
            'email' => $email,
            'telephone' => $phone,
            'address' => $this->address(),
            'founder' => ['@type' => 'Person', 'name' => config('letaky.operator.name')],
            'areaServed' => ['@type' => 'Country', 'name' => self::COUNTRY],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $email,
                'telephone' => $phone,
                'url' => route('contact'),
                'availableLanguage' => $this->language(),
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Sídlo provozovatele jako PostalAddress; druhý řádek adresy je „PSČ obec“.
     *
     * @return array<string, string>|null
     */
    private function address(): ?array
    {
        $lines = $this->operator->addressLines();
        if ($lines === []) {
            return null;
        }

        $address = ['@type' => 'PostalAddress', 'streetAddress' => $lines[0], 'addressCountry' => self::COUNTRY];
        if (isset($lines[1]) && preg_match(self::POSTAL_LINE_PATTERN, $lines[1], $match) === 1) {
            $address['postalCode'] = $match[1];
            $address['addressLocality'] = $match[2];
        }

        return $address;
    }

    /**
     * Web s vyhledáváním v akcích. Název webu Google ukazuje ve výsledcích hledání.
     *
     * @return array<string, mixed>
     */
    private function website(string $home): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $home.'#website',
            'name' => __('app.ui.app_name'),
            'alternateName' => __('app.ui.brand.tagline'),
            'url' => $home,
            'inLanguage' => $this->language(),
            'publisher' => ['@id' => $home.'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('offers').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Drobečková navigace: Slevohlídka › Všechny akce › obchod nebo produkt; právní stránky,
     * kontakt a Nejlepší slevy týdne přímo pod úvodní stránkou. Úvodní stránka ji nemá.
     *
     * @return array<string, mixed>|null
     */
    private function breadcrumb(string $page, string $heading, string $canonical, ?Chain $chain, ?int $productId): ?array
    {
        if ($page === 'home' || $page === 'default') {
            return null;
        }

        $items = [[__('app.ui.app_name'), SeoMeta::homeUrl()]];
        if ($page === 'offers') {
            $items[] = [$heading, route('offers')];
        } elseif ($page === 'offers_chain' || $page === 'offers_product') {
            $items[] = [__('app.seo.pages.offers.heading'), route('offers')];
            $items[] = [$heading, $this->pages->url(new OfferFilters($chain === null ? [] : [$chain], $productId), absolute: true)];
        } else {
            $items[] = [$heading, $canonical];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $canonical.'#breadcrumb',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Akce na stránce jako nabídky obchodů — výpis akcí (aktuální stránka), nejvyšší slevy
     * na úvodní stránce a žebříček Nejlepších slev týdne (R128). Bez akcí null.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>|null
     */
    private function offerList(string $page, string $canonical, array $props): ?array
    {
        $offers = match (true) {
            $page === 'home' => $props['topOffers'] ?? [],
            // Nejlepší slevy týdne (R128): žebříček napříč obchody
            $page === 'weekly' => $props['top'] ?? [],
            str_starts_with($page, 'offers') => $props['offers']['data'] ?? [],
            default => [],
        };
        if (! is_array($offers) || $offers === []) {
            return null;
        }

        $elements = [];
        foreach (array_values($offers) as $index => $offer) {
            $element = is_array($offer) ? $this->offer($offer) : null;
            if ($element !== null) {
                $elements[] = ['@type' => 'ListItem', 'position' => $index + 1, 'item' => $element];
            }
        }

        return $elements === [] ? null : [
            '@type' => 'ItemList',
            '@id' => $canonical.'#offers',
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }

    /**
     * Jedna akce jako Offer (data z OfferPresenter::toPage): cena (bez běžné ceny cena s kartou),
     * platnost, obchod jako prodejce, odkaz na akci u obchodu, obrázek a cena za kilo, litr nebo kus
     * — bez `itemOffered` Product (R111).
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    private function offer(array $offer): ?array
    {
        $price = $offer['price'] ?? $offer['loyaltyPrice'] ?? null;
        if (! is_int($price)) {
            return null;
        }

        $withCard = $offer['price'] === null;
        $unitPrice = $withCard ? ($offer['loyaltyUnitPrice'] ?? null) : ($offer['unitPrice'] ?? null);
        $unitCode = self::UNIT_CODES[$offer['unitPriceUnit'] ?? ''] ?? null;

        return array_filter([
            '@type' => 'Offer',
            'name' => $offer['name'],
            'price' => $this->amount($price),
            'priceCurrency' => self::CURRENCY,
            'validFrom' => $offer['validFrom'],
            'priceValidUntil' => $offer['validTo'],
            'url' => $offer['sourceUrl'] ?? null,
            'description' => $withCard && is_string($offer['loyaltyProgramName'] ?? null)
                ? __('app.seo.content.with_card', ['program' => $offer['loyaltyProgramName']])
                : null,
            'image' => $offer['imageUrl'] ?? null,
            'seller' => ['@type' => 'Organization', 'name' => $offer['chainName']],
            'priceSpecification' => is_int($unitPrice) && $unitCode !== null ? [
                '@type' => 'UnitPriceSpecification',
                'price' => $this->amount($unitPrice),
                'priceCurrency' => self::CURRENCY,
                'referenceQuantity' => ['@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => $unitCode],
            ] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Časté otázky z kontaktu (stejné texty jako na stránce, lang ui.contact.faq).
     *
     * @return array<string, mixed>
     */
    private function faq(string $canonical): array
    {
        /** @var array<string, array{question: string, answer: string}> $items */
        $items = __('app.ui.contact.faq');

        return [
            '@type' => 'FAQPage',
            '@id' => $canonical.'#faq',
            'mainEntity' => array_values(array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $items)),
        ];
    }

    /**
     * Částka v Kč s desetinnou tečkou („9.90“) z haléřů — bez floatu (R7).
     */
    private function amount(int $halers): string
    {
        return sprintf('%d.%02d', intdiv($halers, self::HALER_PER_CROWN), $halers % self::HALER_PER_CROWN);
    }

    /**
     * Jazyk stránky ve tvaru BCP 47 („cs“).
     */
    private function language(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }
}
