<?php

/**
 * Úplnost textů pro výčty — chybějící klíč se zobrazí jako holý text (CODING_GUIDELINES, sekce 7).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\StoreFormat;

it('má název pro každý obchod', function (Chain $chain): void {
    expect(trans()->has('app.chains.'.$chain->value))->toBeTrue();
})->with(Chain::cases());

it('má název pro každý formát prodejny', function (StoreFormat $format): void {
    expect(trans()->has('app.store_formats.'.$format->value))->toBeTrue();
})->with(StoreFormat::cases());
