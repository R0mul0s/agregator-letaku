<?php

/**
 * Hlášení chyb v akcích (R125): odeslání z karty, denní limit, stránka Hlášení pro admina
 * s vyřešením a s tím, co uživatelé skrývají u produktů katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Push\PushDelivery;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSender;
use App\Enums\Chain;
use App\Enums\OfferReportReason;
use App\Http\Controllers\OfferReportController;
use App\Models\Offer;
use App\Models\OfferReport;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
});

it('uloží hlášení a další hlášení stejné akce ho přepíše a znovu otevře', function (): void {
    $offer = Offer::factory()->create();
    $this->actingAs($this->user);

    $this->from(route('offers'))->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'wrong_price', 'note' => ' V letáku 39,90 '])
        ->assertRedirect(route('offers'))
        ->assertSessionHas('status', OfferReportController::STATUS_REPORTED);
    OfferReport::query()->update(['resolved_at' => now()]);

    $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'wrong_validity', 'note' => '']);

    expect(OfferReport::query()->sole())
        ->reason->toBe(OfferReportReason::WrongValidity)
        ->note->toBeNull()
        ->resolved_at->toBeNull();
});

it('pošle adminům upozornění do telefonu, kromě toho, kdo hlásí, a chyba push služby hlášení nezastaví', function (): void {
    $sender = new class implements PushSender
    {
        /** @var list<array{endpoint: string, message: PushMessage}> */
        public array $sent = [];

        /**
         * Zapamatuje si zprávu; zařízení „spadne“ selže výjimkou.
         */
        public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
        {
            if ($subscription->endpoint === 'https://fcm.googleapis.com/fcm/send/spadne') {
                throw new RuntimeException('Push služba neodpovídá');
            }
            $this->sent[] = ['endpoint' => $subscription->endpoint, 'message' => $message];

            return PushDelivery::Sent;
        }
    };
    app()->instance(PushSender::class, $sender);
    $admin = User::factory()->create(['is_admin' => true]);
    $reportingAdmin = User::factory()->create(['is_admin' => true]);
    foreach (['admin' => $admin, 'spadne' => $admin, 'hlasi' => $reportingAdmin, 'uzivatel' => $this->user] as $endpoint => $user) {
        PushSubscription::query()->create(['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$endpoint, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    }
    $offer = Offer::factory()->create(['name' => 'Máslo Tatra', 'chain' => Chain::Lidl]);

    $this->actingAs($reportingAdmin)
        ->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'wrong_price', 'note' => 'V letáku je 39,90'])
        ->assertSessionHasNoErrors();

    expect(OfferReport::query()->count())->toBe(1)
        ->and(array_column($sender->sent, 'endpoint'))->toBe(['https://fcm.googleapis.com/fcm/send/admin'])
        ->and($sender->sent[0]['message']->body)->toBe('Cena je jiná — Máslo Tatra (Lidl) „V letáku je 39,90“')
        ->and($sender->sent[0]['message']->url)->toBe('/hlaseni');
});

it('hlášení z okna akce nepošle toast — poděkuje okno', function (): void {
    $offer = Offer::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'other', 'inline' => true])->assertSessionMissing('status');

    expect(OfferReport::query()->count())->toBe(1);
});

it('hlášení s neznámým důvodem nebo dlouhým popisem odmítne', function (): void {
    $offer = Offer::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'nesmysl'])->assertSessionHasErrors('reason');
    $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'other', 'note' => str_repeat('a', config()->integer('letaky.offer_reports.note_max_length') + 1)])
        ->assertSessionHasErrors('note');

    expect(OfferReport::query()->count())->toBe(0);
});

it('nad denní limit další hlášení nepřijme', function (): void {
    config()->set('letaky.offer_reports.max_per_user_per_day', 2);
    $this->actingAs($this->user);
    $offers = Offer::factory()->count(3)->create();

    foreach ($offers as $offer) {
        $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'other']);
    }

    expect(OfferReport::query()->count())->toBe(2);
});

it('nepřihlášený hlásit nemůže a nemá adresu hlášení', function (): void {
    $offer = Offer::factory()->create();

    $this->post(route('offer-reports.store'), ['offer_id' => $offer->id, 'reason' => 'other'])->assertRedirect(route('login'));
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('offerReports', null));
});

it('admin vidí otevřená hlášení po akcích a vyřeší je, ostatní stránku nevidí', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $offer = Offer::factory()->create(['name' => 'Máslo Tatra']);
    OfferReport::query()->create(['offer_id' => $offer->id, 'user_id' => $this->user->id, 'reason' => OfferReportReason::WrongPrice, 'note' => 'Je za 59,90']);
    OfferReport::query()->create(['offer_id' => $offer->id, 'user_id' => $admin->id, 'reason' => OfferReportReason::Other]);

    $this->actingAs($this->user)->get(route('reports.index'))->assertForbidden();

    $this->actingAs($admin)->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Reports')
        ->has('reports', 1)
        ->where('reports.0.offer.name', 'Máslo Tatra')
        ->has('reports.0.reports', 2)
        ->where('auth.reports.open', 1));

    $this->patch(route('reports.resolve', $offer))->assertSessionHas('status', OfferReportController::STATUS_RESOLVED);

    expect(OfferReport::query()->open()->count())->toBe(0);
});

it('admin vidí, co uživatelé u produktů katalogu skrývají, i v detailu produktu', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $product = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    $pumpkin = Offer::factory()->create(['name' => 'Máslová dýně']);
    Offer::factory()->create(['name' => 'Máslo Tatra']);
    app(AssignProducts::class)->forProduct($product);
    foreach ([$this->user, User::factory()->create()] as $user) {
        $item = WatchItem::factory()->for($user)->create(['product_id' => $product->id, 'keywords' => null, 'exclude_keywords' => 'Dýně']);
        $item->offerExclusions()->create(['offer_id' => $pumpkin->id]);
    }

    $this->actingAs($admin)->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->where('hiddenOffers.0.product.name', 'Máslo')
        ->where('hiddenOffers.0.offer.name', 'Máslová dýně')
        ->where('hiddenOffers.0.users', 2)
        ->where('excludedWords.0.word', 'Dýně')
        ->where('excludedWords.0.users', 2));

    $this->get(route('catalog.show', $product))->assertInertia(fn (Assert $page) => $page
        ->where('excludedWords', [['word' => 'Dýně', 'users' => 2]])
        ->where('assigned', fn ($assigned) => collect($assigned)->firstWhere('name', 'Máslová dýně')['hiddenByUsers'] === 2));
});
