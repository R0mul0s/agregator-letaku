<?php

/**
 * Hlídání úloh cronu `/health/tasks` (R115): záznam běhů v `task_heartbeats` (kanály
 * upozornění zvlášť, úklid, kategorie) a čerstvost seznamů akcí prodejen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\CronTask;
use App\Models\Store;
use App\Models\TaskHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // 12:00 místního času
    $this->travelTo('2026-10-09 10:00:00');
    config(['letaky.cron.token' => 'tajny-token']);
});

/**
 * Prodejna Kauflandu se seznamem akcí staženým před zadaným počtem hodin.
 */
function storeWithListFetchedHoursAgo(int $hours): Store
{
    return Store::query()->create([
        'chain' => Chain::Kaufland,
        'code' => '1100',
        'name' => 'Kaufland Praha-Vršovice',
        'city' => 'Praha',
        'offer_keys_fetched_at' => CarbonImmutable::now()->subHours($hours),
    ]);
}

it('bez jediného běhu hlásí výpadek všech úloh', function (): void {
    $this->get(route('health.tasks'))
        ->assertServiceUnavailable()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSeeText('Prodejny Kaufland — VÝPADEK: poslední úspěšné stažení nikdy')
        ->assertSeeText('E-mailové souhrny — VÝPADEK: poslední úspěšný běh nikdy')
        ->assertSeeText('Kategorie katalogu — VÝPADEK');
});

it('úlohy z cron URL i z artisanu zapíšou úspěšný běh; pak je vše v pořádku', function (): void {
    storeWithListFetchedHoursAgo(1);
    Http::fake(['https://xapi.tesco.com/*' => Http::response(responseFixture('tesco/taxonomy-2026-10-02.json'))]);

    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))->assertOk();
    $this->get(route('cron.import-categories', ['token' => 'tajny-token']))->assertOk();
    $this->artisan('letaky:prune-sessions')->assertSuccessful();

    expect(TaskHeartbeat::query()->whereNotNull('succeeded_at')->count())->toBe(count(CronTask::cases()));

    $this->get(route('health.tasks'))
        ->assertOk()
        ->assertSeeText('Prodejny Kaufland — OK, naposledy 9. 10. 11:00')
        ->assertSeeText('Upozornění v telefonu — OK, naposledy 9. 10. 12:00')
        ->assertSeeText('Denní úklid — OK');
});

it('chyba jednoho kanálu upozornění se zapíše jen u něj, výpadek je až po limitu', function (): void {
    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))->assertOk();

    // Za hodinu kanál končících akcí spadne, ostatní doběhnou
    $this->travel(1)->hours();
    config(['letaky.notifications.ending_soon.from_hour' => 'odpoledne']);
    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))->assertInternalServerError();

    $ending = TaskHeartbeat::query()->findOrFail(CronTask::EndingSoon->value);
    expect($ending->succeeded_at?->toDateTimeString())->toBe('2026-10-09 10:00:00')
        ->and($ending->failed_at?->toDateTimeString())->toBe('2026-10-09 11:00:00')
        ->and(TaskHeartbeat::query()->findOrFail(CronTask::Digest->value)->succeeded_at?->toDateTimeString())->toBe('2026-10-09 11:00:00');

    // Jedno selhání výpadek není, jen se připíše
    $this->get(route('health.tasks'))
        ->assertSeeText('Centrum upozornění — končící akce — OK, naposledy 9. 10. 12:00 (poté chyba 9. 10. 13:00)');

    // Přes limit (9 h) od posledního úspěchu výpadek; ostatní kanály uspěly o hodinu později
    $this->travel(8)->hours();
    $this->travel(1)->minutes();
    $this->get(route('health.tasks'))
        ->assertServiceUnavailable()
        ->assertSeeText('Centrum upozornění — končící akce — VÝPADEK: poslední úspěšný běh 9. 10. 12:00 (poté chyba 9. 10. 13:00)')
        ->assertSeeText('E-mailové souhrny — OK');
});

it('chyba stažení kategorií se zapíše, souběžné spuštění nic nezapíše', function (): void {
    $running = Cache::lock('cron.exclusive.import-categories', 300);
    $running->get();
    $this->get(route('cron.import-categories', ['token' => 'tajny-token']))->assertConflict();
    $running->release();

    expect(TaskHeartbeat::query()->count())->toBe(0);

    Http::fake(['https://xapi.tesco.com/*' => Http::response('', 500)]);
    $this->get(route('cron.import-categories', ['token' => 'tajny-token']))->assertInternalServerError();

    $categories = TaskHeartbeat::query()->findOrFail(CronTask::Categories->value);
    expect($categories->succeeded_at)->toBeNull()
        ->and($categories->failed_at)->not->toBeNull();
    $this->get(route('health.tasks'))
        ->assertSeeText('Kategorie katalogu — VÝPADEK: poslední úspěšný běh nikdy (poté chyba 9. 10. 12:00)');
});

it('seznamy akcí prodejen starší než limit jsou výpadek', function (): void {
    storeWithListFetchedHoursAgo(config()->integer('letaky.health.max_store_lists_age_hours') + 1);

    $this->get(route('health.tasks'))
        ->assertServiceUnavailable()
        ->assertSeeText('Prodejny Kaufland — VÝPADEK: poslední úspěšné stažení 8. 10. 17:00');
});
