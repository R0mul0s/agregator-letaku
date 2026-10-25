<?php

/**
 * Vypnutý obchod (R155): `letaky.disabled_chains` z .env schová už uložené akce obchodu ze všech
 * výpisů a obchod se nestahuje — výzvě obchodu (O6) jde vyhovět bez nasazení kódu. Data zůstávají
 * a po zapnutí se zase ukážou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    $this->user = User::factory()->create();
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);
});

/**
 * Názvy akcí ve Všech akcích.
 *
 * @return list<string>
 */
function listedOfferNames(): array
{
    $names = [];
    test()->get(route('offers'))->assertOk()->assertInertia(function (Assert $page) use (&$names): void {
        $names = array_column($page->toArray()['props']['offers']['data'], 'name');
    });
    sort($names);

    return $names;
}

it('vypnutý obchod schová z Všech akcí, filtru i patičky a jeho stránka je 404', function (): void {
    expect(listedOfferNames())->toBe(['Vejce Kaufland', 'Vejce Tesco']);

    config(['letaky.disabled_chains' => ['tesco']]);

    expect(listedOfferNames())->toBe(['Vejce Kaufland']);
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('chains', fn ($chains): bool => ! collect($chains)->contains('value', 'tesco'))
        ->where('siteFooter.chains', fn ($chains): bool => ! collect($chains)->contains('chain', 'tesco')));
    $this->get('/akce/tesco')->assertNotFound();
    $this->get('/akce/kaufland')->assertOk();

    // Po zapnutí jsou akce zpátky — v databázi zůstaly
    config(['letaky.disabled_chains' => []]);
    expect(listedOfferNames())->toBe(['Vejce Kaufland', 'Vejce Tesco']);
});

it('akce vypnutého obchodu nejsou v Mých slevách', function (): void {
    $this->actingAs($this->user);
    foreach ([Chain::Kaufland, Chain::Tesco] as $chain) {
        FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => $chain, 'include_online_only' => true]);
    }
    WatchItem::factory()->for($this->user)->create(['name' => 'Vejce']);
    config(['letaky.disabled_chains' => ['tesco']]);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('watchItems.0.offers', fn ($offers): bool => array_column($offers->all(), 'name') === ['Vejce Kaufland']));
});

it('vypnutý obchod se nestahuje: cron odpoví 200 a nic nestáhne, registr zdrojů ho nevrací', function (): void {
    config(['letaky.cron.token' => 'token', 'letaky.disabled_chains' => ['tesco']]);
    Http::preventStrayRequests();

    $this->get(route('cron.import-offers', ['chain' => 'tesco', 'token' => 'token']))
        ->assertOk()
        ->assertSeeText('Obchod Tesco je vypnutý');

    expect(ScrapeRun::query()->where('chain', Chain::Tesco)->exists())->toBeFalse()
        ->and(app(SourceRegistry::class)->chainsWithOffers())->not->toContain(Chain::Tesco);
});

it('uložení Mých obchodů vypnutý obchod nenabízí, ale jeho sledování nesmaže', function (): void {
    $this->actingAs($this->user);
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Tesco, 'include_online_only' => true]);
    config(['letaky.disabled_chains' => ['tesco']]);

    $this->get(route('preferences'))->assertInertia(fn (Assert $page) => $page
        ->where('chains', fn ($chains): bool => ! collect($chains)->contains('value', 'tesco')));
    $this->put(route('preferences.update'), [
        'chains' => [['chain' => 'kaufland', 'store_format' => null, 'include_online_only' => true]],
        'loyalty_programs' => [],
    ])->assertRedirect(route('preferences'));

    expect($this->user->followedChains()->pluck('chain')->all())->toEqualCanonicalizing([Chain::Kaufland, Chain::Tesco]);
});

it('neznámou hodnotu a velká písmena v proměnné snese', function (): void {
    config(['letaky.disabled_chains' => ['Tesco', 'makro']]);

    expect(Chain::disabled())->toBe([Chain::Tesco])
        ->and(Chain::Tesco->isDisabled())->toBeTrue()
        ->and(Chain::enabled())->not->toContain(Chain::Tesco);
});
