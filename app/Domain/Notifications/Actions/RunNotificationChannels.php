<?php

/**
 * Kanály cronu upozornění v pevném pořadí — záznamy centra (nové, končící, dnes začínající
 * akce), e-mailové souhrny, upozornění v telefonu a upozornění adminům na výpadek (R42, R66,
 * R74, R76, R126). Volá ho cron URL
 * /cron/send-digests i příkaz letaky:send-digests, ať se pořadí nerozejde.
 *
 * Běží jen jedno spuštění najednou (R113): kanály vybírají uživatele podle času posledního
 * zpracování a zapisují ho až po odeslání, souběžná spuštění by poslala vše dvakrát.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Digest\Actions\SendDigests;
use App\Domain\Push\Actions\SendPushNotifications;
use App\Enums\CronTask;
use App\Support\Deadline;
use App\Support\Exceptions\AlreadyRunning;
use App\Support\ExclusiveRun;
use App\Support\TaskHeartbeats;
use Throwable;

final readonly class RunNotificationChannels
{
    private const LOCK_KEY = 'notification-channels';

    public function __construct(
        private RecordNewOffers $recordNew,
        private RecordEndingOffers $recordEnding,
        private RecordStartingOffers $recordStarting,
        private SendDigests $digests,
        private SendPushNotifications $push,
        private RecordHealthAlerts $healthAlerts,
    ) {}

    /**
     * Spustí kanály; každý dostane rovný díl zbývajícího rozpočtu (R106) a chyba jednoho
     * ostatní nezastaví; výsledek každého kanálu se zapíše pro /health/tasks (R115). Vrátí počet zpracovaných, nebo chybu, po kanálech v pořadí běhu.
     *
     * @return array<string, int|Throwable> Kanál (klíč textů v lang/cs/app.php) => počet nebo chyba
     *
     * @throws AlreadyRunning
     */
    public function __invoke(Deadline $deadline): array
    {
        return ExclusiveRun::run(self::LOCK_KEY, fn (): array => $this->run($deadline));
    }

    /**
     * Kanály po sobě — záznamy centra jako první, upozornění v telefonu se z nich skládá (R74).
     *
     * @return array<string, int|Throwable>
     */
    private function run(Deadline $deadline): array
    {
        $channels = [
            [CronTask::Notifications, $this->recordNew],
            [CronTask::EndingSoon, $this->recordEnding],
            [CronTask::StartingToday, $this->recordStarting],
            [CronTask::Digest, $this->digests],
            [CronTask::Push, $this->push],
            // Až po ostatních kanálech — vidí jejich dnešní výsledek (R126)
            [CronTask::HealthAlerts, $this->healthAlerts],
        ];

        $results = [];
        $remaining = count($channels);
        foreach ($channels as [$task, $action]) {
            // Každý kanál zvlášť (R115) — výpadek jen telefonu by jinak nikdo neviděl
            try {
                $results[$task->value] = $action($deadline->share($remaining--));
                TaskHeartbeats::succeeded($task);
            } catch (Throwable $error) {
                report($error);
                TaskHeartbeats::failed($task);
                $results[$task->value] = $error;
            }
        }

        return $results;
    }
}
