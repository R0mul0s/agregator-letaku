<?php

/**
 * Centrum upozornění (R74): záznamy o nových akcích z cronu, stránka se záznamy a detailem,
 * označení přečtených, zvonek v hlavičce a úklid starých záznamů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use App\Domain\Account\Actions\PruneExpiredSessions;
use App\Domain\Notifications\Actions\RecordEndingOffers;
use App\Domain\Notifications\Actions\RecordNewOffers;
use App\Domain\Notifications\NewOffersNotification;
use App\Domain\Notifications\OffersNotification;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Akce Kauflandu, jako by ji právě přineslo stažení (záznamy vznikají jen po stažení, R58).
 *
 * @param  array<string, mixed>  $attributes
 */
function centerImportedOffer(array $attributes): Offer
{
    ScrapeRun::query()->create(['chain' => Chain::Kaufland, 'status' => ScrapeStatus::Succeeded, 'started_at' => now(), 'finished_at' => now()]);

    return Offer::factory()->create(['chain' => Chain::Kaufland, ...$attributes]);
}

/**
 * Záznam o nových akcích uživatele, jako by ho zapsal cron.
 *
 * @param  list<array{watchItem: string, offerIds: list<int>}>  $groups
 */
function centerRecord(User $user, array $groups): DatabaseNotification
{
    $user->notify(new NewOffersNotification($groups));

    /** @var DatabaseNotification */
    return $user->notifications()->firstOrFail();
}

/**
 * Akce v nákupním seznamu uživatele, výchozí konec zítra (6. 10.).
 *
 * @param  array<string, mixed>  $attributes
 */
function listedOffer(User $user, array $attributes, bool $checked = false): Offer
{
    $offer = Offer::factory()->create(['valid_from' => '2026-09-30', 'valid_to' => '2026-10-06', ...$attributes]);
    $user->shoppingListItems()->create(['offer_id' => $offer->id, 'checked_at' => $checked ? now() : null]);

    return $offer;
}

beforeEach(function (): void {
    $this->travelTo('2026-10-05 06:30:00');
    $this->user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
});

describe('záznamy z cronu', function (): void {
    it('první zpracování jen začne počítat — dřívější akce nejsou nové', function (): void {
        centerImportedOffer(['name' => 'Máslo 250 g']);

        expect(app(RecordNewOffers::class)())->toBe(0)
            ->and($this->user->notifications()->count())->toBe(0)
            ->and($this->user->fresh()?->notified_at?->toDateTimeString())->toBe('2026-10-05 06:30:00');
    });

    it('zapíše nové akce po hlídaných položkách, i bez zapnutých upozornění a ověřeného e-mailu', function (): void {
        $this->user->forceFill(['notified_at' => now()->subHour(), 'email_verified_at' => null])->save();
        $butter = centerImportedOffer(['name' => 'Máslo 250 g']);
        centerImportedOffer(['name' => 'Vejce M']);

        expect(app(RecordNewOffers::class)())->toBe(1);

        $record = $this->user->notifications()->sole();
        expect($record->type)->toBe('new_offers')
            ->and($record->read_at)->toBeNull()
            ->and(NewOffersNotification::groups($record))->toBe([['title' => 'Máslo', 'offerIds' => [$butter->id]]]);
    });

    it('zapamatuje si akce nejlevnější za 12 týdnů a dá je do nadpisu i do detailu (11c)', function (): void {
        $this->user->forceFill(['notified_at' => now()->subHour()])->save();
        // Stejná položka Kauflandu před třemi týdny dráž — skončená, sama nová není
        Offer::factory()->create(['chain' => Chain::Kaufland, 'external_id' => 'maslo-250', 'name' => 'Máslo 250 g', 'price' => 4990, 'valid_from' => '2026-09-09', 'valid_to' => '2026-09-15']);
        $butter = centerImportedOffer(['external_id' => 'maslo-250', 'name' => 'Máslo 250 g', 'price' => 3990, 'valid_from' => '2026-10-01', 'valid_to' => '2026-10-07']);

        app(RecordNewOffers::class)();

        $record = $this->user->notifications()->sole();
        expect(OffersNotification::lowestOfferIds($record))->toBe([$butter->id]);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.0.title', 'Máslo je nejlevněji za 12 týdnů')
                ->where('notifications.0.lowestCount', 1)
                ->where('priceHistoryWeeks', 12));
        $this->get(route('notifications.show', $record->id))
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.offers.0.priceHistory.status', 'lowest'));
    });

    it('bez nové akce nic nezapíše, čas posune', function (): void {
        $this->user->forceFill(['notified_at' => now()->subHour()])->save();
        centerImportedOffer(['name' => 'Vejce M']);

        expect(app(RecordNewOffers::class)())->toBe(0)
            ->and($this->user->notifications()->count())->toBe(0)
            ->and($this->user->fresh()?->notified_at?->toDateTimeString())->toBe('2026-10-05 06:30:00');
    });

    it('bez stažení od posledního zpracování uživatele vůbec nepočítá', function (): void {
        centerImportedOffer(['name' => 'Máslo 250 g']);
        app(RecordNewOffers::class)();
        $this->travelTo('2026-10-05 07:30:00');

        expect(app(RecordNewOffers::class)())->toBe(0)
            ->and($this->user->fresh()?->notified_at?->toDateTimeString())->toBe('2026-10-05 06:30:00');
    });

    it('cron souhrnů zapíše záznamy jako první', function (): void {
        config(['letaky.cron.token' => 'tajny-token']);
        $this->user->forceFill(['notified_at' => now()->subHour()])->save();
        centerImportedOffer(['name' => 'Máslo 250 g']);

        $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
            ->assertOk()
            ->assertSeeText('Centrum upozornění — zapsáno: 1');
    });
});

describe('stránka', function (): void {
    it('ukáže vlastní záznamy s nadpisem a hlídanými položkami, cizí ne', function (): void {
        $butter = centerImportedOffer(['name' => 'Máslo 250 g']);
        $record = centerRecord($this->user, [['title' => 'Máslo', 'offerIds' => [$butter->id]]]);
        centerRecord(User::factory()->create(), [['title' => 'Pivo', 'offerIds' => [$butter->id]]]);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications')
                ->has('notifications', 1)
                ->where('notifications.0.id', $record->id)
                ->where('notifications.0.title', 'Máslo je v akci')
                ->where('notifications.0.text', 'Máslo')
                ->where('notifications.0.unread', true)
                ->where('notifications.0.url', '/upozorneni/'.$record->id)
                ->where('retentionDays', 30));

        // Zobrazení nic neoznačí — přečtení posílá stránka zvlášť
        expect($record->fresh()?->read_at)->toBeNull();
    });

    it('víc akcí shrne počtem a vypíše všechny hlídané položky', function (): void {
        $offers = Offer::factory()->count(3)->create(['chain' => Chain::Kaufland]);
        centerRecord($this->user, [
            ['title' => 'Máslo', 'offerIds' => [$offers[0]->id, $offers[1]->id]],
            ['title' => 'Pivo', 'offerIds' => [$offers[2]->id]],
        ]);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.0.title', '3 nové akce na hlídané zboží')
                ->where('notifications.0.text', 'Máslo, Pivo'));
    });

    it('detail ukáže akce s obchodem a cenou a skončené označí', function (): void {
        $running = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Máslo 250 g', 'price' => 3990]);
        $withdrawn = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Máslo stažené', 'withdrawn_at' => now()]);
        $expired = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Máslo staré', 'valid_from' => '2026-09-20', 'valid_to' => '2026-09-27']);
        $record = centerRecord($this->user, [['title' => 'Máslo', 'offerIds' => [$running->id, $withdrawn->id, $expired->id]]]);

        $this->actingAs($this->user)->get(route('notifications.show', $record->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NotificationDetail')
                ->where('notification.title', '3 nové akce na hlídané zboží')
                ->where('groups.0.title', 'Máslo')
                ->where('groups.0.offers.0.name', 'Máslo 250 g')
                ->where('groups.0.offers.0.chainName', 'Kaufland')
                ->where('groups.0.offers.0.userPrice', 3990)
                ->where('groups.0.offers.0.ended', false)
                ->where('groups.0.offers.1.ended', true)
                ->where('groups.0.offers.2.ended', true));
    });

    it('cizí záznam neukáže', function (): void {
        $record = centerRecord(User::factory()->create(), [['title' => 'Máslo', 'offerIds' => []]]);

        $this->actingAs($this->user)->get(route('notifications.show', $record->id))->assertNotFound();
    });

    it('označí přečtené jen vlastní záznamy', function (): void {
        $mine = centerRecord($this->user, [['title' => 'Máslo', 'offerIds' => []]]);
        $foreign = centerRecord(User::factory()->create(), [['title' => 'Pivo', 'offerIds' => []]]);

        $this->actingAs($this->user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read'), ['ids' => [$mine->id, $foreign->id]])
            ->assertRedirect(route('notifications.index'));

        expect($mine->fresh()?->read_at)->not->toBeNull()
            ->and($foreign->fresh()?->read_at)->toBeNull();
    });

    it('zvonek v hlavičce dostane počet nepřečtených', function (): void {
        centerRecord($this->user, [['title' => 'Máslo', 'offerIds' => []]]);

        $this->actingAs($this->user)->get(route('shopping-list.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificationCenter.url', '/upozorneni')
                ->where('notificationCenter.unread', 1)
                ->where('notificationCenter.active', false));

        $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('notificationCenter.unread', 1));
        auth()->logout();
        $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('notificationCenter', null));
    });

    it('nepřihlášeného pošle na přihlášení', function (): void {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    });
});

describe('akce ze seznamu brzy končí (11b)', function (): void {
    beforeEach(function (): void {
        // 14:30 UTC = 16:30 v Praze (letní čas), zítra je 6. 10.
        $this->travelTo('2026-10-05 14:30:00');
    });

    it('zapíše neodškrtnuté akce ze seznamu, které zítra končí, po obchodech', function (): void {
        $butter = listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Máslo 250 g']);
        $beer = listedOffer($this->user, ['chain' => Chain::Lidl, 'name' => 'Pivo 0,5 l']);
        listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Koupeno'], checked: true);
        listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Stažená', 'withdrawn_at' => now()]);
        listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Končí pozítří', 'valid_to' => '2026-10-07']);
        // Billa: konec akce je jen odhad akčního týdne (R48, R54)
        listedOffer($this->user, ['chain' => Chain::Billa, 'name' => 'Billa máslo']);

        expect(app(RecordEndingOffers::class)())->toBe(1);

        $record = $this->user->notifications()->sole();
        expect($record->type)->toBe('ending_soon')
            ->and(OffersNotification::groups($record))->toBe([
                ['title' => 'Kaufland', 'offerIds' => [$butter->id]],
                ['title' => 'Lidl', 'offerIds' => [$beer->id]],
            ]);

        $this->actingAs($this->user)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.0.kind', 'ending_soon')
                ->where('notifications.0.title', 'Zítra končí 2 akce z vašeho seznamu')
                ->where('notifications.0.text', 'Kaufland, Lidl'));
        $this->get(route('notifications.show', $record->id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.title', 'Kaufland')
                ->where('groups.0.offers.0.name', 'Máslo 250 g')
                ->where('shoppingListUrl', '/seznam'));
    });

    it('dopoledne ještě nic nezapíše', function (): void {
        $this->travelTo('2026-10-05 08:00:00');
        listedOffer($this->user, ['chain' => Chain::Kaufland]);

        expect(app(RecordEndingOffers::class)())->toBe(0);
    });

    it('zapíše jednou denně, další den znovu', function (): void {
        listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Máslo']);
        listedOffer($this->user, ['chain' => Chain::Kaufland, 'name' => 'Vejce', 'valid_to' => '2026-10-07']);

        expect(app(RecordEndingOffers::class)())->toBe(1);
        $this->travelTo('2026-10-05 19:30:00');
        expect(app(RecordEndingOffers::class)())->toBe(0);
        $this->travelTo('2026-10-06 14:30:00');
        expect(app(RecordEndingOffers::class)())->toBe(1)
            ->and($this->user->notifications()->count())->toBe(2);
    });

    it('cron souhrnů vypíše končící akce', function (): void {
        config(['letaky.cron.token' => 'tajny-token']);
        listedOffer($this->user, ['chain' => Chain::Kaufland]);

        $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
            ->assertOk()
            ->assertSeeText('Končící akce ze seznamu — zapsáno: 1');
    });
});

it('denní úklid smaže záznamy starší než doba uchování', function (): void {
    centerRecord($this->user, [['title' => 'Máslo', 'offerIds' => []]]);
    $this->travelTo('2026-11-04 06:30:00');
    centerRecord($this->user, [['title' => 'Pivo', 'offerIds' => []]]);
    $this->travelTo('2026-11-05 06:31:00');

    app(PruneExpiredSessions::class)();

    expect($this->user->notifications()->get()->map(fn (DatabaseNotification $record): array => OffersNotification::groups($record))->all())
        ->toBe([[['title' => 'Pivo', 'offerIds' => []]]]);
});
