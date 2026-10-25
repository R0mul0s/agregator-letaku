<?php

/**
 * Dlaždice PDF letáku Normy (R166) — strany 1, 3, 6 a 7 letáku 2026-44 (26. 10.–1. 11. 2026) a strana 4
 * letáku 2026-45: natočená cena složená ze znaků, ověření cenou za jednotku v závorce, sleva
 * s přeškrtnutou cenou, balení v názvu, platnost víkendové strany.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Norma\NormaLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

const NORMA_OFFERS_URL = 'https://www.norma-online.de/cz/angebote/';

/**
 * Ověřené akce z výstupu pdftotext podle názvu; platnost letáku je týden po–ne.
 *
 * @return array<string, OfferData>
 */
function normaLeafletOffers(string $fixture, string $monday = '2026-10-26'): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture($fixture));
    $start = CarbonImmutable::parse($monday);

    $offers = [];
    foreach (app(NormaLeafletParser::class)->offers($pages, [$start, $start->addDays(6)], NORMA_OFFERS_URL) as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('složí natočenou cenu ze znaků a ověří ji cenou za jednotku v závorce', function (): void {
    $offers = normaLeafletOffers('norma/pdf-2026-44-2026-10-25-strana-6.html');

    // „6“ „9“ „,“ „9“ „0“ jako samostatná slova; „• 1,5 l“ / „(1 l 10,60)“
    expect($offers['Mattoni s příchutí mango alphonso'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(1590)
        ->packageText->toBe('1,5 l')
        ->description->toBe('perlivá')
        ->sourceUrl->toBe(NORMA_OFFERS_URL)
        ->and($offers['Mattoni s příchutí mango alphonso']->package?->quantity)->toBe(1500.0)
        ->and($offers['Mattoni s příchutí mango alphonso']->package?->unit)->toBe(PackageUnit::Milliliter)
        ->and($offers['Mattoni s příchutí mango alphonso']->validFrom->toDateString())->toBe('2026-10-26')
        ->and($offers['Mattoni s příchutí mango alphonso']->validTo->toDateString())->toBe('2026-11-01')
        // Znaky „, 9“ jedním slovem s mezerou a menší cenovka dole na straně
        ->and($offers['Perníčky sáček']->price)->toBe(7990)
        // Ligatura „ﬁ“ a slovo rozdělené na konci řádku
        ->and($offers)->toHaveKey('Activia Jogurtový nápoj s bifidokulturou');
});

it('slevu uloží s přeškrtnutou cenou, jen když sedí procento', function (): void {
    $offers = normaLeafletOffers('norma/pdf-2026-44-2026-10-25-strana-6.html');

    // „114,9“ „0“ (poslední nula menším písmem) a „levnější o 13 %“
    expect($offers['Primona Prací gel'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(9990)
        ->originalPrice->toBe(11490)
        ->discountPercent->toBe(13)
        // Procento natočené po číslicích „2“ „5“ „%“ vedle ceny „5,9“ „0“
        ->and(normaLeafletOffers('norma/pdf-2026-44-2026-10-25-strana-3.html')['Jogurt bílý'])
        ->price->toBe(590)
        ->originalPrice->toBe(790)
        ->discountPercent->toBe(25);
});

it('vezme balení i z posledního řádku názvu a z názvu ho odebere', function (): void {
    $offers = normaLeafletOffers('norma/pdf-2026-44-2026-10-25-strana-1.html');

    // „Pepsi“ / „Mirinda“ / „2,25 l“ velkým písmem, „(1 l 13,29)“
    expect($offers['Pepsi Mirinda'])
        ->price->toBe(2990)
        ->packageText->toBe('2,25 l')
        ->variantNote->toBe('různé druhy')
        // Dvě ceny za jednotku: celková hmotnost a pevný podíl „670 g / PP 360 g (100 g 3,72/6,92)“
        ->and($offers['Okurky'])
        ->price->toBe(2490)
        ->packageText->toBe('670 g')
        ->description->toBe('PP 360 g');
});

it('víkendové strany platí od čtvrtka podle hlavičky strany', function (): void {
    $offers = normaLeafletOffers('norma/pdf-2026-44-2026-10-25-strana-7.html');

    // „od čtvrtka 29. října do neděle 1. listopadu“
    expect($offers['Kozel 10 plech']->validFrom->toDateString())->toBe('2026-10-29')
        ->and($offers['Kozel 10 plech']->validTo->toDateString())->toBe('2026-11-01')
        ->and($offers['Kuřecí stehenní řízky'])
        ->price->toBe(8990)
        ->originalPrice->toBe(11990)
        ->discountPercent->toBe(25);
});

it('přečte i leták s popisem menším písmem', function (): void {
    $offers = normaLeafletOffers('norma/pdf-2026-45-2026-10-25-strana-4.html', '2026-11-02');

    expect($offers['Kostelecké uzeniny Horal salám'])
        ->price->toBe(7990)
        ->originalPrice->toBe(9490)
        ->and($offers['Kostelecké uzeniny Horal salám']->validFrom->toDateString())->toBe('2026-11-02');
});

it('oblečení a zboží bez ceny za jednotku neuloží a počítá nalezené ceny (R129)', function (): void {
    $parser = app(NormaLeafletParser::class);
    $offers = $parser->offers(
        app(PdfTextReader::class)->parse(responseFixture('norma/pdf-2026-44-2026-10-25-strana-3.html')),
        [CarbonImmutable::parse('2026-10-26'), CarbonImmutable::parse('2026-11-01')],
        NORMA_OFFERS_URL,
    );

    // Horní polovina strany je textil a domácnost („Mikrovláknové flanelové povlečení“ 499,90)
    expect(array_map(fn (OfferData $offer): int => $offer->price ?? 0, $offers))->not->toContain(49990)
        ->and($parser->lastStats())
        ->candidates->toBe(22)
        ->verified->toBe(9);
});
