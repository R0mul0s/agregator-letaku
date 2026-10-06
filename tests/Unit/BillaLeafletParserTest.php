<?php

/**
 * Dlaždice PDF letáku Billy (R89) — strany 1, 4, 22, 23, 36 a 37 velkého letáku od 7. 10. 2026: cena ověřená
 * cenou za jednotku, sleva z přeškrtnuté ceny, cena s BILLA Klubem, akce na množství, platnost oddílu strany,
 * a seznam letáků ze stránky /akcni-letaky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Sources\Billa\BillaLeafletItem;
use App\Domain\Sources\Billa\BillaLeafletList;
use App\Domain\Sources\Billa\BillaLeafletParser;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/** Strany 1, 22 a 23 letáku od 7. 10. 2026 (v PDF fixture jako strany 1–3). */
const BILLA_PAGES_1_22_23 = 'billa/pdf-41-2026-10-06-strany-1-22-23.html';

/** Nezlomitelná mezera v ceně v textu akce („13,95 Kč“). */
const BILLA_LEAFLET_NBSP = "\u{00A0}";

/**
 * Ověřené dlaždice z výstupu pdftotext podle názvu z letáku; platnost letáku 7.–13. 10. 2026.
 *
 * @return array<string, BillaLeafletItem>
 */
function billaLeafletItems(string $fixture): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture($fixture));
    $validity = [CarbonImmutable::parse('2026-10-07'), CarbonImmutable::parse('2026-10-13')];

    $items = [];
    foreach (app(BillaLeafletParser::class)->items($pages, $validity) as $item) {
        $items[$item->name] = $item;
    }

    return $items;
}

it('přijme slevu, kterou ověří balení a cena za jednotku, a opraví glyfy písma', function (): void {
    $items = billaLeafletItems('billa/pdf-41-2026-10-06-strana-4.html');

    // „-17%“ nad „24,90“, pod ní „29,90/“; „balení, 350 g“ a „100 g = 7,11 Kč“; v PDF „Petrżel“
    expect($items['Petržel'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(2490)
        ->originalPrice->toBe(2990)
        ->usualPrice->toBe(2990)
        ->discountPercent->toBe(17)
        ->packageText->toBe('balení, 350 g')
        ->variants->toBeFalse()
        ->and($items['Petržel']->packages)->toBe([['unit' => 'g', 'quantity' => 350.0]]);
});

it('dlaždici bez ceny za jednotku vynechá', function (): void {
    $items = billaLeafletItems('billa/pdf-41-2026-10-06-strana-4.html');

    // Mrkev „volná, 1 kg“, kedluben „1 ks“, cibule — cenu nejde ověřit; zbydou česnek a petržel
    expect(array_keys($items))->toEqualCanonicalizing(['Česnek', 'Petržel']);
});

it('velkou cenu s „běžnou cenou“ pod ní uloží jako cenu s BILLA Klubem', function (): void {
    $items = billaLeafletItems(BILLA_PAGES_1_22_23);

    // „100 g = 13 Kč s Klubem/ 18,38 Kč bez Klubu“, velká „16,90“, „běžná cena 23,90“, „-29%“
    expect($items['Lipánek tvarohový -30 % cukru'])
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(2390)
        ->loyaltyPrice->toBe(1690)
        ->usualPrice->toBe(2390)
        ->originalPrice->toBeNull()
        ->discountPercent->toBeNull()
        ->variants->toBeTrue()
        ->and($items['Máslo'])
        ->price->toBe(3690)
        ->loyaltyPrice->toBe(2490);
});

it('akci na množství uloží s cenou jednoho kusu a výhodnou cenou v textu', function (): void {
    $items = billaLeafletItems(BILLA_PAGES_1_22_23);

    // „při koupi 1 ks 29,90“ v popisu, velká „19,90“ a štítek „PŘI KOUPI OD 3 KS“
    expect($items['Pribináček'])
        ->offerType->toBe(OfferType::Multibuy)
        ->price->toBe(2990)
        ->usualPrice->toBe(2990)
        ->promotionText->toBe('od 3 ks: 19,90'.BILLA_LEAFLET_NBSP.'Kč')
        // „1+1“: „KUPTE 2 ZAPLAŤTE 1“
        ->and($items['Hera Classic'])
        ->price->toBe(2790)
        ->promotionText->toBe('od 2 ks: 13,95'.BILLA_LEAFLET_NBSP.'Kč');
});

it('akční cenu bez přeškrtnuté ceny uloží bez běžné ceny', function (): void {
    $items = billaLeafletItems(BILLA_PAGES_1_22_23);

    // „NAŠE CENA“ „69,90“, „500 ml“, „100 ml = 13,98 Kč“; glyf apostrofu
    expect($items['Hellmann’s Kečup'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(6990)
        ->usualPrice->toBeNull()
        ->originalPrice->toBeNull();
});

it('platnost vezme z oddílu nad dlaždicí', function (): void {
    $items = billaLeafletItems(BILLA_PAGES_1_22_23);

    // „SUPER STŘEDA 7. 10.“, „PŘIPRAVTE SE NA VÍKEND UŽ VE ČTVRTEK OD 8. 10. DO 11. 10.“, „SUPER PÁTEK 9. 10.“
    expect($items['Božkov Standard']->validFrom->toDateString())->toBe('2026-10-07')
        ->and($items['Božkov Standard']->validTo->toDateString())->toBe('2026-10-07')
        ->and($items['Rajčata cherry oválná']->validFrom->toDateString())->toBe('2026-10-08')
        ->and($items['Rajčata cherry oválná']->validTo->toDateString())->toBe('2026-10-11')
        ->and($items['Vejce M podestýlková']->validFrom->toDateString())->toBe('2026-10-09')
        ->and($items['Vejce M podestýlková']->validTo->toDateString())->toBe('2026-10-09')
        // Levý sloupec titulní strany oddíl nemá — platnost letáku
        ->and($items['Máslo']->validFrom->toDateString())->toBe('2026-10-07')
        ->and($items['Máslo']->validTo->toDateString())->toBe('2026-10-13');
});

it('stranu za víkendovou hlavičkou bez vlastní hlavičky vynechá, kromě dlaždice pod oddílem', function (): void {
    $items = billaLeafletItems('billa/pdf-41-2026-10-06-strany-36-37.html');

    // Strana 36 „ČTVRTEK–NEDĚLE 8. 10. – 11. 10. 2026“; strana 37 pokračuje bez hlavičky
    expect($items['Maliny']->validFrom->toDateString())->toBe('2026-10-08')
        ->and($items['Maliny']->validTo->toDateString())->toBe('2026-10-11')
        ->and($items)->not->toHaveKey('Bramborové noky')
        ->and($items)->not->toHaveKey('Red Bull')
        ->and($items['Vejce M podestýlková']->validFrom->toDateString())->toBe('2026-10-09');
});

it('ze stránky letáků vezme karty s platností a ze stránky letáku odkaz na PDF', function (): void {
    $list = app(BillaLeafletList::class);
    $leaflets = collect($list->leaflets(responseFixture('billa/akcni-letaky-2026-10-06.html')))->keyBy('path');

    expect($leaflets)->toHaveCount(5)
        ->and($leaflets)->not->toHaveKey('/gusto/podzim-2026')
        ->and($leaflets['/letaky-billa?tab=letaky-billa/velky-letak-nasledujici'])
        ->title->toBe('Velký leták')
        ->and($leaflets['/letaky-billa?tab=letaky-billa/velky-letak-nasledujici']['validFrom']->toDateString())->toBe('2026-10-07')
        ->and($leaflets['/akcni-letaky/katalog-italie']['validTo']->toDateString())->toBe('2026-10-20')
        ->and($list->pdfUrl(responseFixture('billa/letak-velky-nasledujici-2026-10-06.html')))
        ->toBe('https://view.publitas.com/64069/2700042/pdfs/4a505f87-b950-4bed-ad9f-208c63e4c139.pdf');
});

it('stránka letáků bez karet s platností je změněná odpověď', function (): void {
    app(BillaLeafletList::class)->leaflets('<html><body><a href="/akcni-letaky">Letáky</a></body></html>');
})->throws(SourceResponseChanged::class, 'žádnou kartu s platností');
