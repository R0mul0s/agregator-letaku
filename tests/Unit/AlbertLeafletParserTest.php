<?php

/**
 * Dlaždice PDF letáku Albertu (R86) — strany 7 a 8 letáku hypermarketů 40/2026 a strana 14
 * (Mlynářské pečivo s aplikací): cena ověřená cenou za jednotku, sleva v procentech, cena
 * s aplikací, cena nad názvem, platnost z dlaždice a z oddílu „PLATÍ POUZE PÁ–NE“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Albert\AlbertLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

const ALBERT_PAGES_7_8 = 'albert/pdf-40hm-2026-10-06-strany-7-8.html';

const ALBERT_PAGE_URL = 'https://letaky.albert.cz/40hm_akcni_letak/page/%d';

/**
 * Ověřené akce z výstupu pdftotext podle názvu; platnost letáku 30. 9.–6. 10. 2026.
 *
 * @return array<string, OfferData>
 */
function albertLeafletOffers(string $xhtml): array
{
    $pages = app(PdfTextReader::class)->parse($xhtml);
    $validity = [CarbonImmutable::parse('2026-09-30'), CarbonImmutable::parse('2026-10-06')];

    $offers = [];
    foreach (app(AlbertLeafletParser::class)->offers($pages, $validity, ALBERT_PAGE_URL) as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('přijme cenu nad názvem, kterou ověří balení a cena za jednotku, s přeškrtnutou cenou', function (): void {
    $offers = albertLeafletOffers(responseFixture(ALBERT_PAGES_7_8));

    // „49“ „90“ leží nad „Fa / Deodorant sprej“; „150 ml • 100 ml = 33,27 Kč“ sedí jen k 49,90
    expect($offers['Fa Deodorant sprej'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(4990)
        ->originalPrice->toBe(6990)
        ->loyaltyPrice->toBeNull()
        ->packageText->toBe('150 ml')
        ->sourceUrl->toBe('https://letaky.albert.cz/40hm_akcni_letak/page/1')
        // Nejnižší cena za 30 dní („▼ 44,90 Kč“) do popisu nepatří
        ->description->toBe('150 ml • 100 ml = 33,27 Kč • vybrané druhy • platí do 13. 10. 2026')
        ->and($offers['Fa Deodorant sprej']->package?->quantity)->toBe(150.0)
        ->and($offers['Fa Deodorant sprej']->package?->unit)->toBe(PackageUnit::Milliliter);

    // Cena „59“ a „90“ jako dva bloky pod dlaždicí, balení „3× 50 g“ i „3 ks“
    expect($offers['Bref WC blok Power Aktiv'])
        ->price->toBe(5990)
        ->originalPrice->toBe(7990);
});

it('slevu v procentech ověří proti přeškrtnuté ceně a „1 ks od“ proti největšímu balení', function (): void {
    $offers = albertLeafletOffers(responseFixture(ALBERT_PAGES_7_8));

    // „1199,-/“ „- 50 %“ „599, -“ a „96–120 ks • 1 ks od 4,99 Kč“
    expect($offers['Pampers Dětské plenky/pants'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(59900)
        ->originalPrice->toBe(119900)
        ->discountPercent->toBe(50);
});

it('dlaždici, u které procento slevy nesedí na přeškrtnutou cenu, vynechá', function (): void {
    // „- 50 %“ u Pampers přepsané na „- 40 %“ (1199 → 599 je 50 %)
    $xhtml = str_replace('yMax="208.643220">50</word>', 'yMax="208.643220">40</word>', responseFixture(ALBERT_PAGES_7_8));

    expect(albertLeafletOffers($xhtml))->not->toHaveKey('Pampers Dětské plenky/pants')
        ->toHaveKey('Fa Deodorant sprej');
});

it('cenu s aplikací uloží jako cenu s kartou Můj Albert vedle ceny bez aplikace', function (): void {
    $offers = albertLeafletOffers(responseFixture(ALBERT_PAGES_7_8));

    // Velká cena 34,90 s aplikací, „BEZ APLIKACE 39 90“ pod ní, „1 l = 53,20 Kč bez Aplikace / 46,54 Kč Aplikace“
    expect($offers['Domestos WC čistič'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(3990)
        ->loyaltyPrice->toBe(3490)
        ->loyaltyProgram->toBe(LoyaltyProgram::MujAlbert)
        ->originalPrice->toBe(5990)
        ->discountPercent->toBeNull();

    // Běžná cena 39,90 = cena bez aplikace — jen cena s aplikací je nižší
    expect($offers['Bohemia Chips'])
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(3990)
        ->loyaltyPrice->toBe(2990)
        ->originalPrice->toBeNull();
});

it('bez menší ceny bez aplikace vezme jako běžnou cenu přeškrtnutou cenu', function (): void {
    $offers = albertLeafletOffers(responseFixture('albert/pdf-40hm-2026-10-06-strana-14.html'));

    // „49,90“ „- 16 %“ „41 90“ a „100 g = 9,98 Kč bez Aplikace / 8,38 Kč Aplikace“
    expect($offers['Mlynářský bochník žitný'])
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(4990)
        ->loyaltyPrice->toBe(4190);
});

it('neověřitelné dlaždice vynechá — bez ceny za jednotku nebo jen s balením 1 kg', function (): void {
    $offers = albertLeafletOffers(responseFixture(ALBERT_PAGES_7_8));

    expect(array_keys($offers))
        // Robotický vysavač nemá cenu za jednotku, mango „1 ks“ a slepice „1 kg“ ji nepotřebují
        ->not->toContain('Sencor Robotický vysavač SRV 7450WH', 'Mango', 'Slepice bez drobů', 'Jihlavanka Standard')
        ->toHaveCount(19);
});

it('„vybrané druhy“ dají variantu, platnost bere z dlaždice a z oddílu PÁ–NE', function (): void {
    $offers = albertLeafletOffers(responseFixture(ALBERT_PAGES_7_8));

    expect($offers['Nivea Sprchový gel'])
        ->variantNote->toBe('vybrané druhy')
        ->and($offers['Nivea Sprchový gel']->validFrom->toDateString())->toBe('2026-09-30')
        ->and($offers['Nivea Sprchový gel']->validTo->toDateString())->toBe('2026-10-06')
        // „platí do 13. 10. 2026“ v popisu
        ->and($offers['Fa Deodorant sprej']->validTo->toDateString())->toBe('2026-10-13')
        ->and($offers['Somat Tablety do myčky 4 v 1']->variantNote)->toBeNull();

    // Pod „PLATÍ POUZE PÁ–NE 2. 10. 2026 4. 10. 2026“
    expect($offers['Linteo Kuchyňské utěrky'])
        ->packageText->toBe('2 role')
        ->and($offers['Linteo Kuchyňské utěrky']->validFrom->toDateString())->toBe('2026-10-02')
        ->and($offers['Linteo Kuchyňské utěrky']->validTo->toDateString())->toBe('2026-10-04');
});

it('stejná dlaždice dá stejné externí ID bez ohledu na stranu letáku (R16)', function (): void {
    $pages = app(PdfTextReader::class)->parse(responseFixture(ALBERT_PAGES_7_8));
    $validity = [CarbonImmutable::parse('2026-09-30'), CarbonImmutable::parse('2026-10-06')];
    $parser = app(AlbertLeafletParser::class);

    $ids = fn (array $offers): array => array_map(fn (OfferData $offer): string => $offer->externalId, $offers);
    $first = $ids($parser->offers($pages, $validity, ALBERT_PAGE_URL));
    // Strany v opačném pořadí (jiné číslo strany) — ID se nezmění
    $reversed = $ids($parser->offers(array_reverse($pages), $validity, ALBERT_PAGE_URL));

    expect($first)->toHaveCount(19)
        ->and(array_unique($first))->toHaveCount(19)
        ->and($reversed)->toEqualCanonicalizing($first)
        ->and($first[0])->toStartWith('letak-');
});

it('akci na více kusů vynechá, i když cena sedí na cenu za jednotku', function (): void {
    $offers = albertLeafletOffers(responseFixture('albert/pdf-40sm-2026-10-06-strana-6.html'));

    // Lenor 79,90 jen „PŘI KOUPI 2 ks a více“ („cena za 1 ks 99,90 Kč“), 59 dávek po 1,36 Kč
    expect($offers)->not->toHaveKey('Lenor Aviváž')
        ->toHaveKey('Persil Prací gel');
});
