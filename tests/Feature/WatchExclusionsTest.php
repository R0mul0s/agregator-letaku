<?php

/**
 * „Tohle ne“ (R125): skrytí akce u hlídané položky, vyloučení slova z názvu akce (i u položky
 * z katalogu), návrhy slov, vrácení a přehled skrytého v Mých slevách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Catalog\Actions\DeleteProduct;
use App\Domain\Digest\NewOffers;
use App\Enums\Chain;
use App\Http\Controllers\WatchItemController;
use App\Http\Controllers\WatchItemExclusionController;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
});

/**
 * Hlídaná položka s vlastními slovy přihlášeného uživatele.
 *
 * @param  array<string, mixed>  $attributes
 */
function exclusionWatch(array $attributes = []): WatchItem
{
    return WatchItem::factory()->for(test()->user)->create(['name' => 'Máslo', 'keywords' => 'máslo', ...$attributes]);
}

/**
 * Položka z Mých slev (props watchItems) podle názvu.
 *
 * @return array<string, mixed>
 */
function exclusionGroup(string $name): array
{
    $group = [];
    test()->get(route('home'))->assertOk()->assertInertia(function (Assert $page) use ($name, &$group): void {
        $group = collect($page->toArray()['props']['watchItems'])->firstWhere('name', $name) ?? [];
    });

    return $group;
}

it('skryje akci u položky, nabídne Vrátit a vrácením ji ukáže znovu', function (): void {
    $item = exclusionWatch();
    $pumpkin = Offer::factory()->create(['name' => 'Máslová dýně']);
    Offer::factory()->create(['name' => 'Máslo Tatra']);

    $this->from(route('home'))->post(route('watch-items.hidden-offers.store', $item), ['offer_id' => $pumpkin->id])
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', WatchItemExclusionController::STATUS_OFFER_HIDDEN)
        ->assertSessionHas(WatchItemController::UNDO_SESSION_KEY, route('watch-items.hidden-offers.destroy', [$item, $pumpkin], absolute: false));

    $group = exclusionGroup('Máslo');
    expect(array_column($group['offers'], 'name'))->toBe(['Máslo Tatra'])
        ->and(array_column($group['hidden']['offers'], 'name'))->toBe(['Máslová dýně']);

    $this->from(route('home'))->delete(route('watch-items.hidden-offers.destroy', [$item, $pumpkin]))
        ->assertSessionHas('status', WatchItemExclusionController::STATUS_OFFER_RESTORED);

    expect(array_column(exclusionGroup('Máslo')['offers'], 'name'))->toEqualCanonicalizing(['Máslo Tatra', 'Máslová dýně']);
});

it('skrytou akci nehlásí ani souhrn a upozornění', function (): void {
    $item = exclusionWatch();
    $pumpkin = Offer::factory()->create(['name' => 'Máslová dýně']);
    Offer::factory()->create(['name' => 'Máslo Tatra']);
    $item->offerExclusions()->create(['offer_id' => $pumpkin->id]);

    $groups = app(NewOffers::class)->forUser($this->user, null, now()->addMinute()->toImmutable());

    expect($groups)->toHaveCount(1)
        ->and(array_map(fn (Offer $offer): string => $offer->name, $groups[0]['offers']))->toBe(['Máslo Tatra']);
});

it('vyloučí slovo z názvu akce, skryje podobné akce a slovo jde vrátit', function (): void {
    $item = exclusionWatch(['exclude_keywords' => 'pomazánkové']);
    Offer::factory()->create(['name' => 'Máslová dýně Hokkaido']);
    Offer::factory()->create(['name' => 'Máslová dýně z Moravy']);
    Offer::factory()->create(['name' => 'Máslo Tatra']);

    $this->from(route('home'))->post(route('watch-items.excluded-words.store', $item), ['word' => 'Dýně'])
        ->assertSessionHas('status', WatchItemExclusionController::STATUS_WORD_EXCLUDED)
        ->assertSessionHas(WatchItemController::UNDO_SESSION_KEY, route('watch-items.excluded-words.destroy', [$item, 'dýně'], absolute: false));

    expect($item->refresh()->exclude_keywords)->toBe('pomazánkové dýně');
    $group = exclusionGroup('Máslo');
    expect(array_column($group['offers'], 'name'))->toBe(['Máslo Tatra'])
        ->and(array_column($group['hidden']['words'], 'word'))->toBe(['pomazánkové', 'dýně']);

    // Stejné slovo podruhé (i bez diakritiky) se nepřidá
    $this->post(route('watch-items.excluded-words.store', $item), ['word' => 'dyne']);
    expect($item->refresh()->exclude_keywords)->toBe('pomazánkové dýně');

    $this->delete(route('watch-items.excluded-words.destroy', [$item, 'dýně']))
        ->assertSessionHas('status', WatchItemExclusionController::STATUS_WORD_RESTORED);
    expect($item->refresh()->exclude_keywords)->toBe('pomazánkové');
});

it('nepřidá hledané slovo, jeho začátek ani tvar, krátké slovo ani jiné než z písmen', function (string $word): void {
    $item = exclusionWatch(['keywords' => 'máslo', 'variant_keywords' => 'tatra']);

    $this->post(route('watch-items.excluded-words.store', $item), ['word' => $word])->assertSessionHasErrors('word');

    expect($item->refresh()->exclude_keywords)->toBeNull();
})->with(['Máslo', 'mas', 'Máslová', 'tat', 'tatranské', 'dvě slova', 'ab', 'a/b', '250g']);

it('u položky z katalogu skryje akce s vlastním vyloučeným slovem nad přiřazením produktu', function (): void {
    $product = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    $item = exclusionWatch(['product_id' => $product->id, 'keywords' => null]);
    Offer::factory()->create(['name' => 'Máslová dýně']);
    Offer::factory()->create(['name' => 'Máslo Tatra']);
    app(AssignProducts::class)->forProduct($product);

    $this->post(route('watch-items.excluded-words.store', $item), ['word' => 'dýně'])->assertSessionHasNoErrors();

    expect(array_column(exclusionGroup('Máslo')['offers'], 'name'))->toBe(['Máslo Tatra'])
        // Pravidla produktu i přiřazení ostatním uživatelům zůstanou
        ->and($product->refresh()->exclude_keywords)->toBeNull()
        ->and($product->assignments()->count())->toBe(2);
});

it('nabídne k vyloučení slova z názvu kromě hledaných a jejich tvarů, krátkých a čísel', function (): void {
    exclusionWatch();
    Offer::factory()->create(['name' => 'Máslová dýně Hokkaido 1,5 kg s máslem', 'brand' => 'Bio Farma']);

    // „máslem“ nezačíná hledaným „maslo“ — položka přes něj akci nenašla, vyloučit ho jde
    expect(exclusionGroup('Máslo')['offers'][0]['excludeWords'])->toBe(['dýně', 'hokkaido', 'máslem', 'bio', 'farma']);
});

it('z okna akce nepošle toast, okno potvrdí výsledek samo', function (): void {
    $item = exclusionWatch();
    $pumpkin = Offer::factory()->create(['name' => 'Máslová dýně']);

    $this->from(route('home'))->post(route('watch-items.hidden-offers.store', $item), ['offer_id' => $pumpkin->id, 'inline' => true])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status')
        ->assertSessionMissing(WatchItemController::UNDO_SESSION_KEY);
    $this->post(route('watch-items.excluded-words.store', $item), ['word' => 'dýně', 'inline' => true])->assertSessionMissing('status');
    $this->delete(route('watch-items.excluded-words.destroy', [$item, 'dýně']), ['inline' => true])->assertSessionMissing('status');
    $this->delete(route('watch-items.hidden-offers.destroy', [$item, $pumpkin]), ['inline' => true])->assertSessionMissing('status');

    expect($item->refresh()->exclude_keywords)->toBeNull()
        ->and($item->offerExclusions()->count())->toBe(0);
});

it('cizí položce akci neskryje ani slovo nevyloučí', function (): void {
    $foreign = WatchItem::factory()->for(User::factory())->create(['keywords' => 'máslo']);
    $offer = Offer::factory()->create(['name' => 'Máslová dýně']);

    $this->post(route('watch-items.hidden-offers.store', $foreign), ['offer_id' => $offer->id])->assertForbidden();
    $this->post(route('watch-items.excluded-words.store', $foreign), ['word' => 'dýně'])->assertForbidden();
    $this->delete(route('watch-items.excluded-words.destroy', [$foreign, 'dýně']))->assertForbidden();

    expect($foreign->offerExclusions()->count())->toBe(0);
});

it('po smazání produktu dostane položka jeho vyloučená slova i svoje vlastní', function (): void {
    $product = Product::factory()->create(['name' => 'Máslo', 'keywords' => 'máslo', 'exclude_keywords' => 'pomazánkové']);
    $item = exclusionWatch(['product_id' => $product->id, 'keywords' => null, 'exclude_keywords' => 'dýně']);

    app(DeleteProduct::class)->handle($product);

    expect($item->refresh())
        ->product_id->toBeNull()
        ->keywords->toBe('máslo')
        ->exclude_keywords->toBe('pomazánkové dýně');
});
