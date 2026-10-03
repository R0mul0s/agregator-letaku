<?php

/**
 * Registrace nového účtu (Fortify, R12): souhlasy (R51), ochrana proti botům a síla
 * hesla (R53).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Account\RegistrationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** Silné heslo, které projde Password::default(). */
const NEW_PASSWORD = 'Nove-heslo-2026';

/** Jak dlouho „člověk“ v testu vyplňoval formulář (víc než letaky.auth.registration.min_seconds). */
const FILL_SECONDS = 30;

/**
 * Údaje registračního formuláře jako od člověka: token načtení formuláře před chvílí
 * a prázdné skryté pole (R53).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationInput(array $overrides = []): array
{
    return [
        'name' => 'Roman',
        'email' => 'roman@example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
        'terms' => true,
        RegistrationGuard::TOKEN_FIELD => app(RegistrationGuard::class)->token(CarbonImmutable::now()->subSeconds(FILL_SECONDS)),
        RegistrationGuard::TRAP_FIELD => '',
        ...$overrides,
    ];
}

it('zobrazí registrační stránku s ochranou proti botům', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('urls.submit', '/register')
            ->where('urls.terms', '/podminky')
            ->where('urls.privacy', '/ochrana-udaju')
            ->where('guard.tokenField', RegistrationGuard::TOKEN_FIELD)
            ->where('guard.trapField', RegistrationGuard::TRAP_FIELD)
            ->where('guard.token', fn (string $token): bool => $token !== ''));
});

it('zaregistruje uživatele, přihlásí ho, e-mail uloží malými písmeny a pošle odkaz na ověření', function (): void {
    Notification::fake();
    $this->travelTo('2026-10-03 10:00:00');

    $this->post(route('register.store'), registrationInput(['email' => 'Roman@Example.com']))
        ->assertRedirect('/');

    $user = User::query()->sole();
    expect($user)
        ->email->toBe('roman@example.com')
        ->email_verified_at->toBeNull()
        ->terms_accepted_at->toDateTimeString()->toBe('2026-10-03 10:00:00')
        ->terms_version->toBe(config('letaky.legal.terms_version'))
        ->hasMarketingConsent()->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('bez souhlasu s podmínkami účet nezaloží (R51)', function (): void {
    $this->post(route('register.store'), registrationInput(['terms' => false]))
        ->assertSessionHasErrors(['terms' => __('app.ui.auth.register.terms_required')]);

    expect(User::query()->count())->toBe(0);
});

it('dobrovolný souhlas s obchodními sděleními uloží s verzí textu (R51)', function (): void {
    $this->post(route('register.store'), registrationInput(['marketing' => true]));

    expect(User::query()->sole())
        ->hasMarketingConsent()->toBeTrue()
        ->marketing_consent_version->toBe(config('letaky.legal.marketing_consent_version'));
});

it('odmítne už použitý e-mail', function (): void {
    $existing = User::factory()->create();

    $this->post(route('register.store'), registrationInput(['email' => $existing->email]))
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1);
});

it('odmítne heslo, které nesouhlasí s potvrzením, a chybu napíše česky', function (): void {
    $this->post(route('register.store'), registrationInput(['password_confirmation' => 'jine-heslo']))
        ->assertSessionHasErrors(['password' => 'Potvrzení pole heslo nesouhlasí.']);

    $this->assertGuest();
});

it('odmítne odeslání, které nevypadá jako od člověka (R53)', function (array $overrides): void {
    $this->post(route('register.store'), registrationInput($overrides))
        ->assertSessionHasErrors([RegistrationGuard::TOKEN_FIELD => __('app.ui.auth.register.bot_check')]);

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
})->with([
    'vyplněné skryté pole' => [[RegistrationGuard::TRAP_FIELD => 'https://spam.example.com']],
    'bez tokenu' => [[RegistrationGuard::TOKEN_FIELD => '']],
    'podvržený token' => [[RegistrationGuard::TOKEN_FIELD => 'abc']],
    'odesláno hned po načtení' => [fn (): array => [RegistrationGuard::TOKEN_FIELD => app(RegistrationGuard::class)->token()]],
    'formulář otevřený příliš dlouho' => [fn (): array => [RegistrationGuard::TOKEN_FIELD => app(RegistrationGuard::class)->token(CarbonImmutable::now()->subDay())]],
]);

it('odmítne heslo, které se objevilo v úniku dat (R53)', function (): void {
    config(['letaky.auth.password.uncompromised' => true]);
    // Have I Been Pwned vrací k prvním 5 znakům SHA-1 otisku zbytky otisků s počtem výskytů
    $hash = strtoupper(sha1(NEW_PASSWORD));
    Http::fake(['api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).":42\r\n")]);

    $this->post(route('register.store'), registrationInput())
        ->assertSessionHasErrors(['password' => __('validation.password.uncompromised', ['attribute' => 'heslo'])]);

    expect(User::query()->count())->toBe(0);
});
