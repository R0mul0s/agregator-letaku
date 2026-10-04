<?php

/**
 * Artisan: e-mailové souhrny nových akcí (R42) a upozornění v telefonu (R66) — obálka nad
 * SendDigests a SendPushNotifications. Na produkci totéž volá cron URL /cron/send-digests.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Digest\Actions\SendDigests;
use App\Domain\Push\Actions\SendPushNotifications;
use Illuminate\Console\Command;

class SendDigestsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:send-digests';

    /** @var string */
    protected $description = 'Pošle e-mailové souhrny a upozornění v telefonu na nové akce uživatelům, kterým je čas';

    /**
     * Pošle souhrny a upozornění a vypíše jejich počty.
     */
    public function handle(SendDigests $send, SendPushNotifications $push): int
    {
        $this->info(__('app.digest.done', ['count' => $send()]));
        $this->info(__('app.push.done', ['count' => $push()]));

        return self::SUCCESS;
    }
}
