<?php

/**
 * Založí hlídanou položku (R18, R31) — z Hlídám i z průvodce prvním nastavením (R153).
 * Návrh stejné položky z nákupního seznamu je tím vyřízený (R148).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Shopping\WatchSuggestions;
use App\Models\User;
use App\Models\WatchItem;

final class AddWatchItem
{
    public function __construct(
        private readonly WatchSuggestions $suggestions,
    ) {}

    /**
     * Založí položku a uzavře návrh stejné položky.
     *
     * @param  array{name: string, product_id: int|null, keywords: string|null, variant_keywords: string|null, exclude_keywords: string|null}  $data
     */
    public function __invoke(User $user, array $data): WatchItem
    {
        $item = $user->watchItems()->create($data);
        $this->suggestions->closeFor($user, $item);

        return $item;
    }
}
