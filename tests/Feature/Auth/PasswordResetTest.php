<?php

/**
 * Obnova zapomenutého hesla odkazem z e-mailu (Fortify, R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('pošle odkaz pro nastavení hesla na e-mail účtu', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('zobrazí stránku nového hesla s tokenem a e-mailem z odkazu', function (): void {
    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'roman@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'abc')
            ->where('email', 'roman@example.com'));
});

it('nastaví nové heslo tokenem z e-mailu', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Nove-heslo-2026',
            'password_confirmation' => 'Nove-heslo-2026',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', __('passwords.reset'));

        return true;
    });

    expect(Hash::check('Nove-heslo-2026', $user->fresh()?->password ?? ''))->toBeTrue();
});
