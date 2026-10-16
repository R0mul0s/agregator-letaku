<?php

/**
 * Dlaždice PDF letáku Lidlu (R86) — strana 20 letáku od 8. 10. 2026: Lidl Plus se standardní
 * cenou vedle velké ceny, sleva „-10 Kč“, tři velké ceny v jednom řádku, strana bez hlavičky;
 * strana 22 letáku od 19. 10. 2026 s balením nad cenou (R150).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Lidl\LidlLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/**
 * Ověřené akce strany 20; leták s platností celého týdne (jako pondělní leták od 5. 10.).
 *
 * @return array<string, OfferData>
 */
function lidlLeafletPage20(): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture('lidl/leaflet-8-10-page-20-2026-10-06.html'));
    $leaflet = new LeafletData(LeafletKind::Leaflet, 'akcni-letak', validFrom: CarbonImmutable::parse('2026-10-05'), validTo: CarbonImmutable::parse('2026-10-11'));

    $offers = [];
    foreach (app(LidlLeafletParser::class)->offers($pages, $leaflet, 'akcni-letak', '/l/cs/letak/%s/view/flyer/page/%d') as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('u Lidl Plus vezme velkou cenu jako cenu s aplikací a malou vedle jako běžnou', function (): void {
    $offers = lidlLeafletPage20();

    // „S Lidl Plus“ „-25%“, 89.90 a vedle 119.90; „200 g, 100 g = 44,95 Kč“ ověří cenu s aplikací
    expect($offers['ALESTO SELECTION Lískové ořechy'])
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(11990)
        ->loyaltyPrice->toBe(8990)
        ->loyaltyProgram->toBe(LoyaltyProgram::LidlPlus)
        ->originalPrice->toBeNull();

    // „-10 Kč“ musí sedět k běžné ceně 69.90
    expect($offers['BELBAKE Mandle'])
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(6990)
        ->loyaltyPrice->toBe(5990)
        ->variantNote->toBeNull();
});

it('velké ceny v jednom řádku přiřadí sloupcům dlaždic', function (): void {
    $offers = lidlLeafletPage20();

    // „179.90 119.90 99.90“ je jeden řádek textu pod třemi dlaždicemi Božkova
    expect($offers['BOŽKOV Tradiční'])->price->toBe(17990)->promotionText->toBe('Ušetřete* 31%')->offerType->toBe(OfferType::PromoPrice)
        ->and($offers['BOŽKOV Vaječný likér'])->price->toBe(11990)->originalPrice->toBe(14990)->discountPercent->toBe(20)
        ->and($offers['BOŽKOV Na pečení'])->price->toBe(9990)->packageText->toBe('0,5l');
});

it('platnost strany bez hlavičky vezme z patičky, ne z letáku', function (): void {
    $offer = lidlLeafletPage20()['BELBAKE Bourbon vanilka'];

    // „Nabídka zboží platí od 8. 10. do 11. 10. 2026“ — leták sám platí od 5. 10.
    expect($offer->validFrom->toDateString())->toBe('2026-10-08')
        ->and($offer->validTo->toDateString())->toBe('2026-10-11')
        ->and($offer->sourceUrl)->toBe('/l/cs/letak/akcni-letak/view/flyer/page/1')
        ->and($offer->externalId)->toBe(lidlLeafletPage20()['BELBAKE Bourbon vanilka']->externalId)->toStartWith('letak-');
});

/**
 * Ověřené akce strany 22 letáku od 19. 10. 2026 — balení je samostatně nad cenou (~25 b.).
 *
 * @return array<string, OfferData>
 */
function lidlLeafletPackageLabels(): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture('lidl/leaflet-19-10-page-22-2026-10-16.html'));
    $leaflet = new LeafletData(LeafletKind::Leaflet, 'akcni-letak', validFrom: CarbonImmutable::parse('2026-10-19'), validTo: CarbonImmutable::parse('2026-10-21'));

    $offers = [];
    foreach (app(LidlLeafletParser::class)->offers($pages, $leaflet, 'akcni-letak', '/l/cs/letak/%s/view/flyer/page/%d') as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('vezme balení nad cenou, když ho popis nemá (leták od 19. 10. 2026)', function (): void {
    $offers = lidlLeafletPackageLabels();

    // „3 x 330 g“ nad cenou 129.90, popis jen „1 kg = 131,21 Kč“
    expect($offers['TRATTORIA ALFREDO Pizza Speciale'])
        ->price->toBe(12990)
        ->packageText->toBe('3 x 330 g')
        ->description->toBe('3 x 330 g, 1 kg = 131,21 Kč')
        ->and($offers['CHEF SELECT Krůtí prsa plněná sýrem'])->price->toBe(14990)->packageText->toBe('1,5 kg')
        ->and($offers['VITASIA Kuřecí stripsy'])->price->toBe(12990)->packageText->toBe('720 g')
        ->description->toBe('720 g, se sladkou chilli omáčkou, 1 kg = 180,42 Kč');
});

it('balení „1 kg“ nad cenou ověří cenu samo, starší rozvržení na téže straně zůstane', function (): void {
    $offers = lidlLeafletPackageLabels();

    // Knedlíky: popis „s uzeným masem“ bez ceny za jednotku, „1 kg“ přímo nad cenou 79.90
    expect($offers['NÁŠ KRAJ Bramborové knedlíky'])->price->toBe(7990)->packageText->toBe('1 kg')
        // Balení v popisu („500 g, různé druhy“) jako dřív
        ->and($offers['PILOS Proteinový tvaroh'])->packageText->toBe('500 g')->loyaltyPrice->toBe(4490)
        ->and(array_keys($offers))->toHaveCount(9);
});
