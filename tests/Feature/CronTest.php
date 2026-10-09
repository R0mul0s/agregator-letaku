<?php

/**
 * Cron URL pro produkci (R20, R38) a hlídání stahování pro monitoring.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\Category;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const CRON_TOKEN = 'test-cron-token';

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
    config(['letaky.cron.token' => CRON_TOKEN]);
});

it('bez správného tokenu cron URL neexistuje', function (?string $configured, ?string $given): void {
    config(['letaky.cron.token' => $configured]);

    $this->get(route('cron.import-offers', array_filter(['chain' => 'kaufland', 'token' => $given])))->assertNotFound();
    Http::assertNothingSent();
})->with([
    'špatný token' => [CRON_TOKEN, 'jiny'],
    'bez tokenu' => [CRON_TOKEN, null],
    'nenastavený token' => [null, CRON_TOKEN],
    'prázdný token' => ['', ''],
]);

it('stáhne akce zvoleného obchodu a vrátí souhrn jako text', function (): void {
    Http::fake(['https://prodejny.kaufland.cz/*' => Http::response(responseFixture('kaufland/prehled-2026-10-02.html'))]);

    $this->get(route('cron.import-offers', ['chain' => 'kaufland', 'token' => CRON_TOKEN]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSeeText('Kaufland — uloženo nabídek: 7');

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Succeeded);
});

it('obchod je povinný a musí mít zdroj; chyba zdroje je 500', function (): void {
    // Chybný parametr je 422 jako text, ne přesměrování — to by cron viděl jako úspěch (R113)
    $this->get(route('cron.import-offers', ['token' => CRON_TOKEN]))
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    $this->get(route('cron.import-offers', ['chain' => 'makro', 'token' => CRON_TOKEN]))->assertUnprocessable();
    $this->get(route('cron.import-stores', ['token' => CRON_TOKEN]))->assertUnprocessable();

    Http::fake(['https://prodejny.kaufland.cz/*' => Http::response('<html></html>')]);
    $this->get(route('cron.import-offers', ['chain' => 'kaufland', 'token' => CRON_TOKEN]))
        ->assertInternalServerError()
        ->assertSeeText('Kaufland — chyba');

    expect(ScrapeRun::query()->sole()->status)->toBe(ScrapeStatus::Failed);
});

it('stáhne strom kategorií', function (): void {
    Http::fake(['https://xapi.tesco.com/*' => Http::response(responseFixture('tesco/taxonomy-2026-10-02.json'))]);

    $this->get(route('cron.import-categories', ['token' => CRON_TOKEN]))
        ->assertOk()
        ->assertSeeText('Kategorie — uloženo: 54');

    expect(Category::query()->count())->toBe(54);
});

it('volání cronu nezakládá relaci (R113)', function (): void {
    config(['session.driver' => 'database']);

    $this->get(route('cron.prune-sessions', ['token' => CRON_TOKEN]))->assertOk();

    expect(DB::table('sessions')->count())->toBe(0);
});

it('souběžné stažení akcí, prodejen ani kategorií nespustí a odpoví 409 (R57, R113)', function (string $route, array $query, string $lock): void {
    $running = Cache::lock($lock, 300);
    $running->get();

    $this->get(route($route, [...$query, 'token' => CRON_TOKEN]))
        ->assertConflict()
        ->assertSeeText('Úloha už běží');
    Http::assertNothingSent();

    $running->release();
})->with([
    'akce' => ['cron.import-offers', ['chain' => 'kaufland'], 'import-offers:kaufland'],
    'prodejny' => ['cron.import-stores', ['chain' => 'kaufland'], 'cron.exclusive.import-stores.kaufland'],
    'kategorie' => ['cron.import-categories', [], 'cron.exclusive.import-categories'],
]);

it('hlídání stahování: 200, když mají všechny obchody čerstvé stažení, jinak 503', function (): void {
    foreach (Chain::cases() as $chain) {
        ScrapeRun::query()->create(['chain' => $chain, 'status' => ScrapeStatus::Succeeded, 'started_at' => CarbonImmutable::now()->subHour(), 'finished_at' => CarbonImmutable::now()->subHour()]);
    }

    $this->get(route('health.imports'))->assertOk()->assertSeeText('Tesco — OK, naposledy 2. 10. 11:00');

    ScrapeRun::query()->where('chain', Chain::Tesco)->update(['finished_at' => CarbonImmutable::now()->subDays(2)]);

    $this->get(route('health.imports'))
        ->assertServiceUnavailable()
        ->assertSeeText('Tesco — VÝPADEK: poslední úspěšné stažení 30. 9. 12:00')
        ->assertSeeText('Kaufland — OK');
});

it('hlídání stahování nepovažuje částečné stažení za úspěch (R54)', function (): void {
    foreach (Chain::cases() as $chain) {
        ScrapeRun::query()->create(['chain' => $chain, 'status' => ScrapeStatus::Succeeded, 'started_at' => CarbonImmutable::now()->subDays(2), 'finished_at' => CarbonImmutable::now()->subDays(2)]);
        ScrapeRun::query()->create(['chain' => $chain, 'status' => $chain === Chain::Lidl ? ScrapeStatus::Partial : ScrapeStatus::Succeeded, 'started_at' => CarbonImmutable::now()->subHour(), 'finished_at' => CarbonImmutable::now()->subHour()]);
    }

    $this->get(route('health.imports'))
        ->assertServiceUnavailable()
        ->assertSeeText('Lidl — VÝPADEK')
        ->assertSeeText('Tesco — OK');
});
