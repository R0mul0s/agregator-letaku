<?php

/**
 * Odběr akcí přes RSS (R149): kanál obchodu a produktu katalogu, nejnovější akce první,
 * podmíněný dotaz 304, odkaz v hlavičce stránky a ve výpisu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-13 10:00:00');
});

/**
 * Kanál jako XML.
 */
function feedXml(string $url): SimpleXMLElement
{
    $content = (string) test()->get($url)->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')->getContent();

    return new SimpleXMLElement($content);
}

it('kanál obchodu nese jeho neskončené akce od nejnověji zveřejněných s cenou, slevou a platností', function (): void {
    Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Starší máslo', 'created_at' => CarbonImmutable::now()->subDay()]);
    Offer::factory()->create([
        'chain' => Chain::Lidl, 'name' => 'Nové pivo', 'source_url' => 'https://www.lidl.cz/p/pivo',
        'price' => 2990, 'original_price' => 3990, 'loyalty_price' => 2490, 'loyalty_program' => LoyaltyProgram::LidlPlus,
    ]);
    Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Skončená akce', 'valid_from' => CarbonImmutable::today()->subWeek(), 'valid_to' => CarbonImmutable::yesterday()]);
    Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Cizí obchod']);

    $xml = feedXml('/akce/lidl/rss');

    expect((string) $xml->channel->title)->toBe('Akce z letáku Lidlu · Slevohlídka')
        ->and((string) $xml->channel->link)->toBe(url('/akce/lidl'))
        ->and(array_map(fn (SimpleXMLElement $item): string => (string) $item->title, iterator_to_array($xml->channel->item, false)))
        ->toBe(["Nové pivo — 29,90\u{00A0}Kč", "Starší máslo — 29,90\u{00A0}Kč"]);

    $item = $xml->channel->item[0];
    expect((string) $item->link)->toBe('https://www.lidl.cz/p/pivo')
        ->and((string) $item->description)->toContain('sleva 25 %')
        ->and((string) $item->description)->toContain('s kartou')
        ->and((string) $item->description)->toContain("platí 13.\u{00A0}10. – 20.\u{00A0}10.")
        ->and((string) $xml->channel->item[1]->link)->toBe(url('/akce/lidl'));
});

it('kanál produktu nese akce všech obchodů s názvem obchodu', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    foreach ([Chain::Lidl, Chain::Kaufland] as $chain) {
        $offer = Offer::factory()->create(['chain' => $chain, 'name' => 'Pivo '.$chain->label()]);
        OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    }
    Offer::factory()->create(['name' => 'Bez produktu']);

    $xml = feedXml('/akce/pivo/rss');

    expect((string) $xml->channel->title)->toBe('Pivo v akci · Slevohlídka')
        ->and($xml->channel->item)->toHaveCount(2)
        ->and((string) $xml->channel->item[0]->title)->toEndWith(')');
});

it('neznámá stránka kanál nemá a na podmíněný dotaz bez změny odpoví 304', function (): void {
    $this->get('/akce/neexistuje/rss')->assertNotFound();

    Offer::factory()->create(['chain' => Chain::Lidl]);
    $response = $this->get('/akce/lidl/rss')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $lastModified = (string) $response->headers->get('Last-Modified');

    $this->get('/akce/lidl/rss', ['If-Modified-Since' => $lastModified])->assertStatus(304);
});

it('kanál nezakládá relaci', function (): void {
    config(['session.driver' => 'database']);

    $this->get('/akce/lidl/rss')->assertOk();

    expect(DB::table('sessions')->count())->toBe(0);
});

it('stránka obchodu a produktu odkazuje na kanál v hlavičce i ve výpisu, Všechny akce ne', function (): void {
    Product::factory()->create(['name' => 'Pivo']);

    expect($this->get('/akce/lidl')->getContent())->toContain('<link rel="alternate" type="application/rss+xml" title="Akce z letáku Lidlu · Slevohlídka" href="'.url('/akce/lidl/rss').'">')
        ->and($this->get('/akce/pivo')->getContent())->toContain('href="'.url('/akce/pivo/rss').'"')
        ->and($this->get('/akce')->getContent())->not->toContain('application/rss+xml');

    $this->get('/akce/lidl')->assertInertia(fn (Assert $page) => $page->where('feedUrl', '/akce/lidl/rss'));
    $this->get('/akce')->assertInertia(fn (Assert $page) => $page->where('feedUrl', null));
});
