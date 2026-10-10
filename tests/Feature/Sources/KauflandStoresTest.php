<?php

/**
 * Kaufland po prodejnách (R49): seznam prodejen a jejich akcí, stránky prodejen s akcemi, které
 * ve výchozí nabídce chybí, prodejny akce a filtrování Mých slev podle vybraných prodejen.
 *
 * Fixtures z 3. 10. 2026: výchozí nabídka (CZ3300 Praha-Vypich) má vejce (všude), lososa
 * (rybí pult jen v CZ3300) a vepřovou pečeni (CZ3300 a Vrchlabí); Trutnov má vejce, krkovici
 * a čevapčiči, které ve výchozí nabídce nejsou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const KAUFLAND_STORE_CRON_TOKEN = 'test-cron-token';

/** Klíče nabídek z fixtures („klNr|od|do“). */
const KAUFLAND_EGGS = '00153062';
const KAUFLAND_SALMON = '04500157';
const KAUFLAND_PORK_ROAST = '63000139';
const KAUFLAND_NECK = '63000144';
const KAUFLAND_CEVAPCICI = '63015185';

/**
 * Falešné odpovědi Kauflandu: seznam prodejen, akce prodejen a stránky nabídky — výchozí
 * a Trutnova (cookie x-aem-variant=CZ4400).
 */
function fakeKauflandStores(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '.klstorefinder.json') => Http::response(responseFixture('kaufland/stores-2026-10-03.json')),
            preg_match('/storeName=(CZ\d+)\.json/', $url, $matches) === 1 => Http::response(responseFixture("kaufland/store-offers-{$matches[1]}-2026-10-03.json")),
            str_contains($url, '/nabidka/prehled.html') && str_contains($request->header('Cookie')[0] ?? '', 'CZ4400') => Http::response(responseFixture('kaufland/prehled-CZ4400-2026-10-03.html')),
            str_contains($url, '/nabidka/prehled.html') => Http::response(responseFixture('kaufland/prehled-default-2026-10-03.html')),
            default => Http::response('Not found', 404),
        };
    });
}

/**
 * Kódy prodejen, kde akce platí (prázdné = všude).
 *
 * @return list<string>
 */
function kauflandOfferStores(string $klNr): array
{
    return Offer::query()->where('chain', Chain::Kaufland)->where('external_id', $klNr)->sole()
        ->stores()->orderBy('store_code')->pluck('store_code')->all();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-03 10:00:00');
    config(['letaky.cron.token' => KAUFLAND_STORE_CRON_TOKEN]);
});

it('uloží prodejny bez názvu obchodu a seznam jejich akcí; prodejnu, která zmizela, smaže', function (): void {
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ9999', 'name' => 'Zavřená', 'city' => 'Nikde']);
    fakeKauflandStores();

    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']])->assertSuccessful();

    $trutnov = Store::query()->where('code', 'CZ4400')->sole();
    expect(Store::query()->where('chain', Chain::Kaufland)->orderBy('code')->pluck('name', 'code')->all())->toBe(['CZ1550' => 'Vrchlabí', 'CZ3300' => 'Praha-Vypich', 'CZ4400' => 'Trutnov'])
        ->and($trutnov->city)->toBe('Trutnov')
        ->and($trutnov->offer_keys)->toContain(KAUFLAND_NECK.'|2026-09-30|2026-10-06')
        ->and($trutnov->offer_keys_fetched_at?->toDateTimeString())->toBe('2026-10-03 10:00:00');
});

it('zavřenou prodejnu odebere z výběru uživatelů, bez zbylé prodejny výběr zanikne (R113)', function (): void {
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ9999', 'name' => 'Zavřená', 'city' => 'Nikde']);
    $both = FollowedChain::query()->create(['user_id' => User::factory()->create()->id, 'chain' => Chain::Kaufland, 'store_codes' => ['CZ4400', 'CZ9999']]);
    $closedOnly = FollowedChain::query()->create(['user_id' => User::factory()->create()->id, 'chain' => Chain::Kaufland, 'store_codes' => ['CZ9999']]);
    fakeKauflandStores();

    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']])->assertSuccessful();

    expect($both->fresh()?->store_codes)->toBe(['CZ4400'])
        ->and($closedOnly->fresh()?->store_codes)->toBeNull();
});

it('cron URL prodejen stáhne prodejny; obchod bez prodejen odmítne', function (): void {
    fakeKauflandStores();

    $this->get(route('cron.import-stores', ['chain' => 'kaufland', 'token' => KAUFLAND_STORE_CRON_TOKEN]))
        ->assertOk()
        ->assertSee('Kaufland — prodejen: 3, seznamů akcí: 3');
    $this->getJson(route('cron.import-stores', ['chain' => 'tesco', 'token' => KAUFLAND_STORE_CRON_TOKEN]))->assertUnprocessable();
});

it('stáhne stránku prodejny s akcemi, které ve výchozí nabídce chybí, a u akcí uloží prodejny', function (): void {
    fakeKauflandStores();
    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    // Stránky nabídky: výchozí a Trutnov, který jediný má krkovici a čevapčiči
    $pages = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/nabidka/prehled.html'))->values();
    expect($pages)->toHaveCount(2)
        ->and($pages[1][0]->header('Cookie'))->toBe(['x-aem-variant=CZ4400'])
        ->and(Offer::query()->where('chain', Chain::Kaufland)->count())->toBe(5)
        ->and(kauflandOfferStores(KAUFLAND_EGGS))->toBe([])
        ->and(kauflandOfferStores(KAUFLAND_SALMON))->toBe(['CZ3300'])
        ->and(kauflandOfferStores(KAUFLAND_PORK_ROAST))->toBe(['CZ1550', 'CZ3300'])
        ->and(kauflandOfferStores(KAUFLAND_NECK))->toBe(['CZ4400'])
        ->and(kauflandOfferStores(KAUFLAND_CEVAPCICI))->toBe(['CZ4400']);
});

it('se starými seznamy akcí prodejen stáhne jen výchozí nabídku a prodejny akcím neurčí', function (): void {
    fakeKauflandStores();
    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']]);
    $this->travelTo('2026-10-05 10:00:00');
    Http::fake(['https://prodejny.kaufland.cz/nabidka/prehled.html*' => Http::response(responseFixture('kaufland/prehled-default-2026-10-03.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    Http::assertSentCount(1);
    expect(kauflandOfferStores(KAUFLAND_SALMON))->toBe([]);
});

it('položku bez názvu z výchozí stránky na stránkách prodejen nehledá', function (): void {
    $this->travelTo('2026-10-06 10:00:00');
    // Seznam prodejny zná i položku bez názvu (20963057), kterou parser přeskočí
    Store::query()->create([
        'chain' => Chain::Kaufland, 'code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov',
        'offer_keys' => ['00022696|2026-09-30|2026-10-06', '20963057|2026-09-30|2026-10-06', '00021062|2026-10-07|2026-10-13'],
        'offer_keys_fetched_at' => now(),
    ]);
    Http::fake(['https://prodejny.kaufland.cz/nabidka/prehled.html*' => Http::response(responseFixture('kaufland/prehled-2026-10-06.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    Http::assertSentCount(1);
});

it('Moje slevy ukážou jen akce vybraných prodejen a u akce prodejny, kde platí', function (): void {
    fakeKauflandStores();
    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']]);
    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);
    $user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true, 'store_codes' => ['CZ4400', 'CZ1550']]);
    WatchItem::factory()->for($user)->create(['name' => 'Vepřové', 'keywords' => 'vepřov']);
    WatchItem::factory()->for($user)->create(['name' => 'Losos', 'keywords' => 'losos']);

    $storesUrl = null;
    $this->actingAs($user)->get(route('home'))->assertInertia(function (Assert $page) use (&$storesUrl): void {
        $groups = collect($page->toArray()['props']['watchItems'])->keyBy('name');
        $pork = collect($groups['Vepřové']['offers'])->keyBy('name');
        $storesUrl = $pork['K-Mistři od fochu Vepřová krkovice bez kosti pultový prodej']['stores']['url'];

        // Losos je jen v Praze-Vypichu, kterou uživatel nemá
        expect($groups['Losos']['offers'])->toBe([])
            ->and($pork->keys()->sort()->values()->all())->toBe([
                'K-Mistři od fochu Vepřová krkovice bez kosti pultový prodej',
                'K-Mistři od fochu Vepřová pečeně bez kosti pultový prodej',
                'K-Mistři od fochu Vepřové čevapčiči',
            ])
            ->and($pork['K-Mistři od fochu Vepřová krkovice bez kosti pultový prodej']['stores'])->toMatchArray(['names' => ['Trutnov'], 'count' => 1, 'elsewhere' => false])
            ->and($pork['K-Mistři od fochu Vepřová pečeně bez kosti pultový prodej']['stores'])->toMatchArray(['names' => ['Vrchlabí'], 'count' => 2, 'elsewhere' => false]);
    });

    // Seznam prodejen pro okno se načte až po otevření (R106) — vybraná prodejna je označená
    $this->getJson((string) $storesUrl)->assertOk()->assertExactJson(['stores' => [['name' => 'Trutnov', 'selected' => true]]]);
});

it('Všechny akce u akce jen v některých prodejnách ukážou kde platí', function (): void {
    fakeKauflandStores();
    $this->artisan('letaky:import-stores', ['chain' => ['kaufland']]);
    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    $this->get(route('offers', ['q' => 'losos']))->assertInertia(fn (Assert $page) => $page
        ->where('offers.data.0.stores.names', ['Praha-Vypich'])
        ->where('offers.data.0.stores.count', 1)
        ->where('offers.data.0.stores.elsewhere', false));
    $this->get(route('offers', ['q' => 'vejce']))->assertInertia(fn (Assert $page) => $page
        ->where('offers.data.0.stores', null));

    // Přihlášený s Trutnovem: losos v jeho prodejně není — podle Mých obchodů schovaný (R100),
    // po vypnutí s „jinde“
    $user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true, 'store_codes' => ['CZ4400']]);
    $this->actingAs($user)->get(route('offers', ['q' => 'losos']))->assertInertia(fn (Assert $page) => $page
        ->where('offers.total', 0)
        ->where('shoppingPreferences.hidden', 1));
    $this->get(route('offers', ['q' => 'losos', 'moje-obchody' => 0]))->assertInertia(fn (Assert $page) => $page
        ->where('filters.moje-obchody', false)
        ->where('offers.data.0.stores.names', [])
        ->where('offers.data.0.stores.elsewhere', true));
});
