<?php

/**
 * Dlaždice PDF letáku Globusu (R88) — strany 1–3, 16, 32 a 41 letáku od 7. 10. 2026: cena ověřená
 * cenou za jednotku, sleva v procentech, cena s kartou Můj Globus (stín písma, „AC“ / „KC“),
 * platnost z hlavičky strany a ID akce shodné s otiskem z API.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Offers\Data\OfferData;
use App\Domain\Sources\Globus\GlobusLeafletKey;
use App\Domain\Sources\Globus\GlobusLeafletParser;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

const GLOBUS_PAGES_1_3 = 'globus/pdf-41-2026-10-06-strany-1-3.html';

const GLOBUS_OFFERS_URL = 'https://www.globus.cz/globus/hypermarket/akcni-nabidka';

/**
 * Ověřené akce z výstupu pdftotext podle názvu; platnost letáku 7.–13. 10. 2026.
 *
 * @return array<string, OfferData>
 */
function globusLeafletOffers(string $xhtml): array
{
    $pages = app(PdfTextReader::class)->parse($xhtml);
    $validity = [CarbonImmutable::parse('2026-10-07'), CarbonImmutable::parse('2026-10-13')];

    $offers = [];
    foreach (app(GlobusLeafletParser::class)->offers($pages, $validity, GLOBUS_OFFERS_URL) as $offer) {
        $offers[$offer->name] = $offer;
    }

    return $offers;
}

it('přijme cenu, kterou ověří balení a cena za jednotku, se slevou z přeškrtnuté ceny', function (): void {
    $offers = globusLeafletOffers(responseFixture(GLOBUS_PAGES_1_3));

    // „-37 %“ „31“ „90“ nad velkou cenou „19“ „90“; „chlazené • 300 g • 100 g = 6,63“
    expect($offers['VÁŠ VÝBĚR Listové těsto'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(1990)
        ->originalPrice->toBe(3190)
        ->discountPercent->toBe(37)
        ->loyaltyPrice->toBeNull()
        ->packageText->toBe('300 g')
        ->description->toBe('chlazené')
        ->sourceUrl->toBe(GLOBUS_OFFERS_URL)
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->package?->quantity)->toBe(300.0)
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->package?->unit)->toBe(PackageUnit::Gram)
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->validFrom->toDateString())->toBe('2026-10-07')
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->validTo->toDateString())->toBe('2026-10-13')
        // Akční cena bez štítku slevy není sleva (R8)
        ->and($offers['Horalky'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(1190)
        ->originalPrice->toBeNull()
        ->variantNote->toBe('různé druhy');
});

it('cenu s kartou Můj Globus uloží vedle běžné akční ceny podle „AC“ a „KC“', function (): void {
    $offers = globusLeafletOffers(responseFixture(GLOBUS_PAGES_1_3));

    // Velká cena se stínem „8“ „8“ „90“ „90“ = 8,90 s kartou („- 50 %“), pod ní „17“ „90“, „-44 %“
    // a běžná „9“ „90“; „AC: 100 g = 6,60 KC: 100 g = 5,93“ u 150 g
    expect($offers['Olma Florian'])
        ->offerType->toBe(OfferType::Discount)
        ->price->toBe(990)
        ->loyaltyPrice->toBe(890)
        ->loyaltyProgram->toBe(LoyaltyProgram::MujGlobus)
        ->originalPrice->toBe(1790)
        ->discountPercent->toBe(44)
        ->description->toBe('smetanový jogurt, různé druhy');

    // Běžná cena stejně velká pod cenou s kartou: „429“ „90“ nad „479“ „90“, „AC: 1 dávka = 4,80 KC: 1 dávka = 4,30“
    $page32 = globusLeafletOffers(responseFixture('globus/pdf-41-2026-10-06-strana-32.html'));
    expect($page32['Persil'])
        ->offerType->toBe(OfferType::PromoPrice)
        ->price->toBe(47990)
        ->loyaltyPrice->toBe(42990)
        ->packageText->toBe('100 dávek');
});

it('neověřitelné dlaždice vynechá — balení 1 l bez ceny za jednotku, pult za 100 g, restaurace', function (): void {
    $offers = globusLeafletOffers(responseFixture(GLOBUS_PAGES_1_3));

    expect(array_keys($offers))
        // „Mléko čerstvé / plnotučné 3,5% / 1 l“ ani „Tlačenka / 100 g“ cenu za jednotku nemají
        ->not->toContain('VÁŠ VÝBĚR Mléko čerstvé')
        ->not->toContain('Tlačenka světlá Globus')
        ->not->toContain('Smažený vepřový řízek z kotlety s bramborovým salátem')
        ->toHaveCount(16);
});

it('dlaždici, u které procento slevy nesedí na přeškrtnutou cenu, vynechá', function (): void {
    // „-37 %“ u listového těsta přepsané na „-30 %“ (31,90 → 19,90 je 37,6 %)
    $xhtml = str_replace('yMax="120.958800">-37</word>', 'yMax="120.958800">-30</word>', responseFixture(GLOBUS_PAGES_1_3));

    expect(globusLeafletOffers($xhtml))->not->toHaveKey('VÁŠ VÝBĚR Listové těsto')
        ->toHaveKey('VÁŠ VÝBĚR Gnocchi');
});

it('platnost vezme z hlavičky strany', function (): void {
    // „Platí od 7. 10. do 20. 10. 2026.“
    $page16 = globusLeafletOffers(responseFixture('globus/pdf-41-2026-10-06-strana-16.html'));
    // „od 30. 9. do 26. 10. 2026“ — akce s delší platností z minulého letáku
    $page41 = globusLeafletOffers(responseFixture('globus/pdf-41-2026-10-06-strana-41.html'));

    expect($page16['Veto Vegi Steak']->validTo->toDateString())->toBe('2026-10-20')
        ->and($page16['Veto Vegi Steak']->validFrom->toDateString())->toBe('2026-10-07')
        ->and($page41['After Eight']->validFrom->toDateString())->toBe('2026-09-30')
        ->and($page41['After Eight']->validTo->toDateString())->toBe('2026-10-26');
});

it('ID akce je otisk názvu a běžné ceny — stejný spočítá akce z API podle názvu v letáku', function (): void {
    $offers = globusLeafletOffers(responseFixture(GLOBUS_PAGES_1_3));
    $keys = app(GlobusLeafletKey::class);

    expect($offers['Olma Florian']->externalId)->toBe($keys->for('Olma Florian', 990))
        // Velikost písmen, diakritika a interpunkce otisk nemění
        ->and($keys->for('VÁŠ VÝBĚR Listové těsto', 1990))->toBe($keys->for('Váš Výběr listove testo', 1990))
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->externalId)->toBe($keys->for('Váš výběr Listové těsto', 1990))
        ->and($offers['VÁŠ VÝBĚR Listové těsto']->externalId)->not->toBe($keys->for('VÁŠ VÝBĚR Listové těsto', 1890));
});
