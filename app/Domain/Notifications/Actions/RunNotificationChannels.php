<?php

/**
 * Kanály cronu upozornění v pevném pořadí — záznamy centra (nové, končící, dnes začínající
 * akce), e-mailové souhrny a upozornění v telefonu (R42, R66, R74, R76). Volá ho cron URL
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
use App\Support\Deadline;
use App\Support\Exceptions\AlreadyRunning;
use App\Support\ExclusiveRun;
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
    ) {}

    /**
     * Spustí kanály; každý dostane rovný díl zbývajícího rozpočtu (R106) a chyba jednoho
     * ostatní nezastaví. Vrátí počet zpracovaných, nebo chybu, po kanálech v pořadí běhu.
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
            'notifications' => $this->recordNew,
            'ending_soon' => $this->recordEnding,
            'starting_today' => $this->recordStarting,
            'digest' => $this->digests,
            'push' => $this->push,
        ];

        $results = [];
        $remaining = count($channels);
        foreach ($channels as $channel => $action) {
            try {
                $results[$channel] = $action($deadline->share($remaining--));
            } catch (Throwable $error) {
                report($error);
                $results[$channel] = $error;
            }
        }

        return $results;
    }
}
