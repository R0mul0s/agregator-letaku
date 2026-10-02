<?php

/**
 * Přehled všech aktuálních akcí s hledáním.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // 22:30 UTC je už 3. 10. místního času — „dnes“ musí být místní den
    $this->travelTo('2026-10-02T22:30:00Z');
    $this->actingAs(User::factory()->create());
});

/**
 * Názvy nabídek na stránce v pořadí zobrazení.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function offerNames(array $query = []): array
{
    $names = [];
    test()->get(route('offers', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names): void {
            $page->component('Offers');
            $names = array_column($page->toArray()['props']['offers']['data'], 'name');
        });

    return $names;
}

it('je veřejná: nepřihlášený vidí akce a v navigaci jen Všechny akce (R44)', function (): void {
    auth()->logout();
    Offer::factory()->create(['name' => 'Vejce M']);

    $this->get(route('offers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.data.0.name', 'Vejce M')
            ->where('auth.user', null)
            ->where('auth.registerUrl', '/register')
            ->where('navigation', [['url' => '/akce', 'label' => 'nav.offers', 'active' => true]]));
    $this->get(route('offers.suggestions', ['q' => 'vej']))->assertOk();
});

it('ukáže platné i budoucí akce, skončené a stažené obchodem ne', function (): void {
    $today = CarbonImmutable::parse('2026-10-03');
    Offer::factory()->create(['name' => 'Platí dnes', 'valid_from' => $today->subDays(3), 'valid_to' => $today]);
    Offer::factory()->create(['name' => 'Příští týden', 'valid_from' => $today->addDays(4), 'valid_to' => $today->addDays(10)]);
    Offer::factory()->create(['name' => 'Skončila včera', 'valid_from' => $today->subWeek(), 'valid_to' => $today->subDay()]);
    Offer::factory()->create(['name' => 'Stažená obchodem', 'withdrawn_at' => CarbonImmutable::now()]);

    expect(offerNames())->toBe(['Platí dnes', 'Příští týden']);
});

it('hledá bez ohledu na diakritiku a velikost písmen, v názvu i popisu', function (): void {
    Offer::factory()->create(['name' => 'Kunín Trvanlivé mléko tuk 1,5 %', 'description' => 'Kunín polotučné tuk 1,5 %']);
    Offer::factory()->create(['name' => 'Mléčná čokoláda']);
    Offer::factory()->create(['name' => 'Čerstvá vejce M20']);

    expect(offerNames(['q' => 'mleko']))->toBe(['Kunín Trvanlivé mléko tuk 1,5 %'])
        ->and(offerNames(['q' => 'POLOTUČNÉ mléko']))->toBe(['Kunín Trvanlivé mléko tuk 1,5 %'])
        ->and(offerNames(['q' => 'vejce']))->toBe(['Čerstvá vejce M20']);
});

it('znaky % a _ v hledání bere doslova', function (): void {
    Offer::factory()->create(['name' => 'Smetana 33% tuku']);
    Offer::factory()->create(['name' => 'Smetana 12 tuku']);

    expect(offerNames(['q' => '33%']))->toBe(['Smetana 33% tuku']);
});

it('filtruje podle obchodu', function (): void {
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);

    expect(offerNames(['chain' => 'tesco']))->toBe(['Vejce Tesco']);
});

it('odmítne neznámý obchod', function (): void {
    $this->get(route('offers', ['chain' => 'makro']))->assertSessionHasErrors('chain');
});

it('pošle ceny v haléřích, cenu za jednotku a názvy z lang', function (): void {
    Offer::factory()->create([
        'chain' => Chain::Tesco,
        'name' => 'Tesco Mléko UHT polotučné 1,5% 1l',
        'offer_type' => OfferType::LoyaltyOnly,
        'price' => 2190,
        'original_price' => null,
        'discount_percent' => null,
        'loyalty_price' => 890,
        'loyalty_program' => LoyaltyProgram::Clubcard,
        'quantity' => 1000,
        'unit' => PackageUnit::Milliliter,
    ]);

    $this->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.total', 1)
            ->where('offers.data.0.chainName', 'Tesco')
            ->where('offers.data.0.offerType', 'loyalty_only')
            ->where('offers.data.0.price', 2190)
            ->where('offers.data.0.loyaltyPrice', 890)
            ->where('offers.data.0.loyaltyProgramName', 'Clubcard')
            ->where('offers.data.0.unitPrice', 2190)
            ->where('offers.data.0.loyaltyUnitPrice', 890)
            ->where('offers.data.0.unitPriceUnit', 'l')
            ->where('chains.1', ['value' => 'tesco', 'name' => 'Tesco']));
});

it('stránkuje podle konfigurace a zachová hledání v odkazech', function (): void {
    config(['letaky.offers.per_page' => 2]);
    Offer::factory()->count(3)->create(['name' => 'Vejce']);

    $this->get(route('offers', ['q' => 'vejce']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers.data', 2)
            ->where('pagination.lastPage', 2)
            ->where('pagination.nextUrl', '/akce?q=vejce&strana=2')
            ->where('pagination.loadMoreUrl', '/akce?q=vejce&od=1&strana=2')
            ->where('pagination.loadMoreCount', 1)
            ->where('pagination.previousUrl', null));
});

it('„Načíst další“ načte celý rozsah stránek z adresy a označí ho (R43)', function (): void {
    config(['letaky.offers.per_page' => 2]);
    Offer::factory()->count(9)->create();

    $this->get('/akce?od=2&strana=3')
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers.data', 4)
            ->where('pagination.from', 2)
            ->where('pagination.to', 3)
            ->where('pagination.shownFrom', 3)
            ->where('pagination.shownTo', 6)
            ->where('pagination.previousUrl', '/akce')
            ->where('pagination.loadMoreUrl', '/akce?od=2&strana=4')
            ->where('pagination.pages', [
                ['number' => 1, 'url' => '/akce', 'current' => false],
                ['number' => 2, 'url' => '/akce?strana=2', 'current' => true],
                ['number' => 3, 'url' => '/akce?strana=3', 'current' => true],
                ['number' => 4, 'url' => '/akce?strana=4', 'current' => false],
                ['number' => 5, 'url' => '/akce?strana=5', 'current' => false],
            ]));
});

it('stránku za koncem výpisu zkrátí na poslední a rozsah omezí stropem (R43)', function (): void {
    config(['letaky.offers.per_page' => 2, 'letaky.pagination.max_loaded_pages' => 2]);
    Offer::factory()->count(9)->create();

    $this->get('/akce?strana=99')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.to', 5)
            ->where('pagination.loadMoreUrl', null)
            ->has('offers.data', 1));

    $this->get('/akce?od=1&strana=4')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.from', 3)
            ->has('offers.data', 4));
});
