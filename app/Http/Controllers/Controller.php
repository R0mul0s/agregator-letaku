<?php

/**
 * Společný předek kontrolerů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Přihlášený uživatel u rout za middlewarem auth (dřív kopie v šesti kontrolerech, R113).
     *
     * @throws AuthenticationException Routa bez middlewaru auth a nepřihlášený návštěvník
     */
    protected function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
