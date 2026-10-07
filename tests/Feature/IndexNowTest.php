<?php

/**
 * IndexNow (R105): ohlášení změněných stránek vyhledávačům po stažení obchodu a klíč na /{klíč}.txt.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Domain\Offers\ChangedOfferPages;
use App\Domain\Offers\OfferPages;
use App\Enums\Chain;
use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\ScrapeRun;
use App\Support\Seo\IndexNow;
use App\Support\Seo\SeoMeta;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const INDEXNOW_KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/*';

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    config(['letaky.indexnow.key' => INDEXNOW_KEY, 'letaky.cron.token' => 'test-cron-token']);
});

/**
 * Podvrhne Kaufland z fixture a IndexNow, které ohlášení přijme.
 */
function fakeKauflandAndIndexNow(): void
{
    Http::fake([
        'https://prodejny.kaufland.cz/*' => Http::response(responseFixture('kaufland/prehled-2026-10-02.html')),
        INDEXNOW_ENDPOINT => Http::response('', 202),
    ]);
}

/**
 * Stáhne Kaufland přes cron URL.
 */
function importKauflandWithIndexNow(): void
{
    test()->get(route('cron.import-offers', ['chain' => 'kaufland', 'token' => 'test-cron-token']))->assertOk();
}

/**
 * Požadavky odeslané na IndexNow.
 *
 * @return list<Request>
 */
function indexNowRequests(): array
{
    return Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.indexnow.org/'))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

it('na produkci po stažení ohlásí úvodní stránku, Všechny akce a stránku obchodu', function (): void {
    $this->app['env'] = 'production';

    fakeKauflandAndIndexNow();
    importKauflandWithIndexNow();

    $requests = indexNowRequests();
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->method())->toBe('POST')
        ->and($requests[0]['host'])->toBe(parse_url(config('app.url'), PHP_URL_HOST))
        ->and($requests[0]['key'])->toBe(INDEXNOW_KEY)
        ->and($requests[0]['keyLocation'])->toBe(url('/'.INDEXNOW_KEY.'.txt'))
        ->and($requests[0]['urlList'])->toBe([
            SeoMeta::homeUrl(),
            route('offers'),
            app(OfferPages::class)->chainUrl(Chain::Kaufland, absolute: true),
        ]);
});

it('stejné stránky znovu neohlásí — podruhé se nic nezměnilo a adresy čekají na interval', function (): void {
    $this->app['env'] = 'production';

    fakeKauflandAndIndexNow();
    importKauflandWithIndexNow();
    importKauflandWithIndexNow();

    expect(indexNowRequests())->toHaveCount(1);
});

it('přijatou adresu ohlásí znovu až po intervalu', function (): void {
    $this->app['env'] = 'production';
    Http::fake([INDEXNOW_ENDPOINT => Http::response('', 200)]);
    $indexNow = app(IndexNow::class);

    $indexNow->submit([route('offers')]);
    $indexNow->submit([route('offers')]);
    $this->travel(config()->integer('letaky.indexnow.min_interval_hours'))->hours();
    $indexNow->submit([route('offers')]);

    expect(indexNowRequests())->toHaveCount(2);
});

it('mimo produkci a bez platného klíče nic neohlašuje', function (?string $key, string $env): void {
    config(['letaky.indexnow.key' => $key]);
    $this->app['env'] = $env;

    fakeKauflandAndIndexNow();
    importKauflandWithIndexNow();

    expect(indexNowRequests())->toBe([]);
})->with([
    'vývoj' => [INDEXNOW_KEY, 'local'],
    'bez klíče' => [null, 'production'],
    'klíč s nepovolenými znaky' => ['kratky!', 'production'],
]);

it('odmítnuté ohlášení zapíše do logu a adresy příště zkusí znovu', function (): void {
    $this->app['env'] = 'production';
    Http::fake([INDEXNOW_ENDPOINT => Http::response('', 403)]);
    $indexNow = app(IndexNow::class);

    $indexNow->submit([url('/akce')]);
    $indexNow->submit([url('/akce')]);

    expect(indexNowRequests())->toHaveCount(2);
});

it('ke změněným stránkám přidá produkty katalogu s novou nebo staženou akcí', function (): void {
    $run = ScrapeRun::start(Chain::Lidl);
    $butter = Product::factory()->create(['name' => 'Máslo']);
    $beer = Product::factory()->create(['name' => 'Pivo']);
    $new = Offer::factory()->create(['chain' => Chain::Lidl, 'scrape_run_id' => $run->id, 'name' => 'Máslo Pilos']);
    OfferProduct::query()->create(['offer_id' => $new->id, 'product_id' => $butter->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    // Pivo má jen akci staženou obchodem — stránka produktu zmizí ze sitemapy, neohlašuje se
    $withdrawn = Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Pivo Pilsner', 'withdrawn_at' => now()]);
    OfferProduct::query()->create(['offer_id' => $withdrawn->id, 'product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    $pages = app(OfferPages::class);
    expect(app(ChangedOfferPages::class)->forRun($run))->toBe([
        SeoMeta::homeUrl(),
        route('offers'),
        $pages->chainUrl(Chain::Lidl, absolute: true),
        $pages->productUrl($butter->id, absolute: true),
    ]);

    // Stažení, které nic nového nepřineslo
    $this->travel(1)->hours();
    expect(app(ChangedOfferPages::class)->forRun(ScrapeRun::start(Chain::Lidl)))->toBe([]);
});

it('klíč vystaví na /{klíč}.txt, jiný název je 404', function (): void {
    $this->get('/'.INDEXNOW_KEY.'.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent(INDEXNOW_KEY);

    $this->get('/0000000000000000.txt')->assertNotFound();
    // Ostatní textové soubory pro roboty dál fungují
    $this->get('/robots.txt')->assertOk();
    $this->get('/llms.txt')->assertOk();
});
