<?php

/**
 * Dlaždice PDF letáku Hrušky (R165) — strany 1, 4, 6 a 12 letáku 43/26 (21.–27. 10. 2026) a strana 1
 * letáku 44/26: cena ověřená cenou za jednotku, klubová cena, značka nad názvem, text zarovnaný
 * vpravo, ovoce a pult „1 kg“ podle polohy, rámeček „POUZE“ a sloupce s názvy dnů. Strany 1 a 3
 * letáku 46/26 (11.–17. 11. 2026) mají jiné výšky písma a klubovou cenu stejně velkou (R183).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Hruska\HruskaLayout;
use App\Domain\Sources\Hruska\HruskaLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

const HRUSKA_OFFERS_URL = 'https://mojehruska.cz/';

/**
 * Ověřené akce z výstupu pdftotext podle názvu; platnost letáku 21.–27. 10. 2026.
 *
 * @return array<string, OfferData>
 */
function hruskaLeafletOffers(string $fixture): array
{
    $pages = app(PdfTextReader::class)->parse(responseFixture($fixture));
    $validity = [CarbonImmutable::parse('2026-10-21'), CarbonImmutable::parse('2026-10-27')];

    $offers = [];
    foreach (app(HruskaLeafletParser::class)->offers($pages, $validity, HRUSKA_OFFERS_URL) as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('přečte platnost letáku z hlavičky první strany', function (): void {
    $parser = app(HruskaLeafletParser::class);
    $reader = app(PdfTextReader::class);

    // „Platnost: 21. 10.–27. 10. 2026 | 43/26“; leták 44/26 začíná ve čtvrtek (28. 10. je zavřeno)
    expect(array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $parser->validity($reader->parse(responseFixture('hruska/pdf-43-2026-10-25-strana-1.html'))) ?? []))
        ->toBe(['2026-10-21', '2026-10-27'])
        ->and(array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $parser->validity($reader->parse(responseFixture('hruska/pdf-44-2026-10-25-strana-1.html'))) ?? []))
        ->toBe(['2026-10-29', '2026-11-03'])
        // Strana bez hlavičky
        ->and($parser->validity($reader->parse(responseFixture('hruska/pdf-43-2026-10-25-strana-12.html'))))->toBeNull();
});

it('přijme cenu, kterou ověří balení a cena za jednotku, jako akční cenu bez slevy', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html');

    // „NAŠE CENA“ „16“ „90“ nad „Eidam sýr 45%“, „100 g“, „1 kg = 169,00 Kč“
    expect($offers['Eidam sýr 45%'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(1690)
        ->originalPrice->toBeNull()
        ->loyaltyPrice->toBeNull()
        ->packageText->toBe('100 g')
        ->sourceUrl->toBe(HRUSKA_OFFERS_URL)
        ->and($offers['Eidam sýr 45%']->package?->quantity)->toBe(100.0)
        ->and($offers['Eidam sýr 45%']->package?->unit)->toBe(PackageUnit::Gram)
        ->and($offers['Eidam sýr 45%']->validFrom->toDateString())->toBe('2026-10-21')
        ->and($offers['Eidam sýr 45%']->validTo->toDateString())->toBe('2026-10-27')
        // Text zarovnaný vpravo k obrázku: „vybrané druhy | 0,5 l | sklo“ = balení a popis
        ->and($offers['Velkopopovický Kozel 11'])
        ->price->toBe(1790)
        ->packageText->toBe('0,5 l')
        ->description->toBe('vybrané druhy, sklo')
        ->variantNote->toBe('vybrané druhy')
        // Části názvu oddělené „|“
        ->and($offers)->toHaveKey('Milena, Koko, Sojové řezy');
});

it('značku nad názvem uloží jako značku i na začátek názvu', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html');

    expect($offers['Bivoj Vysočina'])
        ->brand->toBe('Bivoj')
        ->price->toBe(1890)
        ->and($offers['Sametový jogurt']->brand)->toBeNull();
});

it('klubovou cenu uloží vedle běžné, ověřené cenou za jednotku s klubem a bez něj', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html');

    // „KLUBOVÁ CENA“ 21,90, pod ní „NAŠE CENA“ 22,90; „1 kg = 219,00 Kč s klubem/ 229,00 Kč bez klubu“
    expect($offers['Prantl Mistrovská šunka nejvyšší jakosti'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(2290)
        ->loyaltyPrice->toBe(2190)
        ->loyaltyProgram->toBe(LoyaltyProgram::HruskaKlub)
        // Klubová cena „od“ i u běžné ceny („od 152,67 Kč bez klubu“)
        ->and(hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-6.html')['Rybí salát'])
        ->price->toBe(2290)
        ->loyaltyPrice->toBe(2090);
});

it('dlaždici z rámečku POUZE s cenou jednoho dne vynechá', function (): void {
    // Smetana ke šlehání 26,90 jen ve středu, „ČTVRTEK–ÚTERÝ 29,90“; „1 kg = 134,50 Kč/149,50 Kč“
    expect(hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html'))->not->toHaveKey('Smetana ke šlehání 31%')
        // Zlatopramen 36,90 jen ve čtvrtek, „PÁTEK–ÚTERÝ 42,90“
        ->and(hruskaLeafletOffers('hruska/pdf-44-2026-10-25-strana-1.html'))->not->toHaveKey('Zlatopramen 11 MAXI');
});

it('ovoce, zeleninu a pult „1 kg“ přijme jen s cenovkou těsně nad názvem', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-4.html');

    expect($offers['Pórek'])
        ->price->toBe(3990)
        ->packageText->toBe('1 kg')
        ->and($offers['Pórek']->package?->quantity)->toBe(1000.0)
        ->and($offers['Květák']->price)->toBe(2990)
        ->and($offers['Květák']->package?->unit)->toBe(PackageUnit::Piece)
        // Klubová cena u zboží „1 kg“: běžná cena pod ní
        ->and(hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-6.html')['Hranolky Sunny Fries'])
        ->price->toBe(5990)
        ->loyaltyPrice->toBe(5490);
});

it('dlaždice pod sloupci s názvy dnů platí jen v ty dny', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-12.html');

    // „SUPER PÁTEK SOBOTA NEDĚLE“ vlevo, „EXTRA PONDĚLÍ ÚTERÝ“ vpravo
    expect($offers['Sádlo vepřové']->validFrom->toDateString())->toBe('2026-10-23')
        ->and($offers['Sádlo vepřové']->validTo->toDateString())->toBe('2026-10-25')
        ->and($offers['Smetanito']->validFrom->toDateString())->toBe('2026-10-26')
        ->and($offers['Smetanito']->validTo->toDateString())->toBe('2026-10-27')
        // „5×75 g“ s cenou za kus („1 ks = 25,98 Kč“)
        ->and($offers['Chlebíček šunkový']->price)->toBe(12990);
});

it('počítá nalezené a ověřené ceny letáku (R129)', function (): void {
    $parser = app(HruskaLeafletParser::class);
    $parser->offers(
        app(PdfTextReader::class)->parse(responseFixture('hruska/pdf-43-2026-10-25-strana-4.html')),
        [CarbonImmutable::parse('2026-10-21'), CarbonImmutable::parse('2026-10-27')],
        HRUSKA_OFFERS_URL,
    );

    expect($parser->lastStats())
        ->candidates->toBe(10)
        ->verified->toBe(10);
});

it('stejný název, balení a cena dají při dalším stažení stejné ID (R16)', function (): void {
    $first = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html');
    $second = hruskaLeafletOffers('hruska/pdf-43-2026-10-25-strana-1.html');

    expect($first['Tatranky']->externalId)->toBe($second['Tatranky']->externalId)
        ->toStartWith('letak-')
        ->not->toBe($first['Donut Halloween']->externalId);
});

it('pozná rozvržení letáku podle výšky cen za jednotku (R183)', function (): void {
    $parser = app(HruskaLeafletParser::class);
    $reader = app(PdfTextReader::class);

    // 43/26: cena za jednotku 7,3 b., klubová cena větší než cenovka; 46/26: 7,8 b., stejně velká
    expect($parser->layout($reader->parse(responseFixture('hruska/pdf-43-2026-10-25-strana-1.html'))))->toEqual(HruskaLayout::original())
        ->and($parser->layout($reader->parse(responseFixture('hruska/pdf-46-2026-11-12-strana-1.html'))))->toEqual(HruskaLayout::web());
});

it('převede leták 46/26 s novými výškami písma a klubovou cenou stejně velkou jako cenovka (R183)', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-46-2026-11-12-strana-1.html');

    // „NAŠE CENA“ „44“ „90“, vpravo od ní „Máslo“, balení „250 g“ písmem ceny za jednotku, „1 kg = 179,60 Kč“
    expect($offers['Máslo'])
        ->price->toBe(4490)
        ->loyaltyPrice->toBeNull()
        ->packageText->toBe('250 g')
        // „12“ „90“ se štítkem „S KLUBEM“ a pod ní menší „14“ „90“ — klubová pozná běžná cena u ní
        ->and($offers['Mouka pšeničná'])
        ->price->toBe(1490)
        ->loyaltyPrice->toBe(1290)
        ->loyaltyProgram->toBe(LoyaltyProgram::HruskaKlub)
        ->and($offers)->toHaveCount(14);
});

it('u letáku 46/26 nechá značku na začátku názvu — má stejně velké písmo (R183)', function (): void {
    $offers = hruskaLeafletOffers('hruska/pdf-46-2026-11-12-strana-3.html');

    // „Kostelecké uzeniny“ nad „Javořické párky*“, „1 kg = 109,00 Kč s klubem/ 119,00 Kč bez klubu“
    expect($offers['Kostelecké uzeniny Javořické párky'])
        ->brand->toBeNull()
        ->price->toBe(1190)
        ->loyaltyPrice->toBe(1090)
        ->and($offers)->toHaveCount(11);
});
