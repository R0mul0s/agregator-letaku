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
use App\Http\Responses\VerifyEmailResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Vlastní odpověď po ověření e-mailu — toast místo parametru ?verified=1 (R51).
     */
    public function register(): void
    {
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
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
                'terms' => route('legal.terms', absolute: false),
                'privacy' => route('legal.privacy', absolute: false),
            ],
        ]));

        // Výzvu k ověření e-mailu (R51) ukazuje lišta v rozvržení, samostatná stránka není potřeba
        Fortify::verifyEmailView(fn (): RedirectResponse => to_route('home'));

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
