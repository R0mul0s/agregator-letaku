<?php

/**
 * Průvodce prvním nastavením nového účtu (R153): kroky, ukládání voleb a karta „Dokončete
 * nastavení“ na Mých slevách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\LoyaltyProgram;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Enums\StoreFormat;
use App\Http\Controllers\OnboardingController;
use App\Models\Category;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use App\Support\AnalyticsEvents;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-18 10:00:00');
    $this->user = User::factory()->notOnboarded()->create(['name' => 'Roman Hlaváček']);
    $this->actingAs($this->user);
});

/**
 * Uživatel sleduje obchody bez upřesnění.
 *
 * @param  list<Chain>  $chains
 */
function followChains(User $user, array $chains): void
{
    foreach ($chains as $chain) {
        FollowedChain::query()->create(['user_id' => $user->id, 'chain' => $chain, 'include_online_only' => true]);
    }
}

it('ukáže krok z adresy, produkty běžného nákupu po odděleních a obchody s počty akcí a kartami', function (): void {
    $dairy = Category::factory()->create(['name' => 'Mléčné, vejce a margaríny', 'position' => 0]);
    $drinks = Category::factory()->create(['name' => 'Nápoje', 'position' => 1]);
    $beer = Product::factory()->create(['name' => 'Pivo', 'category_id' => $drinks->id]);
    $butter = Product::factory()->create(['name' => 'Máslo', 'category_id' => $dairy->id]);
    Product::factory()->create(['name' => 'LEGO']);
    config([
        'letaky.onboarding.products' => ['Pivo', 'Máslo', 'Neexistuje'],
        'letaky.catalog.department_icons' => ['Mléčné, vejce a margaríny' => 'dairy', 'Nápoje' => 'drinks'],
    ]);
    followChains($this->user, [Chain::Tesco]);
    Offer::factory()->create(['chain' => Chain::Tesco]);
    Offer::factory()->create(['chain' => Chain::Tesco, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::Clubcard]);
    // Skončená ani budoucí akce se do počtu nepočítá
    Offer::factory()->create(['chain' => Chain::Tesco, 'valid_from' => '2026-10-01', 'valid_to' => '2026-10-07']);
    Offer::factory()->create(['chain' => Chain::Tesco, 'valid_from' => '2026-10-21', 'valid_to' => '2026-10-27']);

    $this->get(route('onboarding', [OnboardingController::STEP_PARAMETER => '2']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding')
            ->where('step', '2')
            ->where('greetingName', 'Romane')
            // Oddělení v pořadí katalogu, ne seznamu v konfiguraci; produkt, který není, chybí
            ->where('featured', [
                ['department' => 'Mléčné, vejce a margaríny', 'icon' => 'dairy', 'productIds' => [$butter->id]],
                ['department' => 'Nápoje', 'icon' => 'drinks', 'productIds' => [$beer->id]],
            ])
            ->has('products', 3)
            ->where('chains', fn ($chains): bool => collect($chains)->firstWhere('value', 'tesco') === [
                'value' => 'tesco',
                'followed' => true,
                'offersCount' => 2,
                'loyaltyProgram' => 'clubcard',
                'loyaltyProgramName' => 'Clubcard',
                'loyaltyOnlyCount' => 1,
            ] && collect($chains)->firstWhere('value', 'lidl')['followed'] === false)
            ->where('offersCount', 0));
});

it('neznámý krok v adrese otevře první', function (): void {
    $this->get(route('onboarding', [OnboardingController::STEP_PARAMETER => 'nesmysl']))
        ->assertInertia(fn (Assert $page) => $page->where('step', '1'));
});

it('klepnutí na produkt ho začne hlídat a druhé přestane; počítadlo ukáže akce v Mých slevách', function (): void {
    followChains($this->user, [Chain::Lidl]);
    $butter = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    $offer = Offer::factory()->create(['name' => 'Máslo 250 g', 'chain' => Chain::Lidl]);
    OfferProduct::query()->create(['offer_id' => $offer->id, 'product_id' => $butter->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    $this->from(route('onboarding'))
        ->post(route('onboarding.items.store'), ['name' => 'Máslo', 'product_id' => $butter->id])
        ->assertRedirect(route('onboarding'))
        ->assertSessionMissing('status')
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'add_watch_item', 'params' => ['item_type' => 'product']]]);

    $item = $this->user->watchItems()->sole();
    $this->get(route('onboarding'))->assertInertia(fn (Assert $page) => $page
        ->where('watchItems.0.productId', $butter->id)
        ->where('watchItems.0.deleteUrl', route('onboarding.items.destroy', $item, absolute: false))
        ->where('offersCount', 1));

    $this->from(route('onboarding'))->delete(route('onboarding.items.destroy', $item))->assertRedirect(route('onboarding'));
    expect($this->user->watchItems()->count())->toBe(0);
});

it('vlastní slova z hledání uloží jako položku; krátká slova odmítne', function (): void {
    $this->from(route('onboarding'))->post(route('onboarding.items.store'), ['name' => 'Almette', 'keywords' => 'almette'])->assertSessionHasNoErrors();
    $this->from(route('onboarding'))->post(route('onboarding.items.store'), ['name' => 'a', 'keywords' => 'a'])->assertSessionHasErrors('keywords');

    expect($this->user->watchItems()->sole())->keywords->toBe('almette')->product_id->toBeNull();
});

it('cizí položku nesmaže', function (): void {
    $foreign = WatchItem::factory()->for(User::factory())->create();

    $this->delete(route('onboarding.items.destroy', $foreign))->assertForbidden();
});

it('obchody: vypnutý přestane sledovat, sledovaný si nechá upřesnění, nový sleduje celou nabídku', function (): void {
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => false, 'store_codes' => ['1234']]);
    followChains($this->user, [Chain::Tesco]);

    $this->from(route('onboarding'))->put(route('onboarding.chains'), ['chains' => ['kaufland', 'albert']])->assertSessionHasNoErrors();

    $followed = $this->user->followedChains()->get()->keyBy(fn (FollowedChain $chain): string => $chain->chain->value);
    expect($followed->keys()->sort()->values()->all())->toBe(['albert', 'kaufland'])
        ->and($followed['kaufland']->store_codes)->toBe(['1234'])
        ->and($followed['kaufland']->include_online_only)->toBeFalse()
        ->and($followed['albert']->store_format)->toBeNull()
        ->and($followed['albert']->include_online_only)->toBeTrue();
});

it('obchod bez zdroje nabídek sledovat nejde', function (): void {
    $this->put(route('onboarding.chains'), ['chains' => ['makro']])->assertSessionHasErrors('chains.0');

    expect($this->user->followedChains()->count())->toBe(0);
});

it('uloží věrnostní karty', function (): void {
    $this->from(route('onboarding'))->put(route('onboarding.cards'), ['loyalty_programs' => ['clubcard', 'lidl_plus']])->assertSessionHasNoErrors();

    expect($this->user->fresh()?->hasLoyaltyProgram(LoyaltyProgram::Clubcard))->toBeTrue()
        ->and($this->user->fresh()?->hasLoyaltyProgram(LoyaltyProgram::LidlPlus))->toBeTrue();

    $this->put(route('onboarding.cards'), ['loyalty_programs' => ['neexistuje']])->assertSessionHasErrors('loyalty_programs.0');
});

it('dokončení zapíše čas, ohlásí událost a ukáže Hotovo; zavření karty se vrátí zpět', function (): void {
    $this->post(route('onboarding.finish'))
        ->assertRedirect(route('onboarding', [OnboardingController::STEP_PARAMETER => OnboardingController::STEP_DONE]))
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'onboarding_complete', 'params' => ['result' => 'finished']]]);
    $finishedAt = $this->user->fresh()?->onboarded_at;
    expect($finishedAt)->not->toBeNull();

    // Druhé dokončení čas nepřepíše
    $this->travel(1)->hours();
    $this->from(route('home'))->post(route('onboarding.finish'), ['dismissed' => true])
        ->assertRedirect(route('home'))
        ->assertSessionHas(AnalyticsEvents::SESSION_KEY, [['name' => 'onboarding_complete', 'params' => ['result' => 'dismissed']]]);
    expect($this->user->fresh()?->onboarded_at?->equalTo($finishedAt))->toBeTrue();
});

it('Moje slevy ukážou kartu Dokončete nastavení podle skutečného stavu účtu, dokud průvodce není za uživatelem', function (): void {
    followChains($this->user, [Chain::Tesco, Chain::Lidl]);
    Offer::factory()->create(['chain' => Chain::Tesco, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::Clubcard]);
    Offer::factory()->create(['chain' => Chain::Tesco, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::Clubcard]);
    // Akce jen s kartou obchodu, který nesleduje, se nepočítá
    Offer::factory()->create(['chain' => Chain::Albert, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::MujAlbert]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('setup.steps.0', ['key' => 'items', 'done' => true, 'count' => 1, 'url' => '/vitejte?krok=1'])
        ->where('setup.steps.1', ['key' => 'chains', 'done' => true, 'count' => 2, 'url' => '/vitejte?krok=2'])
        ->where('setup.steps.2', ['key' => 'cards', 'done' => false, 'count' => 2, 'url' => '/vitejte?krok=3'])
        ->where('setup.steps.3.done', false)
        ->where('setup.dismissUrl', route('onboarding.finish', absolute: false)));

    $this->user->forceFill(['digest_frequency' => DigestFrequency::Daily, 'loyalty_programs' => [LoyaltyProgram::Clubcard]])->save();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('setup.steps.2.done', true)
        ->where('setup.steps.3.done', true));

    $this->post(route('onboarding.finish'), ['dismissed' => true]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('setup', null));
});

it('do průvodce se jde vrátit z Hlídám i z Mých slev, i když je za uživatelem', function (): void {
    $this->post(route('onboarding.finish'), ['dismissed' => true]);

    $this->get(route('watch-items.index'))->assertInertia(fn (Assert $page) => $page->where('urls.onboarding', '/vitejte'));
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('setup', null)->where('urls.onboarding', '/vitejte'));
    $this->get(route('onboarding'))->assertOk();
});

it('karty jsou splněné i bez karty, když mu nic neuniká', function (): void {
    followChains($this->user, [Chain::Lidl]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('setup.steps.2.done', true));
});

it('dosavadní účty kartu nemají a nový účet sleduje všechny obchody bez upřesnění', function (): void {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('setup', null));

    $this->post(route('logout'));
    $this->post(route('register.store'), registrationInput(['email' => 'novy@example.com']));
    $user = User::query()->where('email', 'novy@example.com')->sole();

    expect($user->onboarded_at)->toBeNull()
        ->and($user->followedChains()->count())->toBe(count(app(ChainCatalog::class)->available()))
        ->and($user->followedChains()->whereNotNull('store_format')->exists())->toBeFalse();
});

it('upřesnění sledovaného obchodu z Mých obchodů zůstane i po uložení průvodce', function (): void {
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Albert, 'store_format' => StoreFormat::Hypermarket, 'include_online_only' => true]);

    $this->put(route('onboarding.chains'), ['chains' => ['albert']]);

    expect($this->user->followedChains()->sole()->store_format)->toBe(StoreFormat::Hypermarket);
});
