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

use App\Domain\Push\PushSender;
use App\Domain\Push\WebPushSender;
use App\Models\User;
use App\Support\RateLimits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registrace služeb: odeslání upozornění v telefonu (R66) přes push služby prohlížečů.
     */
    public function register(): void
    {
        $this->app->singleton(PushSender::class, WebPushSender::class);
    }

    /**
     * Přísnější Eloquent při vývoji a v testech, jednotný zápis čísel v JSON, adresy z APP_URL.
     */
    public function boot(): void
    {
        // Nejkratší zápis desetinných čísel v JSON (9.2, ne 9.1999…) nezávisle
        // na php.ini hostingu (zkušenost z Počasí, R47 tamtéž)
        ini_set('serialize_precision', '-1');

        // N+1 dotazy, přiřazení mimo $fillable a čtení nenačtených atributů
        // jsou při vývoji chyba, na produkci jen ne
        Model::shouldBeStrict(! $this->app->isProduction());

        // Absolutní adresy (odkazy v e-mailech, canonical, sitemap) vždy z APP_URL (R67), ne
        // z požadavku — s kořenovým .htaccess by jinak nesly /public a hlavičky od klienta
        // by mohly změnit adresu v odkazu na obnovu hesla
        URL::forceRootUrl(config()->string('app.url'));

        // Katalog produktů spravuje jen admin (R29)
        Gate::define('manage-catalog', fn (User $user): bool => $user->is_admin);
        // Zprávy od nás do centra upozornění (R74, etapa 11d) posílá admin
        Gate::define('send-announcements', fn (User $user): bool => $user->is_admin);

        // Omezení počtu požadavků (R45)
        RateLimits::register();

        // Síla hesla (R53) — Password::default() v registraci, změně a obnově hesla
        Password::defaults(fn (): Password => $this->passwordRule());
    }

    /**
     * Pravidlo nového hesla: nejmenší délka a (mimo testy) kontrola proti únikům Have I Been
     * Pwned. Když služba neodpoví, Laravel heslo propustí — výpadek nezablokuje registraci.
     */
    private function passwordRule(): Password
    {
        $rule = Password::min(config()->integer('letaky.auth.password.min_length'));

        return config()->boolean('letaky.auth.password.uncompromised') ? $rule->uncompromised() : $rule;
    }
}
