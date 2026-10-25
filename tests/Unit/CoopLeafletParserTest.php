<?php

/**
 * Dlaždice PDF letáků COOP (R167) — strana 1 celostátního letáku CC 22, strany 1 a 3 letáku střední
 * Čechy (SČ 22) a strana 1 letáku jižní Čechy (JČ 22), platnost 21. 10.–3. 11. 2026: text dlaždice
 * z bloku pdftotext, cena ověřená cenou za jednotku, cena s kupónem v aplikaci, pult za 100 g,
 * různé velikosti písma po letácích.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Coop\CoopLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

const COOP_LEAFLET_URL = 'https://www.coopclub.cz/letaky/sc-aktualni-letak/';

/**
 * Ověřené akce z výstupu pdftotext podle názvu; platnost letáku 21. 10.–3. 11. 2026.
 *
 * @return array<string, OfferData>
 */
function coopLeafletOffers(string $fixture): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture($fixture));
    $validity = [CarbonImmutable::parse('2026-10-21'), CarbonImmutable::parse('2026-11-03')];

    $offers = [];
    foreach (app(CoopLeafletParser::class)->offers($pages, $validity, COOP_LEAFLET_URL) as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('přečte platnost z hlavičky regionálního i celostátního letáku', function (): void {
    $parser = app(CoopLeafletParser::class);
    $reader = app(PdfTextReader::class);

    // „OD STŘEDY 21. 10. DO ÚTERÝ 3. 11. 2026“ a „od 21. 10. do 3. 11. 2026“
    foreach (['coop/pdf-sc-22-2026-10-25-strana-1.html', 'coop/pdf-csc-22-2026-10-25-strana-1.html'] as $fixture) {
        expect(array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $parser->validity($reader->parse(responseFixture($fixture))) ?? []))
            ->toBe(['2026-10-21', '2026-11-03']);
    }
});

it('přijme cenu ověřenou balením a cenou za jednotku z řádku „130 g | 1 kg = 130,–“', function (): void {
    $offers = coopLeafletOffers('coop/pdf-sc-22-2026-10-25-strana-1.html');

    // Značka verzálkami menším písmem nad tučným názvem
    expect($offers['Madeta Lipánek Maxi'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(1690)
        ->brand->toBe('Madeta')
        ->packageText->toBe('130 g')
        ->description->toBe('vybrané druhy')
        ->variantNote->toBe('vybrané druhy')
        ->sourceUrl->toBe(COOP_LEAFLET_URL)
        ->and($offers['Madeta Lipánek Maxi']->package?->quantity)->toBe(130.0)
        ->and($offers['Madeta Lipánek Maxi']->package?->unit)->toBe(PackageUnit::Gram)
        // Víc velikostí balení: „120/130 g | 1 kg = 290,83/268,46“
        ->and($offers['Bohemia Chips, Sticks']->price)->toBe(3490)
        // Prací dávky „16/47/59 PD | 1 PD = 5,62/1,91/1,52“
        ->and($offers['Lenor']->price)->toBe(8990);
});

it('pult s cenou za 100 g ověří desetinásobkem ceny za kilogram', function (): void {
    // Odznak „100 g“ u ceny 21,90, v textu jen „1 kg = 219,– | pultový prodej“ v bloku pod názvem
    expect(coopLeafletOffers('coop/pdf-sc-22-2026-10-25-strana-1.html')['Herkules'])
        ->price->toBe(2190)
        ->packageText->toBe('100 g');
});

it('cenu s kupónem v aplikaci uloží vedle ceny bez aplikace', function (): void {
    $offers = coopLeafletOffers('coop/pdf-sc-22-2026-10-25-strana-3.html');

    // „s kupónem 1 PD = 7,89/6,–/4,76“ a „bez kupónu 1 PD = 9,21/7,–/5,55“ u 38/50/63 PD
    expect($offers['Ariel'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(34990)
        ->loyaltyPrice->toBe(29990)
        ->loyaltyProgram->toBe(LoyaltyProgram::CoopCesko)
        // Značka rozdělená na konci řádku „VELKO-“ / „POPOVICKÝ“
        ->and($offers)->toHaveKey('Velkopopovický Kozel 10');
});

it('celostátní leták verzálkami a s větším písmem převede na běžný text', function (): void {
    $offers = coopLeafletOffers('coop/pdf-csc-22-2026-10-25-strana-1.html');

    // „PÁRKY SE SÝREM“, „1 kg = 189,– Kč | pultový prodej“; „S KUPÓNEM 1 l = …“ verzálkami
    expect($offers['Párky se sýrem']->price)->toBe(1890)
        ->and($offers['Ariel']->loyaltyPrice)->toBe(29990)
        ->and($offers['Madeta Lipánek maxi']->price)->toBe(1690);
});

it('leták s menším písmem a cenou za jednotku v tisících', function (): void {
    $offers = coopLeafletOffers('coop/pdf-jc-22-2026-10-25-strana-1.html');

    // „20 g | 1 kg = 1 495,–“
    expect($offers['Joy']->price)->toBe(2990)
        ->and($offers['Lipánek mléko'])
        ->price->toBe(1390)
        ->packageText->toBe('250 ml');
});

it('počítá nalezené a ověřené ceny letáku (R129)', function (): void {
    $parser = app(CoopLeafletParser::class);
    $parser->offers(
        app(PdfTextReader::class)->parse(responseFixture('coop/pdf-sc-22-2026-10-25-strana-3.html')),
        [CarbonImmutable::parse('2026-10-21'), CarbonImmutable::parse('2026-11-03')],
        COOP_LEAFLET_URL,
    );

    expect($parser->lastStats())
        ->candidates->toBe(22)
        ->verified->toBe(18);
});
