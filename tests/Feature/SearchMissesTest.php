<?php

/**
 * Hledání bez výsledků (R171): Všechny akce a Hlídám započítají text, který nic nenašel —
 * souhrnně, jeden člověk nejvýš jednou za den; admin ho vidí v katalogu se sloučenými
 * začátky slova a může ho skrýt; úklid ho po době uchování smaže.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Account\Actions\RunMaintenance;
use App\Domain\Catalog\SearchMisses;
use App\Enums\SearchMissSource;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\WatchItemController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Offer;
use App\Models\SearchMiss;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
});

/**
 * Hlavičky hledání z aplikace (požadavek Inertie) — jen ta se počítají.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];
}

it('hledání ve Všech akcích, které nic nenašlo, započítá; co se našlo, ne', function (): void {
    Offer::factory()->create(['name' => 'Máslo 250 g']);

    $this->withHeaders(inertiaHeaders())->get('/akce?q=Kombucha')->assertOk();
    $this->withHeaders(inertiaHeaders())->get('/akce?q=kombucha')->assertOk();
    // Našlo se — nezapočítá se
    $this->withHeaders(inertiaHeaders())->get('/akce?q=máslo')->assertOk();

    expect(SearchMiss::query()->sole())
        ->source->toBe(SearchMissSource::Offers)
        ->term->toBe('kombucha')
        ->text->toBe('kombucha');
});

it('jednoho člověka započítá u stejného textu jednou za den', function (): void {
    $misses = app(SearchMisses::class);
    $misses->record(SearchMissSource::Offers, 'Kombucha', 'relace-a');
    $misses->record(SearchMissSource::Offers, 'kombucha', 'relace-a');
    $misses->record(SearchMissSource::Offers, 'kombucha', 'relace-b');
    $this->travel(1)->day();
    $misses->record(SearchMissSource::Offers, 'kombucha', 'relace-a');

    expect(SearchMiss::query()->sole()->hits)->toBe(3);
});

it('nepočítá odkaz bez aplikace (robot, vyhledávač), filtr, který akce schoval, ani opravený překlep', function (): void {
    Offer::factory()->create(['name' => 'Máslo 250 g', 'chain' => 'lidl']);

    // Celé načtení stránky (odkaz z vyhledávače)
    $this->get('/akce?q=kombucha')->assertOk();
    // V Kauflandu nic, jinde ano — to není „nic jsme nenašli“
    $this->withHeaders(inertiaHeaders())->get('/akce?q=maslo&chain=kaufland')->assertOk();
    // Překlep, který oprava najde
    $this->withHeaders(inertiaHeaders())->get('/akce?q=masl0')->assertOk();

    expect(SearchMiss::query()->count())->toBe(0);
});

it('Hlídám započítá vlastní slova bez produktu katalogu a bez akce', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson(route('watch-items.preview', ['keywords' => 'kombucha', WatchItemController::MISS_FIELD => '1']))->assertOk();
    // Produkt katalogu se našel (prohlížeč příznak neposlal)
    $this->getJson(route('watch-items.preview', ['keywords' => 'kefir']))->assertOk();

    expect(SearchMiss::query()->sole())
        ->source->toBe(SearchMissSource::Watch)
        ->term->toBe('kombucha');
});

it('přehled sloučí začátky slova při psaní, sečte zdroje a ukáže jen od dvou hledání', function (): void {
    $misses = app(SearchMisses::class);
    // Dva lidé píšou „kombucha“ — výsledky se hledají po písmenech
    foreach (['relace-a', 'relace-b'] as $visitor) {
        foreach (['kombu', 'kombuch', 'kombucha'] as $text) {
            $misses->record(SearchMissSource::Offers, $text, $visitor);
        }
    }
    $misses->record(SearchMissSource::Watch, 'Kombucha', 'relace-c');
    // Jen jeden člověk — v přehledu není
    $misses->record(SearchMissSource::Offers, 'tempeh', 'relace-a');

    $shown = $misses->forAdmin();

    expect($shown)->toHaveCount(1)
        ->and($shown[0])->toMatchArray(['term' => 'kombucha', 'hits' => 3])
        ->and($shown[0]['sources'])->toEqualCanonicalizing([SearchMissSource::Offers, SearchMissSource::Watch]);
});

it('admin vidí hledání v katalogu, skryje ho i se začátky slova a další hledání ho nevrátí', function (): void {
    $misses = app(SearchMisses::class);
    foreach (['relace-a', 'relace-b'] as $visitor) {
        $misses->record(SearchMissSource::Offers, 'kombu', $visitor);
        $misses->record(SearchMissSource::Offers, 'Kombucha', $visitor);
    }
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('catalog.index'))->assertInertia(fn (Assert $page) => $page
        ->where('searchMisses.items.0.text', 'Kombucha')
        ->where('searchMisses.items.0.hits', 2)
        ->where('searchMisses.items.0.sources', ['Všechny akce']));

    $this->actingAs($admin)->delete(route('catalog.search-misses.dismiss'), ['term' => 'kombucha'])
        ->assertSessionHas('status', CatalogController::STATUS_SEARCH_MISS_DISMISSED);
    $misses->record(SearchMissSource::Offers, 'kombucha', 'relace-c');

    expect($misses->forAdmin())->toBe([])
        ->and(SearchMiss::query()->whereNull('dismissed_at')->count())->toBe(0);
});

it('běžný uživatel přehled skrýt nesmí', function (): void {
    $this->actingAs(User::factory()->create())
        ->delete(route('catalog.search-misses.dismiss'), ['term' => 'kombucha'])
        ->assertForbidden();
});

it('krátký text nezapíše a denní úklid smaže hledání starší než doba uchování', function (): void {
    $misses = app(SearchMisses::class);
    $misses->record(SearchMissSource::Offers, 'ko', 'relace-a');
    $misses->record(SearchMissSource::Offers, 'kombucha', 'relace-a');
    $this->travel(config()->integer('letaky.search_misses.retention_days') + 1)->days();
    $misses->record(SearchMissSource::Offers, 'tempeh', 'relace-a');

    expect(app(RunMaintenance::class)()['search_misses'])->toBe(1)
        ->and(SearchMiss::query()->pluck('term')->all())->toBe(['tempeh']);
});
