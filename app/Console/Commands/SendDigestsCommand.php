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
use App\Domain\Notifications\Actions\RecordNewOffers;
use App\Domain\Push\Actions\SendPushNotifications;
use Illuminate\Console\Command;

class SendDigestsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:send-digests';

    /** @var string */
    protected $description = 'Zapíše nové akce do centra upozornění a pošle e-mailové souhrny a upozornění v telefonu uživatelům, kterým je čas';

    /**
     * Zapíše záznamy centra (R74; upozornění v telefonu se z nich skládá), pošle souhrny
     * a upozornění a vypíše jejich počty.
     */
    public function handle(RecordNewOffers $record, SendDigests $send, SendPushNotifications $push): int
    {
        $this->info(__('app.notifications.done', ['count' => $record()]));
        $this->info(__('app.digest.done', ['count' => $send()]));
        $this->info(__('app.push.done', ['count' => $push()]));

        return self::SUCCESS;
    }
}
