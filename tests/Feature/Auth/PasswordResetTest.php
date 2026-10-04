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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('pošle odkaz pro nastavení hesla na e-mail účtu', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    // Texty e-mailu jsou klíče Laravelu přeložené v lang/cs.json — po aktualizaci frameworku se mění
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Nastavení nového hesla')
            ->and($mail->actionText)->toBe('Nastavit nové heslo')
            ->and($mail->introLines[0])->toStartWith('Tento e-mail vám přišel');

        return true;
    });
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

it('po obnově hesla odhlásí všechna zařízení (R67)', function (): void {
    Notification::fake();
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'utocnik', 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Nove-heslo-2026',
            'password_confirmation' => 'Nove-heslo-2026',
        ])->assertRedirect(route('login'));

        return true;
    });

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('odkaz pro obnovu hesla vede na adresu z APP_URL, ne z hlaviček požadavku (R67)', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->withHeaders(['X-Forwarded-Host' => 'zly.example', 'X-Forwarded-Prefix' => '/zly'])
        ->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim(config()->string('app.url'), '/').'/reset-password/');
    });
});
