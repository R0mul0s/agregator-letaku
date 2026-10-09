<?php

/**
 * Přehled všech aktuálních akcí s hledáním.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\MatchStatus;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\User;
use App\Support\Pagination\PaginationLinks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // 22:30 UTC je už 3. 10. místního času — „dnes“ musí být místní den
    $this->travelTo('2026-10-02T22:30:00Z');
    $this->actingAs(User::factory()->create());
});

/**
 * Názvy nabídek na stránce v pořadí zobrazení.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function offerNames(array $query = []): array
{
    $names = [];
    // Jeden obchod nebo produkt přesměruje na čistou adresu (R94)
    test()->followingRedirects()->get(route('offers', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names): void {
            $page->component('Offers');
            $names = array_column($page->toArray()['props']['offers']['data'], 'name');
        });

    return $names;
}

it('je veřejná: nepřihlášený vidí akce a v navigaci jen Všechny akce (R44)', function (): void {
    auth()->logout();
    Offer::factory()->create(['name' => 'Vejce M']);

    $this->get(route('offers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.data.0.name', 'Vejce M')
            ->where('auth.user', null)
            ->where('auth.registerUrl', '/registrace')
            ->where('navigation', [['url' => '/akce', 'key' => 'offers', 'label' => 'nav.offers', 'tab' => false, 'active' => true]]));
    $this->get(route('offers.suggestions', ['q' => 'vej']))->assertOk();
});

it('ukáže platné i budoucí akce, skončené a stažené obchodem ne', function (): void {
    $today = CarbonImmutable::parse('2026-10-03');
    Offer::factory()->create(['name' => 'Platí dnes', 'valid_from' => $today->subDays(3), 'valid_to' => $today]);
    Offer::factory()->create(['name' => 'Příští týden', 'valid_from' => $today->addDays(4), 'valid_to' => $today->addDays(10)]);
    Offer::factory()->create(['name' => 'Skončila včera', 'valid_from' => $today->subWeek(), 'valid_to' => $today->subDay()]);
    Offer::factory()->create(['name' => 'Stažená obchodem', 'withdrawn_at' => CarbonImmutable::now()]);

    expect(offerNames())->toBe(['Platí dnes', 'Příští týden']);
});

it('popis shodný s balením nepošle — karta by „1 kg“ ukázala dvakrát (R107)', function (): void {
    Offer::factory()->create(['name' => 'Banány', 'description' => '1 kg', 'package_text' => '1 kg']);
    Offer::factory()->create(['name' => 'Fa Deodorant sprej', 'description' => '150 ml • vybrané druhy', 'package_text' => '150 ml']);

    $this->get(route('offers'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $offers = array_column($page->toArray()['props']['offers']['data'], null, 'name');
            expect($offers['Banány'])->description->toBeNull()->packageText->toBe('1 kg')
                ->and($offers['Fa Deodorant sprej']['description'])->toBe('150 ml • vybrané druhy');
        });
});

it('odkaz na stránku letáku označí, odkaz na produkt v e-shopu ne (R110)', function (): void {
    Offer::factory()->create(['name' => 'Banány Albert', 'source_url' => 'https://letaky.albert.cz/41hm_akcni_letak/page/14']);
    Offer::factory()->create(['name' => 'Banány Penny', 'source_url' => 'https://files.rewe.co.at/PennyIntLeaflet/CZ/07_10_2026_tl2/2/']);
    // Akce z letáku Tesca odkazuje na produkt v e-shopu
    Offer::factory()->create(['name' => 'Banány Tesco', 'source_url' => 'https://nakup.itesco.cz/groceries/cs-CZ/products/100073126']);
    Offer::factory()->create(['name' => 'Banány bez odkazu', 'source_url' => null]);

    $this->get(route('offers'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $leaflet = array_column($page->toArray()['props']['offers']['data'], 'sourceIsLeaflet', 'name');
            expect($leaflet)->toEqual(['Banány Albert' => true, 'Banány Penny' => true, 'Banány Tesco' => false, 'Banány bez odkazu' => false]);
        });
});

it('hledá bez ohledu na diakritiku a velikost písmen, v názvu i popisu', function (): void {
    Offer::factory()->create(['name' => 'Kunín Trvanlivé mléko tuk 1,5 %', 'description' => 'Kunín polotučné tuk 1,5 %']);
    Offer::factory()->create(['name' => 'Mléčná čokoláda']);
    Offer::factory()->create(['name' => 'Čerstvá vejce M20']);

    expect(offerNames(['q' => 'mleko']))->toBe(['Kunín Trvanlivé mléko tuk 1,5 %'])
        ->and(offerNames(['q' => 'POLOTUČNÉ mléko']))->toBe(['Kunín Trvanlivé mléko tuk 1,5 %'])
        ->and(offerNames(['q' => 'vejce']))->toBe(['Čerstvá vejce M20']);
});

it('znaky % a _ v hledání bere doslova', function (): void {
    Offer::factory()->create(['name' => 'Smetana 33% tuku']);
    Offer::factory()->create(['name' => 'Smetana 12 tuku']);

    expect(offerNames(['q' => '33%']))->toBe(['Smetana 33% tuku']);
});

it('slovo začíná i po uvozovkách, tečce a hvězdičce jako v párování Mých slev (R113)', function (): void {
    Offer::factory()->create(['name' => 'Sýr „Gouda“ plátky']);
    Offer::factory()->create(['name' => 'Kofola.Original 2 l']);
    Offer::factory()->create(['name' => 'Rajčata*cherry']);
    Offer::factory()->create(['name' => 'Gorgonzola']);

    expect(offerNames(['q' => 'gouda']))->toBe(['Sýr „Gouda“ plátky'])
        ->and(offerNames(['q' => 'original']))->toBe(['Kofola.Original 2 l'])
        ->and(offerNames(['q' => 'cherry']))->toBe(['Rajčata*cherry'])
        ->and(offerNames(['q' => 'zola']))->toBe([]);
});

it('filtruje podle obchodu', function (): void {
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);

    expect(offerNames(['chain' => 'tesco']))->toBe(['Vejce Tesco']);
});

it('odmítne neznámý obchod', function (): void {
    $this->get(route('offers', ['chain' => 'makro']))->assertSessionHasErrors('chain');
    $this->get(route('offers', ['chain' => 'tesco,makro']))->assertSessionHasErrors('chain');
});

it('filtruje podle víc obchodů najednou a vrátí je v pořadí nabídky (R82)', function (): void {
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Vejce Lidl', 'chain' => Chain::Lidl]);

    expect(offerNames(['chain' => 'lidl,kaufland']))->toBe(['Vejce Kaufland', 'Vejce Lidl']);
    $this->get(route('offers', ['chain' => 'lidl,kaufland']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.chain', ['kaufland', 'lidl']));
});

it('přihlášený bez volby vidí své sledované obchody, „vse“ všechny (R82)', function (): void {
    $user = User::factory()->create();
    foreach ([Chain::Tesco, Chain::Lidl] as $chain) {
        FollowedChain::query()->create(['user_id' => $user->id, 'chain' => $chain, 'store_format' => null, 'include_online_only' => true]);
    }
    $this->actingAs($user);
    Offer::factory()->create(['name' => 'Vejce Kaufland', 'chain' => Chain::Kaufland]);
    Offer::factory()->create(['name' => 'Vejce Tesco', 'chain' => Chain::Tesco]);

    expect(offerNames())->toBe(['Vejce Tesco'])
        ->and(offerNames(['chain' => 'vse']))->toBe(['Vejce Kaufland', 'Vejce Tesco']);
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->where('filters.chain', ['tesco', 'lidl']));
    $this->get(route('offers', ['chain' => 'vse']))->assertInertia(fn (Assert $page) => $page->where('filters.chain', []));
});

it('bez e-shopu vynechá akce jen z e-shopu a filtr zůstane v odkazech (R82)', function (): void {
    config(['letaky.offers.per_page' => 1]);
    Offer::factory()->create(['name' => 'Vejce z letáku', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Vejce z prodejny', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Vejce z e-shopu', 'chain' => Chain::Tesco, 'online_only' => true]);

    $this->get(route('offers', ['bez-eshopu' => 1, 'chain' => 'tesco,lidl']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.total', 2)
            ->where('filters.bez-eshopu', true)
            ->where('pagination.nextUrl', '/akce?chain=tesco%2Clidl&bez-eshopu=1&strana=2'));
});

it('pošle ceny v haléřích, cenu za jednotku a názvy z lang', function (): void {
    // Akce jen s kartou — uživatel ji má, jinak by ji nastavení Mých obchodů schovalo (R100)
    $this->actingAs(User::factory()->create(['loyalty_programs' => [LoyaltyProgram::Clubcard]]));
    Offer::factory()->create([
        'chain' => Chain::Tesco,
        'name' => 'Tesco Mléko UHT polotučné 1,5% 1l',
        'offer_type' => OfferType::LoyaltyOnly,
        'price' => 2190,
        'original_price' => null,
        'discount_percent' => null,
        'loyalty_price' => 890,
        'loyalty_program' => LoyaltyProgram::Clubcard,
        'quantity' => 1000,
        'unit' => PackageUnit::Milliliter,
    ]);

    $this->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('offers.total', 1)
            ->where('offers.data.0.chainName', 'Tesco')
            ->where('offers.data.0.offerType', 'loyalty_only')
            ->where('offers.data.0.price', 2190)
            ->where('offers.data.0.loyaltyPrice', 890)
            ->where('offers.data.0.loyaltyProgramName', 'Clubcard')
            ->where('offers.data.0.unitPrice', 2190)
            ->where('offers.data.0.loyaltyUnitPrice', 890)
            ->where('offers.data.0.unitPriceUnit', 'l')
            ->where('chains.1', ['value' => 'tesco', 'name' => 'Tesco']));
});

it('stránkuje podle konfigurace a zachová hledání v odkazech', function (): void {
    config(['letaky.offers.per_page' => 2]);
    Offer::factory()->count(3)->create(['name' => 'Vejce']);

    $this->get(route('offers', ['q' => 'vejce']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers.data', 2)
            ->where('pagination.lastPage', 2)
            ->where('pagination.nextUrl', '/akce?q=vejce&strana=2')
            ->where('pagination.loadMoreUrl', '/akce?q=vejce&od=1&strana=2')
            ->where('pagination.loadMoreCount', 1)
            ->where('pagination.previousUrl', null));
});

it('„Načíst další“ načte celý rozsah stránek z adresy a označí ho (R43)', function (): void {
    config(['letaky.offers.per_page' => 2]);
    Offer::factory()->count(9)->create();

    $this->get('/akce?od=2&strana=3')
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers.data', 4)
            ->where('pagination.from', 2)
            ->where('pagination.to', 3)
            ->where('pagination.shownFrom', 3)
            ->where('pagination.shownTo', 6)
            ->where('pagination.previousUrl', '/akce')
            ->where('pagination.loadMoreUrl', '/akce?od=2&strana=4')
            ->where('pagination.pages', [
                ['number' => 1, 'url' => '/akce', 'current' => false],
                ['number' => 2, 'url' => '/akce?strana=2', 'current' => true],
                ['number' => 3, 'url' => '/akce?strana=3', 'current' => true],
                ['number' => 4, 'url' => '/akce?strana=4', 'current' => false],
                ['number' => 5, 'url' => '/akce?strana=5', 'current' => false],
            ]));
});

it('„Načíst další“ pošle jen novou stránku a Inertia ji připojí (R106)', function (): void {
    config(['letaky.offers.per_page' => 2]);
    Offer::factory()->count(5)->create();
    $partial = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Offers',
        'X-Inertia-Partial-Data' => 'offers,pagination',
    ];

    $fullIds = [];
    $this->get('/akce?od=1&strana=2')->assertInertia(function (Assert $page) use (&$fullIds): void {
        $page->where('pagination.append', ['only' => ['offers', 'pagination'], 'headers' => [PaginationLinks::LOAD_MORE_HEADER => '1']]);
        $fullIds = array_column($page->toArray()['props']['offers']['data'], 'id');
    });

    $response = $this->withHeaders([...$partial, PaginationLinks::LOAD_MORE_HEADER => '1'])->get('/akce?od=1&strana=2')->assertOk();
    expect(array_column($response->json('props.offers.data'), 'id'))->toBe(array_slice($fullIds, 2))
        ->and($response->json('mergeProps'))->toBe(['offers.data'])
        ->and($response->json('matchPropsOn'))->toBe(['offers.data.id'])
        ->and($response->json('props.pagination.shownTo'))->toBe(4)
        ->and($response->json('props'))->not->toHaveKey('filterOptions');

    // Jiné částečné načtení (bez hlavičky) dostane celý rozsah bez slučování
    $response = $this->withHeaders([...$partial, PaginationLinks::LOAD_MORE_HEADER => null])->get('/akce?od=1&strana=2')->assertOk();
    expect($response->json('props.offers.data'))->toHaveCount(4)
        ->and($response->json('mergeProps'))->toBeNull();
});

it('stránku za koncem výpisu zkrátí na poslední a rozsah omezí stropem (R43)', function (): void {
    config(['letaky.offers.per_page' => 2, 'letaky.pagination.max_loaded_pages' => 2]);
    Offer::factory()->count(9)->create();

    $this->get('/akce?strana=99')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.to', 5)
            ->where('pagination.loadMoreUrl', null)
            ->has('offers.data', 1));

    $this->get('/akce?od=1&strana=4')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.from', 3)
            ->has('offers.data', 4));
});

it('s hledaným textem řadí podle relevance: název, značka, až pak popis (R71)', function (): void {
    Offer::factory()->create(['name' => 'Coca-Cola 1l', 'description' => 'MENU PIZZA+COLA', 'discount_percent' => 50]);
    Offer::factory()->create(['name' => 'Feliciana Speciale', 'brand' => 'Pizza Bakery', 'discount_percent' => 40]);
    Offer::factory()->create(['name' => 'Dr. Oetker Pizza', 'discount_percent' => 30]);
    Offer::factory()->create(['name' => 'Pizza šunková', 'discount_percent' => 10]);

    expect(offerNames(['q' => 'pizza']))->toBe(['Pizza šunková', 'Dr. Oetker Pizza', 'Feliciana Speciale', 'Coca-Cola 1l']);
});

it('uvnitř skupiny relevance řadí i podle slevy dopočtené z přeškrtnuté ceny (R113)', function (): void {
    Offer::factory()->create(['name' => 'Pizza se slevou od obchodu', 'discount_percent' => 20]);
    // Albert a Penny procenta neposílají — sleva 50 % jen z přeškrtnuté ceny
    Offer::factory()->create(['name' => 'Pizza z letáku', 'offer_type' => OfferType::Discount, 'discount_percent' => null, 'price' => 5000, 'original_price' => 10000]);

    expect(offerNames(['q' => 'pizza']))->toBe(['Pizza z letáku', 'Pizza se slevou od obchodu']);
});

it('akce produktu z našeptávače; zrušený filtr jen slev (R77) staré odkazy ignorují (R71)', function (): void {
    $product = Product::factory()->create(['name' => 'Máslo']);
    $assigned = Offer::factory()->create(['name' => 'Tatra máslo', 'offer_type' => OfferType::Discount, 'original_price' => 5990]);
    OfferProduct::query()->create(['offer_id' => $assigned->id, 'product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    Offer::factory()->create(['name' => 'Máslo bez produktu', 'offer_type' => OfferType::PromoPrice]);
    Offer::factory()->create(['name' => 'Rama', 'offer_type' => OfferType::PromoPrice]);

    expect(offerNames(['produkt' => $product->id]))->toBe(['Tatra máslo'])
        ->and(offerNames(['sleva' => 1]))->toEqualCanonicalizing(['Tatra máslo', 'Máslo bez produktu', 'Rama']);

    $this->get('/akce/maslo')
        ->assertInertia(fn (Assert $page) => $page->where('product', 'Máslo')->where('filters.produkt', $product->id));
});

it('když text nic nenajde, ukáže výsledky opraveného překlepu (R71)', function (): void {
    Cache::flush();
    Offer::factory()->create(['name' => 'Pizza Margherita']);

    $this->get(route('offers', ['q' => 'pyzza']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('correction', ['original' => 'pyzza', 'corrected' => 'pizza'])
            ->where('offers.data.0.name', 'Pizza Margherita')
            ->where('filters.q', 'pyzza'));
});

it('stránka produktu bez akcí ani produkt omezený na obchod se neindexují (R71, R94)', function (): void {
    $product = Product::factory()->create(['name' => 'Máslo']);

    expect($this->get('/akce/maslo')->getContent())->toContain('<meta name="robots" content="noindex, follow">');

    Offer::factory()->create(['name' => 'Tatra máslo'])->productAssignments()->create(['product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    expect($this->get('/akce/maslo')->getContent())->toContain('<meta name="robots" content="index, follow">')
        ->and($this->get('/akce/maslo?chain=lidl')->getContent())->toContain('<meta name="robots" content="noindex, follow">');
});

it('čisté adresy obchodu a produktu, staré přesměruje, neznámá 404 (R94)', function (): void {
    $product = Product::factory()->create(['name' => 'Minerální voda']);

    $this->get('/akce/lidl')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.chain', ['lidl'])->where('heading', 'Akce z letáku Lidlu'));
    $this->get('/akce/mineralni-voda')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.produkt', $product->id)->where('heading', 'Minerální voda v akci'));
    $this->get('/akce?chain=lidl&strana=2')->assertRedirect('/akce/lidl?strana=2')->assertStatus(301);
    $this->get('/akce?produkt='.$product->id.'&chain=tesco')->assertRedirect('/akce/mineralni-voda?chain=tesco');
    // Víc obchodů a změny filtru ve Vue (požadavek Inertie) zůstanou na /akce bez přesměrování
    $this->get('/akce?chain=lidl,tesco')->assertOk();
    $this->get('/akce?chain=lidl', ['X-Inertia' => 'true'])->assertStatus(409);
    $this->get('/akce/neexistuje')->assertNotFound();
});

it('akce produktu řadí od nejnižší ceny za jednotku, bez balení na konec (R94)', function (): void {
    $product = Product::factory()->create(['name' => 'Pivo']);
    foreach ([['Pivo 2 l', 5990, 2000], ['Pivo 0,5 l', 1990, 500], ['Pivo bez balení', 990, null], ['Pivo 1,5 l', 3990, 1500]] as [$name, $price, $quantity]) {
        Offer::factory()->create(['name' => $name, 'price' => $price, 'quantity' => $quantity, 'unit' => PackageUnit::Milliliter])
            ->productAssignments()->create(['product_id' => $product->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    }

    // 2 l = 29,95 Kč/l, 1,5 l = 26,60 Kč/l, 0,5 l = 39,80 Kč/l
    expect(offerNames(['produkt' => $product->id]))->toBe(['Pivo 1,5 l', 'Pivo 2 l', 'Pivo 0,5 l', 'Pivo bez balení']);
});

it('bez volby řadí doporučeně: čerstvé skutečné slevy, starší slevy, pak ostatní od nejnovějších (R100)', function (): void {
    $now = CarbonImmutable::now();
    Offer::factory()->create(['name' => 'Stará sleva 50 %', 'discount_percent' => 50, 'created_at' => $now->subDays(10)]);
    Offer::factory()->create(['name' => 'Čerstvá sleva 20 %', 'discount_percent' => 20, 'created_at' => $now->subHour()]);
    Offer::factory()->create(['name' => 'Čerstvá sleva 40 %', 'discount_percent' => 40, 'created_at' => $now]);
    // Sleva dopočtená z původní ceny (R8) — 50 %
    Offer::factory()->create(['name' => 'Sleva z původní ceny', 'discount_percent' => null, 'price' => 1000, 'original_price' => 2000, 'created_at' => $now]);
    Offer::factory()->create(['name' => 'Akční cena stará', 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null, 'original_price' => null, 'created_at' => $now->subDays(5)]);
    Offer::factory()->create(['name' => 'Akční cena nová', 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null, 'original_price' => null, 'created_at' => $now]);
    // Akce, která teprve začne (R76), až za vším, co platí dnes
    Offer::factory()->create(['name' => 'Brzy sleva 70 %', 'discount_percent' => 70, 'created_at' => $now, 'valid_from' => '2026-10-05', 'valid_to' => '2026-10-11']);

    expect(offerNames())->toBe(['Sleva z původní ceny', 'Čerstvá sleva 40 %', 'Čerstvá sleva 20 %', 'Stará sleva 50 %', 'Akční cena nová', 'Akční cena stará', 'Brzy sleva 70 %']);
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('sort', 'doporucene')
        ->where('filters.razeni', '')
        ->where('sortOptions', [
            ['value' => 'doporucene', 'label' => 'Doporučené'],
            ['value' => 'sleva', 'label' => 'Největší sleva'],
            ['value' => 'cena', 'label' => 'Nejlevnější za kg, l, ks'],
            ['value' => 'konci', 'label' => 'Končí nejdřív'],
        ]));
});

it('řadí podle volby — sleva, konec platnosti — a volba zůstane v odkazech; relevance jen s textem (R100)', function (): void {
    config(['letaky.offers.per_page' => 2]);
    $today = CarbonImmutable::parse('2026-10-03');
    Offer::factory()->create(['name' => 'Vejce 10 %', 'discount_percent' => 10, 'valid_to' => $today->addDays(1)]);
    Offer::factory()->create(['name' => 'Vejce bez slevy', 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null, 'original_price' => null, 'valid_to' => $today]);
    Offer::factory()->create(['name' => 'Vejce 30 %', 'discount_percent' => 30, 'valid_to' => $today->addDays(5)]);

    expect(offerNames(['razeni' => 'sleva']))->toBe(['Vejce 30 %', 'Vejce 10 %'])
        ->and(offerNames(['razeni' => 'konci']))->toBe(['Vejce bez slevy', 'Vejce 10 %']);
    $this->get(route('offers', ['razeni' => 'sleva']))->assertInertia(fn (Assert $page) => $page
        ->where('sort', 'sleva')
        ->where('filters.razeni', 'sleva')
        ->where('pagination.nextUrl', '/akce?razeni=sleva&strana=2'));
    // S textem je výchozí relevance a jde vybrat; bez textu se volba relevance nepoužije
    $this->get(route('offers', ['q' => 'vejce']))->assertInertia(fn (Assert $page) => $page
        ->where('sort', 'relevance')
        ->where('sortOptions.1.value', 'relevance'));
    $this->get(route('offers', ['razeni' => 'relevance']))->assertInertia(fn (Assert $page) => $page->where('sort', 'doporucene'));
    $this->get(route('offers', ['razeni' => 'nahodne']))->assertSessionHasErrors('razeni');
});

it('podle Mých obchodů schová akce jen z e-shopu a s kartou, kterou uživatel nemá; vypnout jde parametrem (R100)', function (): void {
    $user = User::factory()->create(['loyalty_programs' => [LoyaltyProgram::LidlPlus]]);
    FollowedChain::query()->create(['user_id' => $user->id, 'chain' => Chain::Tesco, 'store_format' => null, 'include_online_only' => false]);
    $this->actingAs($user);
    Offer::factory()->create(['name' => 'Tesco z prodejny', 'chain' => Chain::Tesco]);
    Offer::factory()->create(['name' => 'Tesco z e-shopu', 'chain' => Chain::Tesco, 'online_only' => true]);
    Offer::factory()->create(['name' => 'Tesco s Clubcard', 'chain' => Chain::Tesco, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::Clubcard]);
    // Nesledovaný obchod vybraný ve výpisu: celý, jen bez akcí s kartou, kterou uživatel nemá
    Offer::factory()->create(['name' => 'Lidl z letáku', 'chain' => Chain::Lidl]);
    Offer::factory()->create(['name' => 'Lidl s Lidl Plus', 'chain' => Chain::Lidl, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::LidlPlus]);
    Offer::factory()->create(['name' => 'Penny s kartou', 'chain' => Chain::Penny, 'offer_type' => OfferType::LoyaltyOnly, 'loyalty_program' => LoyaltyProgram::PennyKarta]);

    expect(offerNames(['chain' => 'vse']))->toEqualCanonicalizing(['Tesco z prodejny', 'Lidl z letáku', 'Lidl s Lidl Plus'])
        ->and(offerNames(['chain' => 'vse', 'moje-obchody' => 0]))->toHaveCount(6);
    $this->get(route('offers', ['chain' => 'vse']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.moje-obchody', true)
        ->where('shoppingPreferences', ['hidden' => 3, 'url' => '/obchody']));
    config(['letaky.offers.per_page' => 1]);
    $this->get(route('offers', ['chain' => 'vse', 'moje-obchody' => 0]))->assertInertia(fn (Assert $page) => $page
        ->where('filters.moje-obchody', false)
        ->where('shoppingPreferences.hidden', 0)
        ->where('pagination.nextUrl', '/akce?moje-obchody=0&chain=vse&strana=2'));

    // Nepřihlášený žádné nastavení nemá — vidí vše a štítek nedostane
    auth()->logout();
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('offers.total', 6)
        ->where('shoppingPreferences', null));
});

it('filtruje nové, brzy končící a slevy od procent; filtry zůstanou v odkazech a neindexují se (R101)', function (): void {
    // Místní dnešek je 3. 10. (beforeEach), konec do 2 dnů = nejpozději 5. 10.
    $now = CarbonImmutable::now();
    Offer::factory()->create(['name' => 'Končí zítra 10 %', 'discount_percent' => 10, 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04', 'created_at' => $now->subDays(6)]);
    Offer::factory()->create(['name' => 'Končí za týden 40 %', 'discount_percent' => 40, 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-10', 'created_at' => $now->subDays(6)]);
    Offer::factory()->create(['name' => 'Nová 30 %', 'discount_percent' => 30, 'valid_from' => '2026-10-03', 'valid_to' => '2026-10-09', 'created_at' => $now->subHour()]);
    // Dopočtená sleva 50 % z původní ceny (R8) a akční cena bez slevy
    Offer::factory()->create(['name' => 'Nová z původní ceny', 'discount_percent' => null, 'price' => 1000, 'original_price' => 2000, 'valid_to' => '2026-10-05', 'created_at' => $now]);
    Offer::factory()->create(['name' => 'Nová akční cena', 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null, 'original_price' => null, 'created_at' => $now]);
    // Budoucí akce brzy nekončí, i když je krátká
    Offer::factory()->create(['name' => 'Budoucí krátká', 'valid_from' => '2026-10-04', 'valid_to' => '2026-10-05', 'created_at' => $now->subDays(6)]);

    expect(offerNames(['konci-brzy' => 1]))->toEqualCanonicalizing(['Končí zítra 10 %', 'Nová z původní ceny'])
        ->and(offerNames(['nove' => 1]))->toEqualCanonicalizing(['Nová 30 %', 'Nová z původní ceny', 'Nová akční cena'])
        ->and(offerNames(['sleva-od' => 30]))->toEqualCanonicalizing(['Končí za týden 40 %', 'Nová 30 %', 'Nová z původní ceny'])
        ->and(offerNames(['nove' => 1, 'sleva-od' => 50]))->toBe(['Nová z původní ceny']);

    config(['letaky.offers.per_page' => 1]);
    $response = $this->get(route('offers', ['nove' => 1, 'sleva-od' => 20]));
    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.nove', true)
        ->where('filters.sleva-od', 20)
        ->where('filters.konci-brzy', false)
        ->where('filterOptions', ['endingSoonDays' => 2, 'freshDays' => 2, 'minDiscounts' => [10, 20, 30, 50], 'departments' => []])
        ->where('pagination.nextUrl', '/akce?nove=1&sleva-od=20&strana=2'));
    expect($response->getContent())->toContain('<meta name="robots" content="noindex, follow">');
    $this->get(route('offers', ['sleva-od' => 15]))->assertSessionHasErrors('sleva-od');
});

it('filtruje podle oddělení katalogu přes přiřazený produkt a nabídne jen oddělení s akcemi (R102)', function (): void {
    $drinks = Category::factory()->create(['name' => 'Nápoje', 'position' => 2]);
    $beer = Category::factory()->create(['name' => 'Pivo', 'parent_id' => $drinks->id, 'depth' => 1]);
    $meat = Category::factory()->create(['name' => 'Maso a lahůdky', 'position' => 1]);
    Category::factory()->create(['name' => 'Drogerie', 'position' => 3]);
    $pilsner = Product::factory()->create(['name' => 'Pivo', 'category_id' => $beer->id]);
    $ham = Product::factory()->create(['name' => 'Šunka', 'category_id' => $meat->id]);
    Offer::factory()->create(['name' => 'Pilsner Urquell'])->productAssignments()->create(['product_id' => $pilsner->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    Offer::factory()->create(['name' => 'Šunka od kosti'])->productAssignments()->create(['product_id' => $ham->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    Offer::factory()->create(['name' => 'Bez produktu']);

    expect(offerNames(['kategorie' => 'napoje']))->toBe(['Pilsner Urquell'])
        ->and(offerNames(['kategorie' => 'maso-a-lahudky']))->toBe(['Šunka od kosti']);
    $this->get(route('offers', ['kategorie' => 'napoje']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.kategorie', 'napoje')
        // Drogerie akce nemá — nenabízí se
        ->where('filterOptions.departments', [
            ['slug' => 'maso-a-lahudky', 'name' => 'Maso a lahůdky', 'icon' => 'meat'],
            ['slug' => 'napoje', 'name' => 'Nápoje', 'icon' => 'drinks'],
        ]));
    $this->get(route('offers', ['kategorie' => 'neexistuje']))->assertSessionHasErrors('kategorie');
});
