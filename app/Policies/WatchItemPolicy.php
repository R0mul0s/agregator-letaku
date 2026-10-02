<?php

/**
 * Oprávnění k hlídaným položkám — jen vlastník (CODING_GUIDELINES, sekce 10).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WatchItem;

class WatchItemPolicy
{
    /**
     * Upravit položku smí jen její vlastník.
     */
    public function update(User $user, WatchItem $watchItem): bool
    {
        return $watchItem->user_id === $user->id;
    }

    /**
     * Smazat položku smí jen její vlastník.
     */
    public function delete(User $user, WatchItem $watchItem): bool
    {
        return $watchItem->user_id === $user->id;
    }
}
