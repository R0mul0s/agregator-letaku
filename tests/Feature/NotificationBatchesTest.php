<?php

/**
 * Dávky cronu upozornění a souhrnů (R106): hranice nových akcí při souběhu se stažením,
 * časový rozpočet a uživatel, u kterého zpracování opakovaně padá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Domain\Digest\NewOffers;
use App\Domain\Notifications\Actions\RecordNewOffers;
use App\Domain\Notifications\UserBatch;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use App\Support\Deadline;

beforeEach(function (): void {
    $this->travelTo('2026-10-07 10:00:00');
    $this->user = User::factory()->create();
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
});

describe('hranice nových akcí při souběhu se stažením', function (): void {
    it('bez běžícího stažení je hranice teď', function (): void {
        expect(app(NewOffers::class)->horizon()->toDateTimeString())->toBe('2026-10-07 10:00:00');
    });

    it('běžící stažení posune hranici před svůj začátek', function (): void {
        ScrapeRun::query()->create(['chain' => Chain::Tesco, 'status' => ScrapeStatus::Running, 'started_at' => now()->subSeconds(30)]);

        expect(app(NewOffers::class)->horizon()->toDateTimeString())->toBe('2026-10-07 09:59:29');
    });

    it('stažení, které běží déle než zámek, nedoběhlo a hranici nezdržuje', function (): void {
        ScrapeRun::query()->create(['chain' => Chain::Tesco, 'status' => ScrapeStatus::Running, 'started_at' => now()->subSeconds(config()->integer('letaky.import.lock_seconds') + 1)]);

        expect(app(NewOffers::class)->horizon()->toDateTimeString())->toBe('2026-10-07 10:00:00');
    });

    it('akci stažení, které při zpracování ještě běželo, ohlásí příště — neztratí ji', function (): void {
        $this->user->forceFill(['notified_at' => now()->subHour()])->save();
        ScrapeRun::query()->create(['chain' => Chain::Kaufland, 'status' => ScrapeStatus::Succeeded, 'started_at' => now()->subMinutes(50), 'finished_at' => now()->subMinutes(49)]);

        // Tesco stahuje: řádek už má created_at, ale transakce ještě není potvrzená
        $run = ScrapeRun::query()->create(['chain' => Chain::Kaufland, 'status' => ScrapeStatus::Running, 'started_at' => now()->subSeconds(30)]);
        $butter = Offer::factory()->create(['chain' => Chain::Kaufland, 'name' => 'Máslo 250 g', 'created_at' => now()->subSeconds(10)]);

        expect(app(RecordNewOffers::class)())->toBe(0)
            ->and($this->user->fresh()?->notified_at?->toDateTimeString())->toBe('2026-10-07 09:59:29');

        // Stažení doběhne, další volání cronu akci ohlásí
        $this->travelTo('2026-10-07 10:01:00');
        $run->forceFill(['status' => ScrapeStatus::Succeeded, 'finished_at' => now()])->save();

        expect(app(RecordNewOffers::class)())->toBe(1)
            ->and($this->user->notifications()->sole()->data['groups'][0]['offerIds'])->toBe([$butter->id]);
    });
});

describe('dávka uživatelů', function (): void {
    it('po vypršení rozpočtu nikoho dalšího nezpracuje', function (): void {
        $processed = app(UserBatch::class)->run('test', [$this->user], Deadline::in(0), fn (User $user): bool => true);

        expect($processed)->toBe(0);
    });

    it('rozpočet si kroky dělí rovným dílem', function (): void {
        expect(Deadline::in(100)->share(1)->passed())->toBeFalse()
            ->and(Deadline::in(0)->share(5)->passed())->toBeTrue()
            ->and(Deadline::none()->share(5)->passed())->toBeFalse();
    });

    it('uživateli, u kterého zpracování opakovaně padá, po posledním pokusu posune čas', function (): void {
        $batch = app(UserBatch::class);
        $givenUp = [];
        $failing = fn (User $user): bool => throw new RuntimeException('Rozbitá položka');

        foreach (range(1, config()->integer('letaky.cron.user_failures.max_attempts')) as $attempt) {
            $batch->run('test', [$this->user], Deadline::none(), $failing, giveUp: function (User $user) use (&$givenUp): void {
                $givenUp[] = $user->id;
            });
        }

        expect($givenUp)->toBe([$this->user->id])
            ->and($batch->givenUp('test'))->toBe([]);
    });

    it('kanál bez vlastního času uživatele po posledním pokusu vynechá', function (): void {
        $batch = app(UserBatch::class);
        $failing = fn (User $user): bool => throw new RuntimeException('Rozbitá položka');

        foreach (range(1, config()->integer('letaky.cron.user_failures.max_attempts')) as $attempt) {
            expect($batch->givenUp('test'))->toBe([]);
            $batch->run('test', [$this->user], Deadline::none(), $failing);
        }

        expect($batch->givenUp('test'))->toBe([$this->user->id]);
    });

    it('úspěch počet chyb uživatele vynuluje', function (): void {
        $batch = app(UserBatch::class);
        $batch->run('test', [$this->user], Deadline::none(), fn (User $user): bool => throw new RuntimeException('Výpadek'));
        $batch->run('test', [$this->user], Deadline::none(), fn (User $user): bool => true);

        foreach (range(2, config()->integer('letaky.cron.user_failures.max_attempts')) as $attempt) {
            $batch->run('test', [$this->user], Deadline::none(), fn (User $user): bool => throw new RuntimeException('Výpadek'));
        }

        expect($batch->givenUp('test'))->toBe([]);
    });

    it('chyba ze seznamu dávku ukončí, ale uživateli se započítá', function (): void {
        $other = User::factory()->create();
        $processed = [];
        $rejected = function (User $user) use (&$processed): bool {
            $processed[] = $user->id;

            throw new LogicException('Adresa odmítnuta');
        };

        foreach (range(1, config()->integer('letaky.cron.user_failures.max_attempts')) as $attempt) {
            app(UserBatch::class)->run('test', [$this->user, $other], Deadline::none(), $rejected, stopOn: [LogicException::class]);
        }

        // Druhý uživatel na řadu nepřišel, první s odmítnutou adresou se příště vynechá
        expect(array_unique($processed))->toBe([$this->user->id])
            ->and(app(UserBatch::class)->givenUp('test'))->toBe([$this->user->id]);
    });
});
