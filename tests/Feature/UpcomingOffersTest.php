<?php

/**
 * Akce, které ještě nezačaly (R76): sekce Brzy a „Vyplatí se počkat“ v Mých slevách, filtr
 * na Všech akcích, pořadí v nákupním seznamu a ranní záznam „Od dneška platí…“ v centru upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use App\Domain\Notifications\Actions\RecordStartingOffers;
use App\Domain\Notifications\OffersNotification;
use App\Enums\Chain;
use App\Enums\PackageUnit;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Akce Kauflandu s platností; výchozí cena za 250 g.
 *
 * @param  array<string, mixed>  $attributes
 */
function upcomingTestOffer(string $name, string $from, string $to, array $attributes = []): Offer
{
    return Offer::factory()->create([
        'chain' => Chain::Kaufland,
        'name' => $name,
        'valid_from' => $from,
        'valid_to' => $to,
        'price' => 4990,
        'quantity' => 250,
        'unit' => PackageUnit::Gram,
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    // Pondělí 5. 10. dopoledne v Praze; středa je 7. 10.
    $this->travelTo('2026-10-05 08:00:00');
    $this->user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
});

describe('Moje slevy', function (): void {
    it('akce, které ještě nezačaly, dá do sekce Brzy, ne mezi dnešní', function (): void {
        upcomingTestOffer('Máslo dnes', '2026-10-01', '2026-10-07');
        upcomingTestOffer('Máslo ve středu', '2026-10-07', '2026-10-13');

        $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('watchItems.0.offers', 1)
            ->where('watchItems.0.offers.0.name', 'Máslo dnes')
            ->where('watchItems.0.offers.0.startsInDays', null)
            ->has('watchItems.0.upcoming', 1)
            ->where('watchItems.0.upcoming.0.name', 'Máslo ve středu')
            ->where('watchItems.0.upcoming.0.startsInDays', 2));
    });

    it('poradí počkat jen na výrazně levnější budoucí akci se stejnou jednotkou', function (): void {
        upcomingTestOffer('Máslo dnes', '2026-10-01', '2026-10-07');
        upcomingTestOffer('Máslo ve středu', '2026-10-07', '2026-10-13', ['price' => 3990]);

        $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('watchItems.0.waitTip', [
                'offerId' => Offer::query()->where('name', 'Máslo ve středu')->value('id'),
                'chain' => 'kaufland',
                'chainName' => 'Kaufland',
                'validFrom' => '2026-10-07',
                'userPrice' => 3990,
                'unitPrice' => 15960,
                'unitPriceUnit' => 'kg',
                'savingPercent' => 20,
            ]));
    });

    it('neporadí počkat při malém rozdílu, jiné jednotce ani bez dnešní akce', function (bool $withCurrent, array $upcoming): void {
        if ($withCurrent) {
            upcomingTestOffer('Máslo dnes', '2026-10-01', '2026-10-07');
        }
        upcomingTestOffer('Máslo ve středu', '2026-10-07', '2026-10-13', $upcoming);

        $this->actingAs($this->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('watchItems.0.waitTip', null));
    })->with([
        'o 6 % levnější' => [true, ['price' => 4690]],
        'jiná jednotka' => [true, ['price' => 1990, 'quantity' => 1, 'unit' => PackageUnit::Piece]],
        'bez dnešní akce' => [false, ['price' => 1990]],
    ]);
});

it('Všechny akce: štítek Brzy začnou ukáže jen akce, které ještě nezačaly', function (): void {
    upcomingTestOffer('Máslo dnes', '2026-10-01', '2026-10-07');
    upcomingTestOffer('Máslo ve středu', '2026-10-07', '2026-10-13');

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('offers.data.0.name', 'Máslo dnes')
        ->where('offers.data.1.name', 'Máslo ve středu')
        ->where('filters.brzy', false));
    $this->get(route('offers', ['brzy' => 1]))->assertInertia(fn (Assert $page) => $page
        ->has('offers.data', 1)
        ->where('offers.data.0.name', 'Máslo ve středu')
        ->where('offers.data.0.startsInDays', 2)
        ->where('filters.brzy', true));
});

it('nákupní seznam dá akce, které ještě nezačaly, za ty, které platí', function (): void {
    foreach ([upcomingTestOffer('A máslo ve středu', '2026-10-07', '2026-10-13'), upcomingTestOffer('B máslo dnes', '2026-10-01', '2026-10-07')] as $offer) {
        $this->user->shoppingListItems()->create(['offer_id' => $offer->id]);
    }

    $this->actingAs($this->user)->get(route('shopping-list.index'))->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.items.0.offer.name', 'B máslo dnes')
        ->where('groups.0.items.1.offer.name', 'A máslo ve středu')
        ->where('groups.0.items.1.offer.startsInDays', 2));
});

describe('centrum upozornění: od dneška platí', function (): void {
    beforeEach(function (): void {
        // Obchod zveřejnil akce v sobotu 3. 10.
        $this->travelTo('2026-10-03 10:00:00');
        $this->starting = upcomingTestOffer('Máslo 250 g', '2026-10-05', '2026-10-11');
        $this->listed = upcomingTestOffer('Pivo 0,5 l', '2026-10-05', '2026-10-11', ['chain' => Chain::Lidl]);
        $this->user->shoppingListItems()->create(['offer_id' => $this->starting->id]);
        $this->user->shoppingListItems()->create(['offer_id' => $this->listed->id]);
        upcomingTestOffer('Máslo už běží', '2026-10-01', '2026-10-07');
        upcomingTestOffer('Vejce', '2026-10-05', '2026-10-11');
        // 7:30 v Praze
        $this->travelTo('2026-10-05 05:30:00');
        upcomingTestOffer('Máslo zveřejněné dnes', '2026-10-05', '2026-10-11');
    });

    it('ráno zapíše hlídané akce a akce ze seznamu, které dnes začínají a známe je dopředu', function (): void {
        expect(app(RecordStartingOffers::class)())->toBe(1);

        $record = $this->user->notifications()->sole();
        expect($record->type)->toBe('starting_today')
            ->and(OffersNotification::groups($record))->toBe([
                ['title' => 'Máslo', 'offerIds' => [$this->starting->id]],
                ['title' => 'Nákupní seznam', 'offerIds' => [$this->listed->id]],
            ]);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.0.kind', 'starting_today')
                ->where('notifications.0.title', 'Od dneška platí 2 akce, na které čekáte')
                ->where('notifications.0.text', 'Máslo, Nákupní seznam'));
    });

    it('před sedmou nic, pak jednou denně a uživateli bez začínajících akcí nic', function (): void {
        $other = User::factory()->create();
        WatchItem::factory()->for($other)->create(['name' => 'Rohlík', 'keywords' => 'rohlík']);

        $this->travelTo('2026-10-05 04:30:00');
        expect(app(RecordStartingOffers::class)())->toBe(0);

        $this->travelTo('2026-10-05 05:30:00');
        expect(app(RecordStartingOffers::class)())->toBe(1);
        $this->travelTo('2026-10-05 12:30:00');
        expect(app(RecordStartingOffers::class)())->toBe(0)
            ->and($this->user->notifications()->count())->toBe(1)
            ->and($other->notifications()->count())->toBe(0);
    });

    it('cron souhrnů vypíše dnes začínající akce', function (): void {
        config(['letaky.cron.token' => 'tajny-token']);

        $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
            ->assertOk()
            ->assertSeeText('Dnes začínající akce — zapsáno: 1');
    });
});
