<?php

/**
 * Strukturovaná data schema.org (R99): jeden graf na indexované stránce — organizace, web,
 * stránka, drobečková navigace, akce jako nabídky obchodů a časté otázky kontaktu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\Product;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Uzly grafu schema.org ze stránky; každý blok ld+json musí být platný JSON.
 *
 * @return list<array<string, mixed>>
 */
function structuredNodes(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches[1])->toHaveCount(1);
    $data = json_decode($matches[1][0], true, flags: JSON_THROW_ON_ERROR);
    expect($data['@context'])->toBe('https://schema.org');

    return $data['@graph'];
}

/**
 * První uzel daného typu, nebo null.
 *
 * @param  list<array<string, mixed>>  $nodes
 * @return array<string, mixed>|null
 */
function structuredNode(array $nodes, string $type): ?array
{
    foreach ($nodes as $node) {
        if ($node['@type'] === $type) {
            return $node;
        }
    }

    return null;
}

it('úvodní stránka: organizace s kontaktem a sídlem, web s hledáním a stránka bez drobečkové navigace', function (): void {
    $nodes = structuredNodes((string) $this->get('/')->getContent());
    $organization = structuredNode($nodes, 'Organization');

    expect($organization['@id'])->toBe(url('/').'/#organization')
        ->and($organization['contactPoint']['email'])->toBe(config('letaky.operator.email'))
        ->and($organization['contactPoint']['telephone'])->toBe('+420736449607')
        ->and($organization['address'])->toMatchArray(['streetAddress' => 'Rodov 133', 'postalCode' => '503 03', 'addressLocality' => 'Smiřice'])
        ->and(structuredNode($nodes, 'WebSite')['potentialAction']['target']['urlTemplate'])->toBe(route('offers').'?q={search_term_string}')
        ->and(structuredNode($nodes, 'WebPage')['about'])->toBe(['@id' => url('/').'/#organization'])
        ->and(structuredNode($nodes, 'BreadcrumbList'))->toBeNull();
});

it('stránka produktu: drobečková navigace přes Všechny akce a akce jako nabídky s cenou za litr', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    Offer::factory()->create([
        'chain' => Chain::Lidl,
        'name' => 'Braník 0,5 l',
        'brand' => 'Braník',
        'price' => 1990,
        'quantity' => 500,
        'unit' => PackageUnit::Milliliter,
        'valid_from' => '2026-10-01',
        'valid_to' => '2026-10-07',
    ])->productAssignments()->create(['product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    $nodes = structuredNodes((string) $this->get('/akce/pivo')->getContent());
    $breadcrumb = structuredNode($nodes, 'BreadcrumbList');
    $offer = structuredNode($nodes, 'ItemList')['itemListElement'][0]['item'];

    expect(structuredNode($nodes, 'CollectionPage')['mainEntity'])->toBe(['@id' => url('/akce/pivo').'#offers'])
        ->and(array_column($breadcrumb['itemListElement'], 'name'))->toBe(['Slevohlídka', 'Všechny akce z letáků', 'Pivo v akci'])
        ->and(array_column($breadcrumb['itemListElement'], 'item'))->toBe([url('/').'/', route('offers'), url('/akce/pivo')])
        ->and($offer)->toMatchArray([
            '@type' => 'Offer',
            'price' => '19.90',
            'priceCurrency' => 'CZK',
            'validFrom' => '2026-10-01',
            'priceValidUntil' => '2026-10-07',
            'seller' => ['@type' => 'Organization', 'name' => 'Lidl'],
        ])
        ->and($offer['itemOffered']['brand']['name'])->toBe('Braník')
        ->and($offer['priceSpecification'])->toMatchArray(['price' => '39.80', 'referenceQuantity' => ['@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'LTR']]);
});

it('akce jen s cenou s kartou má v nabídce cenu s kartou a program v popisu', function (): void {
    Offer::factory()->create(['chain' => Chain::Lidl, 'price' => null, 'loyalty_price' => 2490, 'loyalty_program' => 'lidl_plus']);

    $offer = structuredNode(structuredNodes((string) $this->get('/akce/lidl')->getContent()), 'ItemList')['itemListElement'][0]['item'];

    expect($offer['price'])->toBe('24.90')
        ->and($offer['description'])->toContain('Lidl Plus');
});

it('kontakt: stránka kontaktu a časté otázky se stejnými texty jako na stránce', function (): void {
    $nodes = structuredNodes((string) $this->get('/kontakt')->getContent());
    $faq = structuredNode($nodes, 'FAQPage');

    expect(structuredNode($nodes, 'ContactPage'))->not->toBeNull()
        ->and($faq['mainEntity'])->toHaveCount(count(__('app.ui.contact.faq')))
        ->and($faq['mainEntity'][0]['name'])->toBe(__('app.ui.contact.faq.free.question'))
        ->and(array_column(structuredNode($nodes, 'BreadcrumbList')['itemListElement'], 'name'))->toBe(['Slevohlídka', 'Kontakt']);
});

it('neindexované stránky strukturovaná data nemají', function (): void {
    expect($this->get('/prihlaseni')->getContent())->not->toContain('application/ld+json')
        ->and($this->get('/akce?q=pivo')->getContent())->not->toContain('application/ld+json');
});
