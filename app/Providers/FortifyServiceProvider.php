<?php

/**
 * Napojení Laravel Fortify na aplikaci — akce účtu, stránky Inertia, omezení pokusů (R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Registrace služeb — Fortify nic navíc nepotřebuje.
     */
    public function register(): void
    {
        //
    }

    /**
     * Akce pro správu účtu, stránky přihlášení a registrace a limit pokusů o přihlášení.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerViews();

        // Klíč e-mail + IP: hádání hesla k jednomu účtu z jedné adresy, ostatní uživatele neomezí
        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip());

            return Limit::perMinute(config()->integer('letaky.auth.login_attempts_per_minute'))->by($throttleKey);
        });
    }

    /**
     * Stránky přihlášení, registrace a obnovy hesla jako Inertia stránky.
     * Adresy formulářů posílá server — routy Fortify se ve Vue nepíšou natvrdo.
     */
    private function registerViews(): void
    {
        Fortify::loginView(fn (): Response => Inertia::render('Auth/Login', [
            'urls' => [
                'submit' => route('login.store', absolute: false),
                'register' => route('register', absolute: false),
                'forgotPassword' => route('password.request', absolute: false),
            ],
        ]));

        Fortify::registerView(fn (): Response => Inertia::render('Auth/Register', [
            'urls' => [
                'submit' => route('register.store', absolute: false),
                'login' => route('login', absolute: false),
            ],
        ]));

        Fortify::requestPasswordResetLinkView(fn (): Response => Inertia::render('Auth/ForgotPassword', [
            'urls' => [
                'submit' => route('password.email', absolute: false),
                'login' => route('login', absolute: false),
            ],
        ]));

        Fortify::resetPasswordView(fn (Request $request): Response => Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
            'urls' => [
                'submit' => route('password.update', absolute: false),
            ],
        ]));
    }
}
