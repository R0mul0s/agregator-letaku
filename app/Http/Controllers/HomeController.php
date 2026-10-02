<?php

/**
 * Úvodní stránka přihlášeného uživatele — seznam jeho slev (od etapy 3).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Zobrazí seznam slev. Do etapy 3 (hlídání) jen prázdný stav.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Home');
    }
}
