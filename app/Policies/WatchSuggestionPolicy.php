<?php

/**
 * Oprávnění k návrhům, co hlídat (R148) — jen vlastník (CODING_GUIDELINES, sekce 10).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WatchSuggestion;

class WatchSuggestionPolicy
{
    /**
     * Zavřít návrh („Ne, díky“) smí jen jeho vlastník.
     */
    public function delete(User $user, WatchSuggestion $suggestion): bool
    {
        return $suggestion->user_id === $user->id;
    }
}
