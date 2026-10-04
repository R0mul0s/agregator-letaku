<?php

/**
 * „Hlídat“ z karty ve Všech akcích (R60): produkt katalogu jedním klepnutím, akce bez produktu
 * do formuláře vlastních slov, nepřihlášený přes registraci.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Http\Controllers\WatchItemController;
use App\Http\Responses\RegisterResponse;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->butter = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    $this->butterOffer = Offer::factory()->create(['name' => 'Máslo Tatra 250 g']);
    OfferProduct::query()->create(['offer_id' => $this->butterOffer->id, 'product_id' => $this->butter->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    $this->otherOffer = Offer::factory()->create(['name' => 'Zubní pasta Elmex']);
});

/**
 * Cíl hlídání akce ze stránky Všech akcí podle názvu akce.
 *
 * @return array<string, mixed>|null
 */
function watchTargetOf(string $name): ?array
{
    $target = null;
    test()->get(route('offers'))->assertInertia(function (Assert $page) use ($name, &$target): void {
        $target = collect($page->toArray()['props']['offers']['data'])->firstWhere('name', $name)['watchTarget'] ?? null;
    });

    return $target;
}

it('u akce s produktem katalogu nabídne produkt, jinak název akce', function (): void {
    expect(watchTargetOf('Máslo Tatra 250 g'))->toBe(['productId' => $this->butter->id, 'name' => 'Máslo', 'watched' => false])
        ->and(watchTargetOf('Zubní pasta Elmex'))->toBe(['productId' => null, 'name' => 'Zubní pasta Elmex', 'watched' => false]);
});

it('produkt přidá jedním klepnutím, vrátí se na Všechny akce a označí ho jako hlídaný', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from(route('offers'))
        ->post(route('watch-items.store'), ['product_id' => $this->butter->id, 'name' => 'Máslo', WatchItemController::STAY_FIELD => true])
        ->assertRedirect(route('offers'))
        ->assertSessionHas('status', WatchItemController::STATUS_ADDED);

    expect($user->watchItems()->sole()->product_id)->toBe($this->butter->id)
        ->and(watchTargetOf('Máslo Tatra 250 g')['watched'])->toBeTrue();
});

it('akce bez produktu otevře Hlídám s názvem akce ve formuláři vlastních slov', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('watch-items.index', [WatchItemController::PREFILL_PARAMETER => 'Zubní pasta Elmex']))
        ->assertInertia(fn (Assert $page) => $page->where('prefill', 'Zubní pasta Elmex'));
});

it('nepřihlášený přes registraci — produkt začne hlídat hned po ní', function (): void {
    $this->get(route('register', [RegisterResponse::WATCH_PARAMETER => $this->butter->id]))->assertOk();

    $this->post(route('register.store'), registrationInput());

    expect(WatchItem::query()->sole())
        ->product_id->toBe($this->butter->id)
        ->name->toBe('Máslo');
});

it('neexistující produkt v adrese registrace ignoruje', function (): void {
    $this->get(route('register', [RegisterResponse::WATCH_PARAMETER => 999999]))->assertOk();

    $this->post(route('register.store'), registrationInput());

    expect(WatchItem::query()->count())->toBe(0);
});
