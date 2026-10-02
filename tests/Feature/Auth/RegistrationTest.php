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
use Inertia\Testing\AssertableInertia as Assert;

/** Silné heslo, které projde Password::default(). */
const NEW_PASSWORD = 'Nove-heslo-2026';

it('zobrazí registrační stránku', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('urls.submit', '/register'));
});

it('zaregistruje uživatele, přihlásí ho a e-mail uloží malými písmeny', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Roman',
        'email' => 'Roman@Example.com',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])->assertRedirect('/');

    $user = User::query()->sole();
    expect($user->email)->toBe('roman@example.com');
    $this->assertAuthenticatedAs($user);
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
