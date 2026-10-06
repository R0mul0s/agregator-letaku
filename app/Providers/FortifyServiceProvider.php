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
use App\Domain\Account\AuthShowcase;
use App\Domain\Account\RegistrationGuard;
use App\Enums\SocialProvider;
use App\Http\Controllers\SocialLoginController;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\VerifyEmailResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Vlastní odpovědi: po ověření e-mailu toast místo parametru ?verified=1 (R51),
     * po registraci rovnou do Hlídám (R55).
     */
    public function register(): void
    {
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
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

        // Dva limity (R53): e-mail + IP proti hádání hesla k jednomu účtu (ostatní uživatele
        // neomezí) a samotná IP proti zkoušení uniklých přihlašovacích údajů přes různé e-maily
        RateLimiter::for('login', function (Request $request): array {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(config()->integer('letaky.auth.login_attempts_per_minute'))->by($throttleKey),
                Limit::perMinute(config()->integer('letaky.auth.login_attempts_per_minute_per_ip'))->by('ip|'.$request->ip()),
            ];
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
            // Panel vedle formuláře (R56): počet akcí, obchody a ukázka akcí s nejvyšší slevou
            'showcase' => fn (): array => app(AuthShowcase::class)->toArray(),
            // Přihlášení přes Google a Facebook (R96); parametr „Zapamatovat si mě“ přidá stránka
            'social' => $this->socialProviders(),
            'rememberParameter' => SocialLoginController::REMEMBER_PARAMETER,
        ]));

        Fortify::registerView(function (Request $request): Response {
            // Z karty akce „Hlídat“ (R60): produkt se začne hlídat hned po registraci
            RegisterResponse::rememberProduct($request);

            return Inertia::render('Auth/Register', [
                'urls' => [
                    'submit' => route('register.store', absolute: false),
                    'login' => route('login', absolute: false),
                    'terms' => route('legal.terms', absolute: false),
                    'privacy' => route('legal.privacy', absolute: false),
                ],
                // Ochrana proti botům (R53): podepsaný čas načtení a název skrytého pole
                'guard' => [
                    'tokenField' => RegistrationGuard::TOKEN_FIELD,
                    'token' => app(RegistrationGuard::class)->token(),
                    'trapField' => RegistrationGuard::TRAP_FIELD,
                ],
                // Nápověda u hesla (R56) — stejná délka jako Password::defaults() v AppServiceProvider
                'passwordMinLength' => config()->integer('letaky.auth.password.min_length'),
                // Panel vedle formuláře (R56): počet akcí, obchody a ukázka akcí s nejvyšší slevou
                'showcase' => fn (): array => app(AuthShowcase::class)->toArray(),
                // Registrace přes Google a Facebook (R96) — souhlasy potvrdí dokončení registrace
                'social' => $this->socialProviders(),
            ]);
        });

        // Výzvu k ověření e-mailu (R51) ukazuje lišta v rozvržení, samostatná stránka není potřeba
        Fortify::verifyEmailView(fn (): RedirectResponse => to_route('home'));

        Fortify::requestPasswordResetLinkView(fn (): Response => Inertia::render('Auth/ForgotPassword', [
            'urls' => [
                'submit' => route('password.email', absolute: false),
                'login' => route('login', absolute: false),
            ],
            // Stejný panel jako u přihlášení (R56) — AuthShowcase bez dat stránku shodí
            'showcase' => fn (): array => app(AuthShowcase::class)->toArray(),
        ]));

        Fortify::resetPasswordView(fn (Request $request): Response => Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
            'urls' => [
                'submit' => route('password.update', absolute: false),
            ],
            'showcase' => fn (): array => app(AuthShowcase::class)->toArray(),
        ]));
    }

    /**
     * Tlačítka přihlášení přes Google a Facebook (R96) — jen poskytovatelé s klíči v .env.
     * Adresa vede mimo Inertii (přesměrování k poskytovateli by XHR nedokončil).
     *
     * @return list<array{provider: string, url: string, logo: string}>
     */
    private function socialProviders(): array
    {
        return array_map(fn (SocialProvider $provider): array => [
            'provider' => $provider->value,
            'url' => route('social.redirect', ['provider' => $provider], absolute: false),
            'logo' => $provider->logoUrl(),
        ], SocialProvider::configured());
    }
}
