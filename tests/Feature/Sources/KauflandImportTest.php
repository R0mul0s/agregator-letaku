<?php

/**
 * Import nabídky Kauflandu ze stránky prodejny.kaufland.cz (ZDROJE_DAT.md, Kaufland).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\ScrapeStatus;
use App\Models\Leaflet;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const KAUFLAND_OFFERS_URL = 'https://prodejny.kaufland.cz/nabidka/prehled.html*';

/**
 * Nabídka z databáze podle čísla artiklu Kauflandu.
 */
function kauflandOffer(string $klNr): Offer
{
    return Offer::query()->where('chain', Chain::Kaufland)->where('external_id', $klNr)->sole();
}

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

it('uloží nabídku a zdroj a stažení zapíše jako úspěšné', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    // 9 položek: duplicitní vejce a položka bez názvu se neuloží
    expect(Offer::query()->count())->toBe(7);

    $leaflet = Leaflet::query()->sole();
    expect($leaflet)
        ->kind->toBe(LeafletKind::Web)
        ->external_id->toBe('nabidka-2026-09-30')
        ->and($leaflet->valid_from->toDateString())->toBe('2026-09-30')
        ->and($leaflet->valid_to->toDateString())->toBe('2026-10-06');

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Succeeded)
        ->offers_count->toBe(7);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->data()['kloffer-week'] === 'current');
});

it('slevu s původní cenou uloží jako slevu a duplicitu z kampaně vynechá', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00153062'))
        ->name->toBe('Čerstvá vejce M20 podestýlková')
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(5990)
        ->original_price->toBe(9990)
        ->discount_percent->toBe(40)
        ->quantity->toBe(20.0)
        ->unit->toBe(PackageUnit::Piece)
        ->source_category->toBe('Mléčné výrobky, tuky, vejce');
});

it('odkaz akce vede na její kategorii a textovým fragmentem na dlaždici', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    $url = kauflandOffer('00153062')->source_url;
    expect($url)
        ->toStartWith('https://prodejny.kaufland.cz/nabidka/prehled.html?kloffer-week=current&kloffer-category='.urlencode('03_Mléčné_výrobky__tuky__vejce').'#:~:text=')
        ->and(rawurldecode((string) parse_url((string) $url, PHP_URL_FRAGMENT)))->toStartWith(':~:text=Čerstvá vejce');
});

it('„AKCE! pouze“ bez původní ceny uloží jako akční cenu, ne slevu (R8)', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00017018'))
        ->name->toBe('Kunín Trvanlivé mléko tuk 1,5 %')
        ->description->toBe('Kunín polotučné tuk 1,5 %')
        ->offer_type->toBe(OfferType::PromoPrice)
        ->price->toBe(890)
        ->original_price->toBeNull()
        ->discount_percent->toBeNull()
        ->quantity->toBe(1000.0)
        ->unit->toBe(PackageUnit::Milliliter);

    expect(kauflandOffer('20026119'))
        ->name->toBe('Coca-Cola Limonáda')
        ->variant_note->toBe('různé druhy')
        ->offer_type->toBe(OfferType::PromoPrice);
});

it('cenu s Kaufland Card uloží vedle běžné ceny', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00131761'))
        ->offer_type->toBe(OfferType::Discount)
        ->price->toBe(2990)
        ->original_price->toBe(4290)
        ->loyalty_price->toBe(2490)
        ->loyalty_program->toBe(LoyaltyProgram::KauflandCard)
        // „250 ml/500 ml“ — cena za jednotku by byla zavádějící
        ->package_text->toBe('250 ml/500 ml')
        ->quantity->toBeNull();
});

it('akci jen s kartou uloží s běžnou cenou z loyaltyFormattedOldPrice', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00074941'))
        ->name->toBe('Tatra Máslo')
        ->offer_type->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(5290)
        ->loyalty_price->toBe(2990)
        ->loyalty_program->toBe(LoyaltyProgram::KauflandCard);
});

it('bez title použije detailTitle a platnost bere z položky, ne z kategorie', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('20809649'))
        ->name->toBe('Rostlinná')
        ->description->toBe('Rama různé druhy')
        ->variant_note->toBe('různé druhy');

    $blueberries = kauflandOffer('00135885');
    expect($blueberries->valid_from->toDateString())->toBe('2026-10-05')
        ->and($blueberries->valid_to->toDateString())->toBe('2026-10-06');
});

it('stáhne i příští týden, když ho stránka ohlásí', function (): void {
    $current = str_replace('"nextWeekDates":[]', '"nextWeekDates":["2026-10-07"]', responseFixture('kaufland/prehled-2026-10-02.html'));
    Http::fake([KAUFLAND_OFFERS_URL => Http::response($current)]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->data()['kloffer-week'] === 'next');
});

it('opakovaný import nabídky nezdvojí, jen aktualizuje', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);
    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(Offer::query()->count())->toBe(7)
        ->and(Leaflet::query()->count())->toBe(1)
        ->and(ScrapeRun::query()->count())->toBe(2);
});

it('nabídku, která v novém stažení chybí, označí jako staženou obchodem a vrátí, když se objeví (R16)', function (): void {
    $full = responseFixture('kaufland/prehled-2026-10-02.html');
    $withoutEggs = str_replace('"klNr":"00153062"', '"klNr":"99999999"', $full);
    Http::fakeSequence(KAUFLAND_OFFERS_URL)
        ->push($full)
        ->push($withoutEggs)
        ->push($full);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);
    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00153062')->withdrawn_at)->not->toBeNull()
        ->and(ScrapeRun::query()->latest('id')->first()?->withdrawn_count)->toBe(1);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);

    expect(kauflandOffer('00153062')->withdrawn_at)->toBeNull();
});

it('když v novém stažení chybí podezřele mnoho akcí, žádnou neoznačí jako staženou (R54)', function (): void {
    // 1 z 8 neskončených akcí je nad touto hranicí
    config(['letaky.import.max_withdrawn_share' => 0.1]);
    $full = responseFixture('kaufland/prehled-2026-10-02.html');
    Http::fakeSequence(KAUFLAND_OFFERS_URL)
        ->push($full)
        ->push(str_replace('"klNr":"00153062"', '"klNr":"99999999"', $full));

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']]);
    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertSuccessful();

    expect(kauflandOffer('00153062')->withdrawn_at)->toBeNull()
        ->and(kauflandOffer('99999999')->exists)->toBeTrue()
        ->and(ScrapeRun::query()->latest('id')->first())
        ->status->toBe(ScrapeStatus::Partial)
        ->withdrawn_count->toBe(0)
        ->error->toContain('chybí 1 z 8 neskončených akcí');
});

it('neočekávanou odpověď zapíše jako neúspěšné stažení a skončí chybou', function (): void {
    Http::fake([KAUFLAND_OFFERS_URL => Http::response('<html><body>Údržba</body></html>')]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeStatus::Failed)
        ->error->toContain('OfferTemplate')
        ->and(Offer::query()->count())->toBe(0);
});

it('chybovou odpověď obchodu zapíše jako neúspěšné stažení', function (): void {
    config(['letaky.http.retries' => 1]);
    Http::fake([KAUFLAND_OFFERS_URL => Http::response('', 503)]);

    $this->artisan('letaky:import-offers', ['chain' => ['kaufland']])->assertFailed();

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed);
});

it('odmítne neznámý obchod', function (): void {
    $this->artisan('letaky:import-offers', ['chain' => ['makro']])->assertExitCode(2);

    expect(ScrapeRun::query()->count())->toBe(0);
});
