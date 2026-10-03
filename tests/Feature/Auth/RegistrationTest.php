<?php

/**
 * Registrace nového účtu (Fortify, R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** Silné heslo, které projde Password::default(). */
const NEW_PASSWORD = 'Nove-heslo-2026';

it('zobrazí registrační stránku', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('urls.submit', '/register')
            ->where('urls.terms', '/podminky')
            ->where('urls.privacy', '/ochrana-udaju'));
});

it('zaregistruje uživatele, přihlásí ho, e-mail uloží malými písmeny a pošle odkaz na ověření', function (): void {
    Notification::fake();
    $this->travelTo('2026-10-03 10:00:00');

    $this->post(route('register.store'), [
        'name' => 'Roman',
        'email' => 'Roman@Example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
        'terms' => true,
    ])->assertRedirect('/');

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
    $this->post(route('register.store'), [
        'name' => 'Roman',
        'email' => 'roman@example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])->assertSessionHasErrors(['terms' => __('app.ui.auth.register.terms_required')]);

    expect(User::query()->count())->toBe(0);
});

it('dobrovolný souhlas s obchodními sděleními uloží s verzí textu (R51)', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Roman',
        'email' => 'roman@example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
        'terms' => true,
        'marketing' => true,
    ]);

    expect(User::query()->sole())
        ->hasMarketingConsent()->toBeTrue()
        ->marketing_consent_version->toBe(config('letaky.legal.marketing_consent_version'));
});

it('odmítne už použitý e-mail', function (): void {
    $existing = User::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Někdo',
        'email' => $existing->email,
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1);
});

it('odmítne heslo, které nesouhlasí s potvrzením, a chybu napíše česky', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Roman',
        'email' => 'roman@example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => 'jine-heslo',
    ])->assertSessionHasErrors(['password' => 'Potvrzení pole heslo nesouhlasí.']);

    $this->assertGuest();
});
