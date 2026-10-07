<?php

/**
 * Moje slevy — akce k hlídaným položkám ve sledovaných obchodech (R18, R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\AssignProducts;
use App\Domain\Catalog\Actions\CorrectAssignment;
use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\OffersSort;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\StoreFormat;
use App\Models\FollowedChain;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use App\Models\OfferStore;
use App\Models\Product;
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

    // Cena, kterou uživatel zaplatí — z ní stránka počítá nejnižší cenu skupiny (R55)
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('watchItems.0.offers.0.userPrice', 890)
        ->where('watchItems.0.offers.1.userPrice', 1290));
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

it('řadí podle předvolby uživatele: od nejvyšší slevy nebo od konce platnosti (R41)', function (): void {
    follow(Chain::Kaufland);
    watch('Máslo', ['keywords' => 'máslo']);
    Offer::factory()->create(['name' => 'Máslo malá sleva', 'discount_percent' => 10, 'valid_to' => '2026-10-04']);
    Offer::factory()->create(['name' => 'Máslo velká sleva', 'discount_percent' => 40, 'valid_to' => '2026-10-08']);
    Offer::factory()->create(['name' => 'Máslo akční cena', 'discount_percent' => null, 'original_price' => null, 'offer_type' => OfferType::PromoPrice, 'valid_to' => '2026-10-03']);

    $this->user->forceFill(['offers_sort' => OffersSort::Discount])->save();
    expect(myOffers()['Máslo'])->toBe(['Máslo velká sleva', 'Máslo malá sleva', 'Máslo akční cena']);

    $this->user->forceFill(['offers_sort' => OffersSort::EndingSoon])->save();
    expect(myOffers()['Máslo'])->toBe(['Máslo akční cena', 'Máslo malá sleva', 'Máslo velká sleva']);
});

it('s minimální slevou ukáže jen akce, které ji splní — i dopočtenou z původní ceny (R41)', function (): void {
    follow(Chain::Kaufland);
    watch('Máslo', ['keywords' => 'máslo']);
    Offer::factory()->create(['name' => 'Máslo 10 %', 'discount_percent' => 10]);
    Offer::factory()->create(['name' => 'Máslo dopočtená', 'discount_percent' => null, 'price' => 2000, 'original_price' => 4000]);
    Offer::factory()->create(['name' => 'Máslo akční cena', 'discount_percent' => null, 'original_price' => null, 'offer_type' => OfferType::PromoPrice]);

    $this->user->forceFill(['min_discount_percent' => 30])->save();

    expect(myOffers()['Máslo'])->toBe(['Máslo dopočtená']);
});

/**
 * Zmínky v letácích bez ceny na stránce podle hlídaných položek: „obchod leták s.číslo stav“.
 *
 * @return array<string, list<string>>
 */
function myMentions(): array
{
    $groups = [];
    test()->get(route('home'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$groups): void {
            foreach ($page->toArray()['props']['watchItems'] as $item) {
                $groups[$item['name']] = array_map(
                    fn (array $mention): string => "{$mention['chain']} {$mention['leafletTitle']} s.{$mention['pageNumber']} {$mention['matchStatus']}",
                    $item['mentions'],
                );
            }
        });

    return $groups;
}

/**
 * Stránka letáku obchodu s textem.
 *
 * @param  array<string, mixed>  $leaflet
 */
function leafletPage(string $text, int $number = 1, array $leaflet = []): LeafletPage
{
    return LeafletPage::factory()->create([
        'text' => $text,
        'number' => $number,
        'leaflet_id' => Leaflet::factory()->create([
            'chain' => Chain::Lidl,
            'kind' => LeafletKind::Leaflet,
            'title' => 'Leták',
            'valid_from' => '2026-10-08',
            'valid_to' => '2026-10-11',
            ...$leaflet,
        ]),
    ]);
}

it('ukáže zmínky v letácích sledovaných obchodů bez stránek s receptem (R27)', function (): void {
    follow(Chain::Lidl);
    watch('Vejce');
    watch('Coca-Cola Zero', ['keywords' => 'coca cola', 'variant_keywords' => 'zero']);
    $page = leafletPage('Okurka Salátová Vejce 39% Řízky', 1);
    leafletPage('Rozšířená Nabídka Coca-Cola 175 1709 -14%', 28, ['external_id' => 'jiny']);
    leafletPage('Telecí Řízek Postup Přípravy Vyklepneme Vejce', 49, ['external_id' => 'recept']);
    leafletPage('Vejce z Penny', 2, ['chain' => Chain::Penny, 'external_id' => 'penny']);
    leafletPage('Vejce skončená', 3, ['external_id' => 'stary', 'valid_from' => '2026-09-24', 'valid_to' => '2026-10-01']);

    expect(myMentions())->toBe([
        'Coca-Cola Zero' => ['lidl Leták s.28 maybe'],
        'Vejce' => ['lidl Leták s.1 match'],
    ]);

    $this->get(route('home'))->assertInertia(fn (Assert $inertia) => $inertia
        ->where('watchItems.1.mentions.0.pageUrl', $page->page_url)
        ->where('watchItems.1.mentions.0.imageUrl', $page->image_url)
        ->where('watchItems.1.mentions.0.validFrom', '2026-10-08'));
});

it('zmínku vynechá, když má obchod ve stejném období akci s cenou', function (): void {
    follow(Chain::Lidl);
    watch('Vejce');
    leafletPage('Vejce 39%', 1);
    leafletPage('Vejce 20 ks', 5, ['external_id' => 'pristi', 'valid_from' => '2026-10-15', 'valid_to' => '2026-10-18']);
    Offer::factory()->create(['name' => 'Čerstvá vejce', 'chain' => Chain::Lidl, 'valid_from' => '2026-10-08', 'valid_to' => '2026-10-10']);

    expect(myMentions())->toBe(['Vejce' => ['lidl Leták s.5 match']]);
});

it('položka z katalogu ukáže akce přiřazené k produktu i s ručními opravami (R31)', function (): void {
    follow(Chain::Kaufland);
    $product = Product::factory()->create(['name' => 'Vejce', 'keywords' => 'vejce']);
    watch('Moje vejce', ['product_id' => $product->id, 'keywords' => null]);
    Offer::factory()->create(['name' => 'Čerstvá vejce']);
    $quail = Offer::factory()->create(['name' => 'Křepelčí vajíčka']);
    Offer::factory()->create(['name' => 'Vejce v Tescu', 'chain' => Chain::Tesco]);
    app(AssignProducts::class)->forProduct($product);
    app(CorrectAssignment::class)->include($product, $quail);

    expect(myOffers()['Moje vejce'])->toEqualCanonicalizing(['Čerstvá vejce', 'Křepelčí vajíčka']);
});

it('u prázdné skupiny řekne, jestli chodí e-mailový souhrn (R42)', function (): void {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('digestFrequency', null)
        ->where('urls.digest', '/ucet#souhrn'));

    $this->user->forceFill(['digest_frequency' => DigestFrequency::Weekly])->save();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('digestFrequency', 'jednou týdně'));
});

it('pozdraví křestním jménem v 5. pádě (R47)', function (): void {
    $this->user->forceFill(['name' => 'Roman Hlaváček'])->save();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('greetingName', 'Romane'));
});

it('najde akci i podle slova se spojovníkem nebo tečkou (předvýběr v databázi hledá část slova)', function (): void {
    follow(Chain::Kaufland);
    watch('K-Mistři', ['keywords' => 'K-Mistři']);
    watch('Dr. Oetker', ['keywords' => 'Dr.Oetker']);
    Offer::factory()->create(['name' => 'K-Mistři od fochu Vepřová pečeně', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Dr.Oetker Ristorante pizza', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Mistrovská klobása', 'chain' => Chain::Kaufland]);

    expect(myOffers())->toBe([
        'Dr. Oetker' => ['Dr.Oetker Ristorante pizza'],
        'K-Mistři' => ['K-Mistři od fochu Vepřová pečeně'],
    ]);
});

it('krmivo pro zvířata ukáže jen u hlídání o zvířatech (R50)', function (): void {
    follow(Chain::Tesco);
    watch('Hovězí', ['keywords' => 'hovězí']);
    watch('Krmivo pro psy', ['keywords' => 'psy|pedigree']);
    Offer::factory()->create(['name' => 'Hovězí zadní plec', 'chain' => Chain::Tesco]);
    // Krmivo poznané podle kategorie obchodu i podle textu
    Offer::factory()->create(['name' => 'Friskies Selections hovězí v želé 12 x 85g', 'chain' => Chain::Tesco, 'source_category' => 'Pro kočky']);
    Offer::factory()->create(['name' => 'Pedigree kapsička s hovězím', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Konzerva pro psy s hovězím', 'chain' => Chain::Tesco]);

    // Pořadí řídí cena (náhodná z factory) — porovnává se bez pořadí
    $groups = array_map(function (array $names): array {
        sort($names);

        return $names;
    }, myOffers());
    expect($groups)->toBe([
        'Hovězí' => ['Hovězí zadní plec'],
        'Krmivo pro psy' => ['Konzerva pro psy s hovězím', 'Pedigree kapsička s hovězím'],
    ]);
});

it('pošle řazení na výběr a adresu, kam se hned uloží (R100)', function (): void {
    $this->user->forceFill(['offers_sort' => OffersSort::Discount, 'min_discount_percent' => 20])->save();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('offersPreferences.sort', 'discount')
        ->where('offersPreferences.minDiscountPercent', 20)
        ->where('offersPreferences.updateUrl', '/ucet/moje-slevy')
        ->where('offersPreferences.sortOptions.0', ['value' => 'unit_price', 'label' => 'nejnižší ceny za jednotku']));

    $this->from(route('home'))
        ->put('/ucet/moje-slevy', ['offers_sort' => 'ending_soon', 'min_discount_percent' => 20])
        ->assertRedirect(route('home'));
    expect($this->user->refresh()->offers_sort)->toBe(OffersSort::EndingSoon);
});

it('u akcí pošle příznaky pro štítky Nové a Končí brzy (R101)', function (): void {
    follow(Chain::Kaufland);
    watch('Máslo', ['keywords' => 'máslo']);
    $now = CarbonImmutable::now();
    // Místní dnešek 2. 10., konec do 2 dnů = nejpozději 4. 10.
    Offer::factory()->create(['name' => 'Máslo nové', 'valid_from' => '2026-10-01', 'valid_to' => '2026-10-08', 'created_at' => $now->subHour()]);
    Offer::factory()->create(['name' => 'Máslo končí', 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04', 'created_at' => $now->subDays(5)]);

    $this->get(route('home'))->assertInertia(function (Assert $page): void {
        $offers = collect($page->toArray()['props']['watchItems'][0]['offers'])->keyBy('name');
        expect($offers['Máslo nové'])->toMatchArray(['isNew' => true, 'endsSoon' => false])
            ->and($offers['Máslo končí'])->toMatchArray(['isNew' => false, 'endsSoon' => true]);
        $page->where('offerFilterDays', ['fresh' => 2, 'endingSoon' => 2]);
    });
});

it('s vybranými prodejnami jde dočasně ukázat akce všech prodejen, nastavení zůstane (R101)', function (): void {
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true, 'store_codes' => ['CZ4400']]);
    watch('Losos', ['keywords' => 'losos']);
    $elsewhere = Offer::factory()->create(['name' => 'Losos jen v Praze']);
    OfferStore::query()->create(['offer_id' => $elsewhere->id, 'store_code' => 'CZ1550']);

    expect(myOffers())->toBe(['Losos' => []]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('stores', ['count' => 1, 'all' => false, 'parameter' => 'prodejny', 'allValue' => 'vse', 'url' => '/']));
    $this->get(route('home', ['prodejny' => 'vse']))->assertInertia(fn (Assert $page) => $page
        ->where('stores.all', true)
        ->where('watchItems.0.offers.0.name', 'Losos jen v Praze'));
    expect(FollowedChain::query()->firstOrFail()->store_codes)->toBe(['CZ4400']);
});

it('bez vybraných prodejen přepínač prodejen nenabídne (R101)', function (): void {
    follow(Chain::Kaufland);

    $this->get(route('home', ['prodejny' => 'vse']))->assertInertia(fn (Assert $page) => $page->where('stores', null));
});
