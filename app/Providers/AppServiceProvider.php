<?php

/**
 * Globální nastavení aplikace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registrace služeb — zatím žádné.
     */
    public function register(): void
    {
        //
    }

    /**
     * Přísnější Eloquent při vývoji a v testech, jednotný zápis čísel v JSON.
     */
    public function boot(): void
    {
        // Nejkratší zápis desetinných čísel v JSON (9.2, ne 9.1999…) nezávisle
        // na php.ini hostingu (zkušenost z Počasí, R47 tamtéž)
        ini_set('serialize_precision', '-1');

        // N+1 dotazy, přiřazení mimo $fillable a čtení nenačtených atributů
        // jsou při vývoji chyba, na produkci jen ne
        Model::shouldBeStrict(! $this->app->isProduction());

        // Katalog produktů spravuje jen admin (R29)
        Gate::define('manage-catalog', fn (User $user): bool => $user->is_admin);
    }
}
