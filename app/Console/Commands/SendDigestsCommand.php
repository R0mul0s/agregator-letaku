<?php

/**
 * Artisan: e-mailové souhrny nových akcí (R42) — obálka nad SendDigests.
 * Na produkci totéž volá cron URL /cron/send-digests.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Digest\Actions\SendDigests;
use Illuminate\Console\Command;

class SendDigestsCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:send-digests';

    /** @var string */
    protected $description = 'Pošle e-mailové souhrny nových akcí uživatelům, kterým je čas';

    /**
     * Pošle souhrny a vypíše jejich počet.
     */
    public function handle(SendDigests $send): int
    {
        $this->info(__('app.digest.done', ['count' => $send()]));

        return self::SUCCESS;
    }
}
