<?php

/**
 * Moje slevy — akce k hlídaným položkám ve sledovaných obchodech (R18, R19).
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
use App\Enums\StoreFormat;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Sledovaný obchod přihlášeného uživatele.
 */
function follow(Chain $chain, ?StoreFormat $format = null, bool $includeOnlineOnly = true): void
{
    FollowedChain::query()->create([
        'user_id' => test()->user->id,
        'chain' => $chain,
        'store_format' => $format,
        'include_online_only' => $includeOnlineOnly,
    ]);
}

/**
 * Hlídaná položka přihlášeného uživatele.
 *
 * @param  array<string, string|null>  $attributes
 */
function watch(string $name, array $attributes = []): WatchItem
{
    return WatchItem::factory()->for(test()->user)->create(['name' => $name, ...$attributes]);
}

/**
 * Názvy nabídek na stránce podle hlídaných položek.
 *
 * @return array<string, list<string>>
 */
function myOffers(): array
{
    $groups = [];
    test()->get(route('home'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$groups): void {
            $page->component('Home');
            foreach ($page->toArray()['props']['watchItems'] as $item) {
                $groups[$item['name']] = array_column($item['offers'], 'name');
            }
        });

    return $groups;
}

it('bez sledovaných obchodů vyzve k jejich výběru', function (): void {
    watch('Vejce');
    Offer::factory()->create(['name' => 'Čerstvá vejce']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('hasFollowedChains', false)
        ->where('watchItems.0.offers', []));
});

it('ukáže akce hlídaných položek jen ze sledovaných obchodů', function (): void {
    follow(Chain::Kaufland);
    watch('Vejce');
    Offer::factory()->create(['name' => 'Čerstvá vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Čerstvá vejce Tesco', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Máslo', 'chain' => Chain::Kaufland]);

    expect(myOffers())->toBe(['Vejce' => ['Čerstvá vejce Kaufland']]);
});

it('neukáže skončené ani obchodem stažené akce a cizí hlídané položky', function (): void {
    follow(Chain::Kaufland);
    watch('Vejce');
    WatchItem::factory()->create(['name' => 'Cizí položka', 'keywords' => 'vejce']);
    Offer::factory()->create(['name' => 'Vejce platná']);
    Offer::factory()->create(['name' => 'Vejce skončená', 'valid_from' => '2026-09-20', 'valid_to' => '2026-10-01']);
    Offer::factory()->create(['name' => 'Vejce stažená', 'withdrawn_at' => CarbonImmutable::now()]);

    expect(myOffers())->toBe(['Vejce' => ['Vejce platná']]);
});

it('u zvoleného typu prodejny ukáže jeho akce a akce všech prodejen, akce jen z e-shopu podle volby', function (): void {
    follow(Chain::Tesco, StoreFormat::Supermarket, includeOnlineOnly: false);
    watch('Vejce');
    Offer::factory()->create(['name' => 'Vejce všude', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Vejce supermarket', 'chain' => Chain::Tesco, 'store_format' => StoreFormat::Supermarket]);
    Offer::factory()->create(['name' => 'Vejce hypermarket', 'chain' => Chain::Tesco, 'store_format' => StoreFormat::Hypermarket]);
    Offer::factory()->create(['name' => 'Vejce online', 'chain' => Chain::Tesco, 'online_only' => true]);

    expect(myOffers()['Vejce'])->toEqualCanonicalizing(['Vejce všude', 'Vejce supermarket']);
});

it('akci jen s kartou ukáže, jen když uživatel kartu má, a řadí podle ceny, kterou zaplatí', function (): void {
    follow(Chain::Tesco);
    watch('Mléko', ['keywords' => 'mléko']);
    $loyaltyOnly = [
        'chain' => Chain::Tesco,
        'offer_type' => OfferType::LoyaltyOnly,
        'price' => 2190,
        'original_price' => null,
        'loyalty_price' => 890,
        'loyalty_program' => LoyaltyProgram::Clubcard,
        'quantity' => 1000,
        'unit' => PackageUnit::Milliliter,
    ];
    Offer::factory()->create(['name' => 'Mléko s Clubcard', ...$loyaltyOnly]);
    Offer::factory()->create(['name' => 'Mléko sleva', 'chain' => Chain::Tesco, 'price' => 1290, 'quantity' => 1000, 'unit' => PackageUnit::Milliliter]);

    expect(myOffers()['Mléko'])->toBe(['Mléko sleva']);

    $this->user->update(['loyalty_programs' => [LoyaltyProgram::Clubcard]]);

    expect(myOffers()['Mléko'])->toBe(['Mléko s Clubcard', 'Mléko sleva']);
});

it('řadí od nejnižší ceny za jednotku, akce na více kusů a možné shody na konec', function (): void {
    follow(Chain::Kaufland);
    watch('Coca-Cola Zero', ['keywords' => 'coca cola', 'variant_keywords' => 'zero']);
    $liters = fn (int $ml): array => ['quantity' => $ml, 'unit' => PackageUnit::Milliliter];
    Offer::factory()->create(['name' => 'Coca-Cola Zero 0,5 l', 'price' => 2490, ...$liters(500)]);
    Offer::factory()->create(['name' => 'Coca-Cola Zero 1,5 l', 'price' => 3290, ...$liters(1500)]);
    Offer::factory()->create(['name' => 'Coca-Cola Zero menu', 'price' => 2990, 'offer_type' => OfferType::Multibuy, ...$liters(1000)]);
    Offer::factory()->create(['name' => 'Coca-Cola různé druhy', 'variant_note' => 'různé druhy', 'price' => 1990, ...$liters(2000)]);

    expect(myOffers()['Coca-Cola Zero'])->toBe([
        'Coca-Cola Zero 1,5 l',
        'Coca-Cola Zero 0,5 l',
        'Coca-Cola Zero menu',
        'Coca-Cola různé druhy',
    ]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('watchItems.0.offers.0.matchStatus', 'match')
        ->where('watchItems.0.offers.3.matchStatus', 'maybe'));
});
