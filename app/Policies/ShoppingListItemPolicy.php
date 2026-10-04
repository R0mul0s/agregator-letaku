<?php

/**
 * Oprávnění k položkám nákupního seznamu (R61) — jen vlastník.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShoppingListItem;
use App\Models\User;

class ShoppingListItemPolicy
{
    /**
     * Odškrtnout položku smí jen vlastník seznamu.
     */
    public function update(User $user, ShoppingListItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    /**
     * Smazat položku smí jen vlastník seznamu.
     */
    public function delete(User $user, ShoppingListItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
