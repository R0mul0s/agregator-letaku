<?php

/**
 * Přihlášení a odhlášení (Fortify, R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia as Assert;

it('pošle nepřihlášeného ze stránek pro přihlášené na přihlášení', function (): void {
    $this->get(route('watch-items.index'))->assertRedirect(route('login'));
    $this->get(route('account'))->assertRedirect(route('login'));
});

it('zobrazí přihlašovací stránku s adresami formuláře', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('urls.submit', '/login')
            ->where('urls.register', '/register')
            ->where('urls.forgotPassword', '/forgot-password'));
});

it('přihlásí uživatele se správným heslem a pošle ho na seznam slev', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('nepřihlásí se špatným heslem a chybu napíše česky', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'spatne-heslo'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('po vyčerpání pokusů o přihlášení odpoví omezením', function (): void {
    $user = User::factory()->create();
    $attempts = config()->integer('letaky.auth.login_attempts_per_minute');

    foreach (range(1, $attempts) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'spatne-heslo']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('odhlásí uživatele', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('pošle přihlášeného z přihlašovací stránky na seznam slev', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('home'));
});

it('omezí pokusy o přihlášení z jedné IP i přes různé e-maily (R53)', function (): void {
    config(['letaky.auth.login_attempts_per_minute_per_ip' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->post(route('login.store'), ['email' => "nekdo{$attempt}@example.com", 'password' => 'spatne-heslo'])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => 'dalsi@example.com', 'password' => 'spatne-heslo'])
        ->assertTooManyRequests();
});
