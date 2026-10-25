<?php

/**
 * Příkaz letaky:push-keys (R66) vypíše nový pár klíčů VAPID jako řádky .env.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('vypíše veřejný a soukromý klíč VAPID jako řádky .env', function (): void {
    expect(Artisan::call('letaky:push-keys'))->toBe(0);

    $output = Artisan::output();
    expect($output)->toMatch('/^LETAKY_VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{80,}$/m')
        ->and($output)->toMatch('/^LETAKY_VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{40,}$/m');
});
