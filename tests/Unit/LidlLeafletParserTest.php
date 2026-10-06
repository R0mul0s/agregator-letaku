<?php

/**
 * Dlaždice PDF letáku Lidlu (R86) — strana 20 letáku od 8. 10. 2026: Lidl Plus se standardní
 * cenou vedle velké ceny, sleva „-10 Kč“, tři velké ceny v jednom řádku, strana bez hlavičky.
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
