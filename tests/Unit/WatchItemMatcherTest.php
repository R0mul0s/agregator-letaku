<?php

/**
 * Párování hlídané položky s nabídkou podle slov (R18) a stav „možná“ (R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Matching\PetFood;
use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchItemMatcher;
use App\Domain\Matching\WatchRule;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\WatchItem;

/**
 * Stav shody pravidel a nabídky (modely se neukládají).
 *
 * @param  array<string, string|null>  $rule
 * @param  array<string, string|null>  $offer
 */
function matchOffer(array $rule, array $offer): ?MatchStatus
{
    $normalizer = new TextNormalizer;
    $item = (new WatchItem)->forceFill([
        'keywords' => $rule['keywords'],
        'variant_keywords' => $rule['variant'] ?? null,
        'exclude_keywords' => $rule['exclude'] ?? null,
    ]);
    $model = (new Offer)->forceFill([
        'name' => $offer['name'],
        'brand' => $offer['brand'] ?? null,
        'description' => $offer['description'] ?? null,
        'variant_note' => $offer['variant_note'] ?? null,
    ]);

    return (new WatchItemMatcher($normalizer, new PetFood))->match(WatchRule::fromWatchItem($item, $normalizer), $model);
}

it('najde slovo bez ohledu na diakritiku, velikost písmen a koncovku', function (string $name): void {
    expect(matchOffer(['keywords' => 'vejce'], ['name' => $name]))->toBe(MatchStatus::Match);
})->with(['Čerstvá VEJCE M20', 'Toustový chléb s vejcem']);

it('hledá od začátku slova, ne uprostřed', function (): void {
    expect(matchOffer(['keywords' => 'cola'], ['name' => 'Pepsicola 1,5 l']))->toBeNull()
        ->and(matchOffer(['keywords' => 'coca cola'], ['name' => 'Coca-Cola Zero 500ml']))->toBe(MatchStatus::Match);
});

it('vyžaduje všechna slova a hledá i ve značce a popisu', function (): void {
    expect(matchOffer(['keywords' => 'coca cola'], ['name' => 'RC Cola 1,5 l']))->toBeNull()
        ->and(matchOffer(['keywords' => 'mléko polotučné'], ['name' => 'Kunín Trvanlivé mléko tuk 1,5 %', 'description' => 'Kunín polotučné tuk 1,5 %']))->toBe(MatchStatus::Match)
        ->and(matchOffer(['keywords' => 'tatra máslo'], ['name' => 'Máslo 250 g', 'brand' => 'TATRA']))->toBe(MatchStatus::Match);
});

it('slovo s alternativami stačí najít v jedné z nich', function (): void {
    $rule = ['keywords' => 'mléko polotučné|1,5'];

    expect(matchOffer($rule, ['name' => 'Kunín Trvanlivé mléko tuk 1,5 %']))->toBe(MatchStatus::Match)
        ->and(matchOffer($rule, ['name' => 'Tesco Mléko UHT polotučné 1l']))->toBe(MatchStatus::Match)
        ->and(matchOffer($rule, ['name' => 'Selské mléko plnotučné 3,9 %']))->toBeNull();
});

it('vyloučené slovo nabídku vyřadí, i jako začátek delšího slova', function (): void {
    $rule = ['keywords' => 'máslo', 'exclude' => 'máslov arašíd'];

    expect(matchOffer($rule, ['name' => 'Tatra Máslo']))->toBe(MatchStatus::Match)
        ->and(matchOffer($rule, ['name' => 'Arašídové máslo']))->toBeNull()
        ->and(matchOffer($rule, ['name' => 'Omega rostlinný tuk máslová příchuť']))->toBeNull();
});

it('chybějící variantu u souhrnné nabídky označí jako možnou shodu', function (): void {
    $rule = ['keywords' => 'coca cola', 'variant' => 'zero'];

    expect(matchOffer($rule, ['name' => 'Coca-Cola Zero 500ml']))->toBe(MatchStatus::Match)
        ->and(matchOffer($rule, ['name' => 'Coca-Cola Limonáda', 'variant_note' => 'různé druhy']))->toBe(MatchStatus::Maybe)
        ->and(matchOffer($rule, ['name' => 'Coca-Cola Original 1,5 l']))->toBeNull();
});

it('bez hledaných slov nenajde nic', function (): void {
    expect(matchOffer(['keywords' => ' | '], ['name' => 'Cokoliv']))->toBeNull();
});

/**
 * Stav zmínky pravidel na stránce letáku (R27).
 *
 * @param  array<string, string|null>  $rule
 */
function mentionOnPage(array $rule, string $pageText): ?MatchStatus
{
    $normalizer = new TextNormalizer;
    $item = (new WatchItem)->forceFill([
        'keywords' => $rule['keywords'],
        'variant_keywords' => $rule['variant'] ?? null,
        'exclude_keywords' => $rule['exclude'] ?? null,
    ]);

    return (new WatchItemMatcher($normalizer, new PetFood))->mention(WatchRule::fromWatchItem($item, $normalizer), $normalizer->normalize($pageText));
}

it('zmínku na stránce letáku hledá jen jako celé slovo', function (): void {
    // Skutečné stránky Lidlu 8. 10. 2026 (keyWords)
    expect(mentionOnPage(['keywords' => 'máslo'], 'Velkopopovický Kozel 05 2997 Máslo 9960 Super'))->toBe(MatchStatus::Match)
        ->and(mentionOnPage(['keywords' => 'máslo'], 'Dýně Máslová -28% Sweet Dumpling'))->toBeNull();
});

it('vyloučená slova u zmínky použije jen v okolí slova — stránka je směs produktů (R107)', function (): void {
    $rule = ['keywords' => 'vejce', 'exclude' => 'toust'];

    // „Toustový chléb“ pět slov za vejci patří jinému produktu
    expect(mentionOnPage($rule, 'Vejce 39% Kuřecí řízky 199 Toustový chléb'))->toBe(MatchStatus::Match);

    // Skutečné stránky Lidlu 12. 10. a Penny 7. 10. 2026: banány v čokoládě, ne ovoce
    $bananas = ['keywords' => 'banány', 'exclude' => 'příchu orion tyčink oplatk svačink'];
    expect(mentionOnPage($bananas, 'Margot 3136 Orion Ledové Kaštany Kofila Banány V Čokoládě'))->toBeNull()
        ->and(mentionOnPage($bananas, 'TYČINKY ORION OPAVIA Kofila, Banány Milena, Koko'))->toBeNull()
        // Stačí jeden výskyt bez vyloučeného slova v okolí
        ->and(mentionOnPage($bananas, 'Orion Kofila Banány v čokoládě Pečivo Chléb Rohlíky Nová Sklizeň Pomelo Banány -50%'))->toBe(MatchStatus::Match);
});

it('zmínku bez varianty označí jako možnou', function (): void {
    $rule = ['keywords' => 'coca cola', 'variant' => 'zero'];

    expect(mentionOnPage($rule, 'Cappy Pulpy Pomeranč Coca-Cola 175 1709 -14%'))->toBe(MatchStatus::Maybe)
        ->and(mentionOnPage($rule, 'Lidl Plus 28% Coca-Cola Zero 175 1427'))->toBe(MatchStatus::Match)
        ->and(mentionOnPage($rule, 'Kinder Bueno Mini'))->toBeNull();
});
