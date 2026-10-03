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

it('cron URL uklidí jen s tokenem', function (): void {
    config(['letaky.cron.token' => 'tajny-token']);

    $this->get(route('cron.prune-sessions'))->assertNotFound();
    $this->get(route('cron.prune-sessions', ['token' => 'tajny-token']))
        ->assertOk()
        ->assertSeeText('Úklid — smazáno vypršelých relací:');
});
