<?php

/**
 * Seznam prodejen akce v okně (`/akce/{offer}/prodejny`, R49, R106): jen prodejny, kde akce platí,
 * podle názvu; přihlášenému označí jeho vybrané prodejny. Kaufland i COOP s dlouhým kódem
 * prodejny (R169) — R170 doplnil chybějící test.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\Store;
use App\Models\User;

/** Kód prodejny COOP — část adresy její stránky (R169). */
const COOP_STORE_CODE = 'jednota-kamenice-nad-lipou-viteslava-novaka-550';

/**
 * Akce obchodu platná jen v zadaných prodejnách.
 *
 * @param  list<string>  $codes
 */
function offerInStores(Chain $chain, array $codes): Offer
{
    $offer = Offer::factory()->create(['chain' => $chain]);
    foreach ($codes as $code) {
        OfferStore::query()->create(['offer_id' => $offer->id, 'store_code' => $code]);
    }

    return $offer;
}

beforeEach(function (): void {
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ4400', 'name' => 'Trutnov', 'city' => 'Trutnov']);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ1550', 'name' => 'Vrchlabí', 'city' => 'Vrchlabí']);
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => 'CZ3300', 'name' => 'Praha-Vypich', 'city' => 'Praha']);
    Store::query()->create(['chain' => Chain::Coop, 'code' => COOP_STORE_CODE, 'name' => 'Jednota Kamenice nad Lipou', 'city' => 'Jednota Pelhřimov']);
});

it('vypíše prodejny, kde akce platí; nepřihlášenému nic neoznačí', function (): void {
    $offer = offerInStores(Chain::Kaufland, ['CZ4400', 'CZ1550']);

    $stores = $this->getJson(route('offers.stores', $offer))->assertOk()->json('stores');

    expect(collect($stores)->pluck('name')->sort()->values()->all())->toBe(['Trutnov', 'Vrchlabí'])
        ->and(collect($stores)->pluck('selected')->unique()->all())->toBe([false]);
});

it('přihlášenému označí jeho vybrané prodejny, i COOP s dlouhým kódem (R169)', function (): void {
    $user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $user->id, 'chain' => Chain::Kaufland, 'store_codes' => ['CZ4400']]);
    FollowedChain::query()->create(['user_id' => $user->id, 'chain' => Chain::Coop, 'store_codes' => [COOP_STORE_CODE]]);

    $kaufland = $this->actingAs($user)->getJson(route('offers.stores', offerInStores(Chain::Kaufland, ['CZ4400', 'CZ3300'])))->json('stores');
    $coop = $this->actingAs($user)->getJson(route('offers.stores', offerInStores(Chain::Coop, [COOP_STORE_CODE])))->json('stores');

    expect(collect($kaufland)->pluck('selected', 'name')->all())->toEqualCanonicalizing(['Trutnov' => true, 'Praha-Vypich' => false])
        ->and($coop)->toBe([['name' => 'Jednota Kamenice nad Lipou', 'selected' => true]]);
});

it('neznámou prodejnu (zavřenou) vynechá', function (): void {
    $offer = offerInStores(Chain::Kaufland, ['CZ4400', 'CZ9999']);

    expect($this->getJson(route('offers.stores', $offer))->json('stores'))->toBe([['name' => 'Trutnov', 'selected' => false]]);
});
