<?php

/**
 * Artisan: e-mailové souhrny nových akcí (R42) a upozornění v telefonu (R66) — obálka nad
 * RunNotificationChannels. Na produkci totéž volá cron URL /cron/send-digests.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Actions\RunNotificationChannels;
use App\Support\Deadline;
use App\Support\Exceptions\AlreadyRunning;
use Illuminate\Console\Command;
use Throwable;

class SendDigestsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:send-digests';

    /** @var string */
    protected $description = 'Zapíše nové, končící a dnes začínající akce do centra upozornění a pošle e-mailové souhrny a upozornění v telefonu uživatelům, kterým je čas';

    /**
     * Spustí kanály upozornění bez časového rozpočtu a vypíše jejich počty nebo chyby.
     */
    public function handle(RunNotificationChannels $channels): int
    {
        try {
            $results = $channels(Deadline::none());
        } catch (AlreadyRunning) {
            $this->error(__('app.cron.already_running'));

            return self::FAILURE;
        }

        $failed = false;
        foreach ($results as $channel => $result) {
            if ($result instanceof Throwable) {
                $this->error(__("app.$channel.failed", ['error' => $result->getMessage()]));
                $failed = true;
            } else {
                $this->info(__("app.$channel.done", ['count' => $result]));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
