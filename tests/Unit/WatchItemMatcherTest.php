<?php

/**
 * Párování hlídané položky s nabídkou podle slov (R18) a stav „možná“ (R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

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

    return (new WatchItemMatcher($normalizer))->match(WatchRule::fromWatchItem($item, $normalizer), $model);
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
