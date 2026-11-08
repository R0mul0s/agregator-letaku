<?php

/**
 * Import akcí e-shopu Košík.cz — JSON API po odděleních akcí s kurzorem (R179, ZDROJE_DAT.md, Košík).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const KOSIK_MORE_URL = 'https://www.kosik.cz/api/front/products/more';

/**
 * Falešné odpovědi Košíku z fixtures: první stránka podle `slug` („akce/mlecne-a-chlazene“ →
 * akce-mlecne-a-chlazene-2026-11-08.json), další stránka (POST s kurzorem) mléčných výrobků.
 *
 * @param  (Closure(array<mixed>, Request): array<mixed>)|null  $modify  Úprava odpovědi před vrácením
 */
function fakeKosik(?Closure $modify = null): void
{
    Http::fake(function (Request $request) use ($modify) {
        $fixture = str_starts_with($request->url(), KOSIK_MORE_URL)
            ? 'kosik/more-mlecne-a-chlazene-2026-11-08.json'
            : 'kosik/'.str_replace('/', '-', (string) $request['slug']).'-2026-11-08.json';
        $data = jsonResponseFixture($fixture);

        return Http::response($modify === null ? $data : $modify($data, $request));
    });
}

/**
 * Nabídka Košíku z databáze podle začátku názvu.
 */
function kosikOffer(string $name): Offer
{
    return Offer::query()->where('chain', Chain::Kosik)->where('name', 'like', $name.'%')->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-11-08 10:00:00');
    // Fixtures mají jen dvě oddělení a lékárnu (celkem 9 akcí v úvodní stránce „akce“)
    config([
        'letaky.sources.kosik.departments' => ['mlecne-a-chlazene', 'ovoce-a-zelenina'],
        'letaky.sources.kosik.excluded_departments' => ['lekarna-dr-max'],
    ]);
});

it('uloží akce všech stránek oddělení do zdroje akcí e-shopu bez lékárny', function (): void {
    fakeKosik();

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertSuccessful();

    $leaflet = Leaflet::query()->sole();
    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded)
        ->and($leaflet->kind)->toBe(LeafletKind::Eshop)
        ->and($leaflet->external_id)->toBe('akce')
        ->and($leaflet->valid_from)->toBeNull()
        // Mléčné: 3 na první a 2 na další stránce (POST s kurzorem), ovoce a zelenina 3; lékárna se nestahuje
        ->and(Offer::query()->where('chain', Chain::Kosik)->count())->toBe(8)
        ->and(kosikOffer('Mini Babybel')->price)->toBe(4890)
        ->and(Offer::query()->where('name', 'like', 'Dr.Max%')->exists())->toBeFalse()
        ->and(Offer::query()->where('chain', Chain::Kosik)->where('online_only', false)->exists())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === KOSIK_MORE_URL
        && is_string($request['cursor']) && $request['limit'] === 30);
});

it('slevu uloží s původní cenou, balením, platností do data z popisku a odkazy', function (): void {
    fakeKosik();

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']]);

    expect(kosikOffer('Fine Life Eidam'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(1390)
        ->original_price->toBe(1890)
        ->discount_percent->toBe(26)
        ->brand->toBe('Fine Life')
        ->package_text->toBe('100 g')
        ->quantity->toBe(100.0)
        ->unit->toBe(PackageUnit::Gram)
        ->source_category->toBe('Mléčné a chlazené')
        ->source_url->toBe('https://www.kosik.cz/p767408-fine-life-eidam-platky-30')
        ->image_url->toContain('/300x300x1_')
        ->online_only->toBeTrue()
        ->valid_from->toDateString()->toBe('2026-11-08')
        ->valid_to->toDateString()->toBe('2026-11-10')
        ->and(kosikOffer('Mlékárna Kunín Selské mléko'))
        ->package_text->toBe('1 l')
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Milliliter)
        // „Akce platí do 8. 11.“ — končí dnes
        ->and(kosikOffer('Honestly Kimchi Pálivé')->valid_to->toDateString())->toBe('2026-11-08');
});

it('zboží na kusy vážené uloží s cenou za kilogram', function (): void {
    fakeKosik();

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']]);

    // Citron cca 140 g: price 4,886 Kč je odhadovaný kus, akce je 34,90 Kč/kg z 69,90 Kč/kg
    expect(kosikOffer('Citron'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(3490)
        ->original_price->toBe(6990)
        ->package_text->toBe('1 kg')
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Gram)
        ->description->toBe('cca 140 g')
        // Kaki bez productQuantity — balení v názvu není, cena za kus zůstane
        ->and(kosikOffer('Kaki'))
        ->price->toBe(2490)
        ->package_text->toBeNull();
});

it('konec akce bez roku po Novém roce patří do příštího roku a nedávno minulý akci vynechá', function (string $label, ?string $validTo): void {
    $this->travelTo('2026-12-28 10:00:00');
    fakeKosik(function (array $data, Request $request) use ($label): array {
        if (($request['slug'] ?? null) === 'akce/ovoce-a-zelenina') {
            $data['products']['items'][0]['actionLabel'] = $label;
        }

        return $data;
    });

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertSuccessful();

    $citron = Offer::query()->where('name', 'like', 'Citron%')->first();
    expect($citron?->valid_to->toDateString())->toBe($validTo);
})->with([
    'do 5. 1.' => ['Akce platí do 5. 1.', '2027-01-05'],
    'do 31. 12.' => ['Akce platí do 31. 12.', '2026-12-31'],
    'skončila 20. 12.' => ['Akce platí do 20. 12.', null],
]);

it('akce, která pokračuje se stejnou cenou, si nechá začátek z prvního stažení (R54)', function (): void {
    fakeKosik();
    $this->artisan('letaky:import-offers', ['chain' => ['kosik']]);
    $eidam = kosikOffer('Fine Life Eidam');

    $this->travelTo('2026-11-09 10:00:00');
    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertSuccessful();

    // Žádný nový řádek — všechny akce pokračují, Kimchi skončil
    expect(Offer::query()->where('chain', Chain::Kosik)->count())->toBe(8)
        ->and(kosikOffer('Fine Life Eidam'))
        ->id->toBe($eidam->id)
        ->valid_from->toDateString()->toBe('2026-11-08')
        ->valid_to->toDateString()->toBe('2026-11-10')
        ->withdrawn_at->toBeNull()
        // Kimchi skončil 8. 11. — 9. 11. ho Košík ještě vypisuje, ale akce se nevezme
        ->and(Offer::query()->where('name', 'like', 'Honestly Kimchi%')->sole()->valid_to->toDateString())->toBe('2026-11-08');
});

it('neúplné pokrytí akcí odděleními stažení zastaví — přibylo oddělení', function (): void {
    fakeKosik(function (array $data, Request $request): array {
        if (($request['slug'] ?? null) === 'akce') {
            $data['products']['totalCount'] = 100;
        }

        return $data;
    });

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('pokrývají jen 9 z 100 akcí')
        ->and(Offer::query()->count())->toBe(0);
});

it('jiný popisek platnosti u mnoha položek stažení zastaví', function (): void {
    fakeKosik(function (array $data): array {
        foreach ($data['products']['items'] ?? $data['products'] as $index => $item) {
            $path = isset($data['products']['items']) ? ['products', 'items', $index] : ['products', $index];
            data_set($data, implode('.', [...$path, 'actionLabel']), 'Akce do vyprodání zásob');
        }

        return $data;
    });

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('8 z 8 položek nejde převést');
});

it('změněný tvar odpovědi stažení zastaví', function (): void {
    Http::fake(['*' => Http::response(['items' => []])]);

    $this->artisan('letaky:import-offers', ['chain' => ['kosik']])->assertFailed();

    expect(ScrapeRun::query()->sole()->error)->toContain('nemá products.totalCount nebo products.items');
});
