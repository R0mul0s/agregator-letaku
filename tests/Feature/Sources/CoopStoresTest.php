<?php

/**
 * Prodejny COOP (R169): seznam prodejen ze stránky /prodejny/ na coopclub.cz a u každé letáky, které
 * v ní platí, z oddílu „Letákové akce v této prodejně“ — po dávkách, nejdéle neobnovené první.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Chains\ChainCatalog;
use App\Domain\Sources\Coop\CoopStoreSource;
use App\Enums\Chain;
use App\Models\Store;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Falešné odpovědi coopclub.cz: seznam 4 prodejen a jejich stránky (Kladno má stejnou stránku jako
 * Václavice — leták SČ).
 */
function fakeCoopStores(): void
{
    Http::fake(function (Request $request) {
        $pages = [
            'https://www.coopclub.cz/prodejny/' => 'coop/prodejny-2026-10-25.html',
            'https://www.coopclub.cz/prodejny/jednota-vaclavice/' => 'coop/prodejna-jednota-vaclavice-2026-10-25.html',
            'https://www.coopclub.cz/prodejny/jednota-kladno/' => 'coop/prodejna-jednota-vaclavice-2026-10-25.html',
            'https://www.coopclub.cz/prodejny/terno-hradec-kralove/' => 'coop/prodejna-terno-hradec-kralove-2026-10-25.html',
            'https://www.coopclub.cz/prodejny/tuty-dvur-kralove-nad-labem/' => 'coop/prodejna-tuty-dvur-kralove-nad-labem-2026-10-25.html',
        ];

        return isset($pages[$request->url()]) ? Http::response(responseFixture($pages[$request->url()])) : Http::response('', 404);
    });
}

it('uloží prodejny COOP a u každé sledované letáky z její stránky', function (): void {
    fakeCoopStores();

    $this->artisan('letaky:import-stores', ['chain' => ['coop']])->assertSuccessful();

    $stores = Store::query()->where('chain', Chain::Coop)->get()->keyBy('code');
    expect($stores)->toHaveCount(4)
        ->and($stores['jednota-vaclavice'])
        ->name->toBe('Jednota Václavice')
        ->city->toBe('Václavice')
        ->offer_keys->toBe(['SČ'])
        // Terno v Hradci Králové patří družstvu z Českých Budějovic a žádný leták coopclub.cz nemá
        ->and($stores['terno-hradec-kralove']->offer_keys)->toBe([])
        // TUTY ve Dvoře Králové má jen obrázkový leták TUTY/TIP, ne regionální VČ
        ->and($stores['tuty-dvur-kralove-nad-labem']->offer_keys)->toBe([])
        ->and($stores['tuty-dvur-kralove-nad-labem']->offer_keys_fetched_at)->not->toBeNull();
});

it('leták VČ, na který žádná stránka prodejny neodkazuje, přiřadí podle družstva z patičky letáku', function (): void {
    $source = app(CoopStoreSource::class);

    // „COOP Pardubice, družstvo“ bez odkazu na leták = VČ; TUTY téhož kraje má odkaz na leták TUTY, takže ne
    expect($source->parseLeaflets(responseFixture('coop/prodejna-jednota-pardubice-2026-10-25.html'), 'jednota-pardubice'))->toBe(['VČ'])
        ->and($source->parseLeaflets(responseFixture('coop/prodejna-tuty-dvur-kralove-nad-labem-2026-10-25.html'), 'tuty-dvur-kralove-nad-labem'))->toBe([])
        // Terno v Hradci Králové: družstvo z Českých Budějovic, žádný leták
        ->and($source->parseLeaflets(responseFixture('coop/prodejna-terno-hradec-kralove-2026-10-25.html'), 'terno-hradec-kralove'))->toBe([]);
});

it('stránky prodejen stahuje po dávkách — nové a nejdéle neobnovené první', function (): void {
    fakeCoopStores();
    config(['letaky.sources.coop.store_lists_per_run' => 3]);

    $this->artisan('letaky:import-stores', ['chain' => ['coop']])->assertSuccessful();
    expect(Store::query()->where('chain', Chain::Coop)->whereNull('offer_keys_fetched_at')->count())->toBe(1);

    $this->travel(1)->hours();
    $this->artisan('letaky:import-stores', ['chain' => ['coop']])->assertSuccessful();
    // Druhý běh vezme tu, která chyběla, a dvě nejstarší
    expect(Store::query()->where('chain', Chain::Coop)->whereNull('offer_keys_fetched_at')->count())->toBe(0)
        ->and(Store::query()->where('chain', Chain::Coop)->where('offer_keys_fetched_at', '>=', now()->subMinute())->count())->toBe(3);
});

it('prodejnu bez sledovaného letáku označí ve výběru poznámkou', function (): void {
    fakeCoopStores();
    $this->artisan('letaky:import-stores', ['chain' => ['coop']])->assertSuccessful();

    $stores = collect(app(ChainCatalog::class)->stores(Chain::Coop))->keyBy('code');
    expect($stores['terno-hradec-kralove']['note'] ?? null)->toBe('žádný z letáků, které sledujeme')
        ->and($stores['jednota-vaclavice'])->not->toHaveKey('note');
});
