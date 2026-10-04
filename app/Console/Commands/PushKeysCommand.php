<?php

/**
 * Artisan: vygeneruje klíče VAPID pro upozornění v telefonu (R66). Na produkci artisan neběží
 * (R20) — klíče se vygenerují lokálně a vloží do .env na hostingu. Jednou provždy: nové klíče
 * zneplatní všechny odběry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushKeysCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:push-keys';

    /** @var string */
    protected $description = 'Vygeneruje klíče VAPID pro upozornění v telefonu (web push)';

    /**
     * Vypíše nový pár klíčů jako řádky .env.
     */
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info(__('app.push.keys_generated'));
        $this->line('LETAKY_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('LETAKY_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
