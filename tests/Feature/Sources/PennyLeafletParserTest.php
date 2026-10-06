<?php

/**
 * Parser vektorové vrstvy letáku Penny nad skutečnými stránkami — pravidla R26 a R85.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Penny\PennyLeafletParser;
use App\Domain\Sources\Penny\SvgTextReader;
use App\Domain\Sources\Penny\SvgToken;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;

/**
 * Tokeny stránky letáku z fixture `penny/page-….svg`.
 *
 * @return list<SvgToken>
 */
function pennyPageTokens(string $page): array
{
    return app(SvgTextReader::class)->tokens(responseFixture("penny/page-{$page}.svg"));
}

/**
 * Nabídky jedné stránky letáku.
 *
 * @param  list<array{float, float}>  $leafletOffsets
 * @return list<OfferData>
 */
function pennyPageOffers(string $page, array $leafletOffsets = []): array
{
    $validity = [CarbonImmutable::parse('2026-09-30'), CarbonImmutable::parse('2026-10-06')];

    return app(PennyLeafletParser::class)->offers(pennyPageTokens($page), $validity, 1, 'https://example.test/1/', $leafletOffsets);
}

/**
 * Jediná nabídka stránky podle začátku názvu.
 *
 * @param  list<OfferData>  $offers
 */
function pennyPageOffer(array $offers, string $name): OfferData
{
    $found = array_values(array_filter($offers, fn (OfferData $offer): bool => str_starts_with($offer->name, $name)));
    expect($found)->toHaveCount(1);

    return $found[0];
}

it('spojí balení a cenu za jednotku oddělené za „|“ s nezlomitelnou mezerou', function (): void {
    // „200 g |“ a „100g 19,95 Kč“ jsou dva tokeny na stejném účaří, každý v jiném sloupci
    expect(pennyPageOffer(pennyPageOffers('0004-2026-10-02'), 'ZRAJÍCÍ SÝR S BÍLOU PLÍSNÍ'))
        ->name->toBe('ZRAJÍCÍ SÝR S BÍLOU PLÍSNÍ NA POVRCHU MILKERIA')
        ->packageText->toBe('200 g')
        ->price->toBe(3990)
        ->originalPrice->toBe(4990);
});

it('přečte cenu za jednotku bez haléřů a rozdělenou na dva řádky', function (): void {
    // „1 kg 49 Kč“ k 4,90 Kč za 100 g
    expect(pennyPageOffer(pennyPageOffers('0001-2026-10-02'), 'PAŠTIKA PRO KOČKY'))
        ->packageText->toBe('100g')
        ->price->toBe(490);

    // „100g“ a „31,96 Kč“ pod sebou — balení je 250 g, ne 100 g
    expect(pennyPageOffer(pennyPageOffers('0022-2026-10-06'), 'KÁVA JACOBS VELVET'))
        ->packageText->toBe('250 g')
        ->price->toBe(7990)
        ->originalPrice->toBe(13490);
});

it('ověří varianty balení a ceny za jednotku', function (): void {
    // „70/80g“ + „100 g 24,14/21,13 Kč“ sedí k 16,90 Kč pro obě varianty
    expect(pennyPageOffer(pennyPageOffers('0017-2026-10-06'), 'DUPETKY'))
        ->packageText->toBe('70/80g')
        ->price->toBe(1690)
        ->originalPrice->toBe(2390);
});

it('blok bez ceny za jednotku přiřadí podle rozvržení ověřených dlaždic stránky', function (): void {
    // Sýr 100 g cenu za jednotku nemá; blok leží vůči ceně jako bloky ověřených sýrů na stránce
    $offer = pennyPageOffer(pennyPageOffers('0004-2026-10-02'), 'SÝR CHEDDAR 50% BONI plátky, světlý');

    expect($offer)
        ->packageText->toBe('100g')
        ->price->toBe(2390)
        ->originalPrice->toBe(3490)
        ->and($offer->package?->quantity)->toBe(100.0)
        ->and($offer->package?->unit)->toBe(PackageUnit::Gram);
});

it('stránku bez ověřených dlaždic přečte podle rozvržení celého letáku', function (): void {
    $parser = app(PennyLeafletParser::class);
    $offsets = [];
    foreach (['0001-2026-10-02', '0004-2026-10-02', '0005-2026-10-06', '0017-2026-10-06', '0022-2026-10-06', '0026-2026-10-06'] as $page) {
        array_push($offsets, ...$parser->tileOffsets(pennyPageTokens($page)));
    }

    // Zelenina na straně 2 má jen „cena za 1kg“ vedle ceny, stránka sama nic neověří
    expect(array_filter(pennyPageOffers('0002-2026-10-06'), fn (OfferData $offer): bool => str_starts_with($offer->name, 'KAPIE')))->toBeEmpty()
        ->and(pennyPageOffer(pennyPageOffers('0002-2026-10-06', $parser->commonOffsets($offsets)), 'KAPIE ČERVENÁ'))
        ->packageText->toBe('1 kg')
        ->price->toBe(4990)
        ->originalPrice->toBe(7990);
});

it('dvě sousední stejné ceny přiřadí každou jejímu bloku', function (): void {
    $offers = pennyPageOffers('0005-2026-10-06');

    // 34,90 Kč u Oreo (sleva 30 %) i u Skittles (41 %) — nejbližší dvojice by Oreo daly cizí přeškrtnutou cenu
    expect(pennyPageOffer($offers, 'SUŠENKY OREO'))->price->toBe(3490)->originalPrice->toBe(4990)
        ->and(pennyPageOffer($offers, 'SKITTLES'))->price->toBe(3490)->originalPrice->toBe(5990)
        ->and(pennyPageOffer($offers, 'MILKA JAFFA'))->price->toBe(2990)
        ->and(pennyPageOffer($offers, 'SUŠENKY OPAVIA'))->price->toBe(2990)->originalPrice->toBe(4990);
});

it('cenu s PENNY kartou uloží jako loyalty_price vedle běžné ceny', function (): void {
    // Velká cena 21,90 Kč s kartou, „cena bez pennykarty“ 39,90 Kč
    expect(pennyPageOffer(pennyPageOffers('0026-2026-10-06'), 'PYTLE NA ODPADKY CLINA'))
        ->offerType->toBe(OfferType::LoyaltyOnly)
        ->price->toBe(3990)
        ->loyaltyPrice->toBe(2190)
        ->loyaltyProgram->toBe(LoyaltyProgram::PennyKarta)
        ->originalPrice->toBeNull();
});

it('nadpis oddílu „MOJE PENNY KARTA“ z běžné dlaždice dlaždici s kartou neudělá', function (): void {
    expect(pennyPageOffer(pennyPageOffers('0017-2026-10-06'), 'KREKRY TUC'))
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(1690)
        ->loyaltyPrice->toBeNull();
});

it('cenu za více kusů uloží jako cenu kusu a výhodnou cenu v textu akce', function (): void {
    // „při koupi 1 ks cena 169,90 Kč od 2 ks cena 149,90 Kč“, velká cena 149,90 Kč
    expect(pennyPageOffer(pennyPageOffers('0001-2026-10-02'), 'JÄGERMEISTER'))
        ->offerType->toBe(OfferType::Multibuy)
        ->price->toBe(16990)
        ->promotionText->toBe('od 2 ks: 149,90'.PriceFormatter::NO_BREAK_SPACE.'Kč')
        ->originalPrice->toBeNull();
});

it('malé číslo bez přeškrtávací čáry uzná jako původní cenu jen se štítkem slevy', function (): void {
    $offers = pennyPageOffers('0001-2026-10-02');

    // Titulní strana čáru nemá; štítek 40 % sedí k 17,90 Kč z 29,90 Kč
    expect(pennyPageOffer($offers, 'LISTOVÉ TĚSTO'))
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(1790)
        ->originalPrice->toBe(2990)
        // Štítek 33 % k 9,90 Kč žádné malé číslo nemá — zůstane akční cena bez slevy
        ->and(pennyPageOffer($offers, 'ZAKYSANÁ SMETANA'))
        ->offerType->toBe(OfferType::PromoPrice)
        ->originalPrice->toBeNull();
});
