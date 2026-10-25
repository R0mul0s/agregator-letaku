<?php

/**
 * Stránky prodejen pro vyhledávače (R172): `/akce/kaufland/trutnov` ukáže akce, které v prodejně
 * platí, má vlastní titulek, canonical a je v sitemap.xml; stránka obchodu na prodejny odkazuje;
 * stará adresa s jednou prodejnou v parametru přesměruje. Jen obchody se `store_pages` (Kaufland,
 * Globus) — COOP stránky prodejen nemá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\Store;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov']);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ1550', 'name' => 'Vrchlabí', 'city' => 'Vrchlabí']);
    Store::query()->create(['chain' => Chain::Coop, 'code' => 'jednota-kladno', 'name' => 'Jednota Kladno', 'city' => 'Kladno']);
});

/**
 * Akce Kauflandu, volitelně jen v zadaných prodejnách.
 *
 * @param  list<string>  $storeCodes
 */
function kauflandOfferIn(string $name, array $storeCodes = []): Offer
{
    $offer = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => $name]);
    foreach ($storeCodes as $code) {
        OfferStore::query()->create(['offer_id' => $offer->id, 'store_code' => $code]);
    }

    return $offer;
}

it('stránka prodejny ukáže akce, které v ní platí, s titulkem, nadpisem a canonical', function (): void {
    kauflandOfferIn('Máslo');
    kauflandOfferIn('Krkovice', ['CZ4400']);
    kauflandOfferIn('Losos', ['CZ1550']);

    $response = $this->get('/akce/kaufland/trutnov')->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Offers')
        ->where('heading', 'Akce Kauflandu Trutnov')
        ->where('storePage.name', 'Trutnov')
        ->where('storePage.chainUrl', '/akce/kaufland')
        ->where('storePage.restrictedCount', 1)
        ->where('filters.prodejny', ['CZ4400'])
        ->where('offers.total', 2));
    $html = $response->getContent();
    expect($html)->toContain('<title inertia>Kaufland Trutnov — akce z letáku tento týden · Slevohlídka</title>')
        ->and($html)->toContain('<link rel="canonical" href="'.url('/akce/kaufland/trutnov').'">')
        ->and(metaContent($html, 'robots'))->toBe('index, follow')
        ->and($html)->not->toContain('Losos');
});

it('neznámá prodejna a obchod bez stránek prodejen jsou 404, kanál RSS obchodu zůstává', function (): void {
    $this->get('/akce/kaufland/praha-neexistuje')->assertNotFound();
    $this->get('/akce/coop/jednota-kladno')->assertNotFound();
    $this->get('/akce/lidl/trutnov')->assertNotFound();
    $this->get('/akce/kaufland/rss')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
});

it('jedna prodejna v parametru přesměruje na stránku prodejny, víc prodejen ne', function (): void {
    $this->get('/akce/kaufland?prodejny=CZ4400')->assertRedirect('/akce/kaufland/trutnov')->assertStatus(301);
    $this->get('/akce?chain=kaufland&prodejny=CZ4400')->assertRedirect('/akce/kaufland/trutnov');
    $this->get('/akce/kaufland?prodejny=CZ4400,CZ1550')->assertOk();
});

it('stránka prodejny s hledáním se neindexuje', function (): void {
    kauflandOfferIn('Máslo');

    expect(metaContent($this->get('/akce/kaufland/trutnov?q=maslo')->getContent(), 'robots'))->toBe('noindex, follow');
});

it('stránka obchodu odkazuje na prodejny a ty jsou v sitemap.xml', function (): void {
    kauflandOfferIn('Máslo');

    $this->get('/akce/kaufland')->assertInertia(fn (Assert $page) => $page
        ->where('chainStores', [
            ['name' => 'Trutnov', 'url' => '/akce/kaufland/trutnov'],
            ['name' => 'Vrchlabí', 'url' => '/akce/kaufland/vrchlabi'],
        ]));
    // Všechny akce ani stránka prodejny seznam prodejen nemají
    $this->get('/akce')->assertInertia(fn (Assert $page) => $page->where('chainStores', []));

    $this->get('/sitemap.xml')
        ->assertSee(url('/akce/kaufland/trutnov'), false)
        ->assertSee(url('/akce/kaufland/vrchlabi'), false)
        ->assertDontSee('jednota-kladno', false);
});

it('stejný název prodejny dostane v adrese kód prodejny', function (): void {
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ9900', 'name' => 'Trutnov', 'city' => 'Trutnov']);
    kauflandOfferIn('Máslo');

    $this->get('/akce/kaufland/trutnov')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.prodejny', ['CZ4400']));
    $this->get('/akce/kaufland/trutnov-cz9900')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.prodejny', ['CZ9900']));
});

it('stránkování na stránce prodejny zůstane na její adrese', function (): void {
    config(['letaky.offers.per_page' => 1]);
    kauflandOfferIn('Máslo');
    kauflandOfferIn('Mléko');

    $this->get('/akce/kaufland/trutnov')->assertInertia(fn (Assert $page) => $page
        ->where('pagination.nextUrl', '/akce/kaufland/trutnov?strana=2'));
});
