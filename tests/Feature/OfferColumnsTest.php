<?php

/**
 * Sloupce akce bez surové odpovědi obchodu (R106, R113) — `withoutRaw()` skládá sloupce
 * z `$fillable`; nový sloupec mimo něj by z výpisů tiše zmizel.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Models\Offer;
use Illuminate\Support\Facades\Schema;

it('withoutRaw načte všechny sloupce tabulky kromě raw', function (): void {
    $selected = array_map(fn (string $column): string => substr($column, strlen('offers.')), Offer::query()->withoutRaw()->getQuery()->columns ?? []);

    expect($selected)->toEqualCanonicalizing(array_values(array_diff(Schema::getColumnListing('offers'), ['raw'])));
});
