<?php

/**
 * Úklid vypršelých relací a odkazů pro obnovu hesla (R53).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use App\Domain\Account\Actions\PruneExpiredSessions;
use App\Domain\Offers\Actions\PruneOfferRaw;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

it('smaže relace starší než jejich platnost a propadlé odkazy na obnovu hesla, platné nechá', function (): void {
    config(['session.driver' => 'database']);
    $this->travelTo('2026-10-03 12:00:00');
    $lifetime = config()->integer('session.lifetime');
    $session = fn (string $id, int $minutesAgo): array => [
        'id' => $id, 'user_id' => null, 'ip_address' => '127.0.0.1', 'user_agent' => 'test',
        'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ];
    DB::table('sessions')->insert([$session('vyprsela', $lifetime + 1), $session('platna', $lifetime - 1)]);
    DB::table('password_reset_tokens')->insert([
        ['email' => 'stary@example.com', 'token' => 'x', 'created_at' => now()->subDay()],
        ['email' => 'novy@example.com', 'token' => 'y', 'created_at' => now()],
    ]);

    expect(app(PruneExpiredSessions::class)())->toBe(1);

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['platna'])
        ->and(DB::table('password_reset_tokens')->pluck('email')->all())->toBe(['novy@example.com']);
});

it('smaže prošlé položky databázové cache (limity požadavků s IP), platné nechá (R69)', function (): void {
    config(['cache.default' => 'database']);
    $this->travelTo('2026-10-03 12:00:00');
    DB::table('cache')->insert([
        ['key' => 'limit-prosly', 'value' => '1', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'limit-platny', 'value' => '1', 'expiration' => now()->addMinute()->getTimestamp()],
    ]);

    app(PruneExpiredSessions::class)();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['limit-platny']);
});

it('cron URL uklidí jen s tokenem', function (): void {
    config(['letaky.cron.token' => 'tajny-token']);

    $this->get(route('cron.prune-sessions'))->assertNotFound();
    $this->get(route('cron.prune-sessions', ['token' => 'tajny-token']))
        ->assertOk()
        ->assertSeeText('Úklid — smazáno vypršelých relací:');
});

it('vyprázdní surovou odpověď akcí skončených před dobou uchování, novější nechá (R113)', function (): void {
    config(['letaky.offers.raw_retention_days' => 60, 'letaky.offers.raw_prune_batch' => 1]);
    $this->travelTo('2026-10-09 12:00:00');
    $old = Offer::factory()->create(['valid_from' => '2026-07-01', 'valid_to' => '2026-08-01', 'raw' => ['id' => 1]]);
    $recent = Offer::factory()->create(['valid_from' => '2026-09-01', 'valid_to' => '2026-09-07', 'raw' => ['id' => 2]]);
    $older = Offer::factory()->create(['valid_from' => '2026-06-01', 'valid_to' => '2026-06-07', 'raw' => ['id' => 3]]);
    $updatedAt = $old->updated_at;

    expect(app(PruneOfferRaw::class)())->toBe(2)
        ->and($old->fresh()->raw)->toBe([])
        ->and($old->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($older->fresh()->raw)->toBe([])
        ->and($recent->fresh()->raw)->toBe(['id' => 2])
        ->and(app(PruneOfferRaw::class)())->toBe(0);
});
