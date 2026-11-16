<?php

/**
 * Obnova zapomenutého hesla odkazem z e-mailu (Fortify, R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\SocialProvider;
use App\Models\PushSubscription;
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
            ->where('email', 'roman@example.com')
            ->has('showcase.offers'));
});

it('stránka zapomenutého hesla má data panelu s akcemi — bez nich spadne (R56)', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotPassword')
            ->has('showcase.offers')
            ->has('showcase.chains')
            ->has('showcase.deals'));
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

        return str_starts_with($url, rtrim(config()->string('app.url'), '/').'/nove-heslo/');
    });
});

it('neexistující účet ani opakovaná žádost nejdou poznat podle odpovědi (R170)', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => 'nikdo@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    $this->post(route('password.email'), ['email' => $user->email]);
    // Druhá žádost hned po první: broker ji odmítne (throttled), odpověď je stejná
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));
});

it('obnova hesla neověřeného účtu ověří e-mail a zruší propojení cizích účtů (R170)', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->socialAccounts()->create(['provider' => SocialProvider::Facebook, 'provider_user_id' => 'utocnik']);
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

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->socialAccounts()->exists())->toBeFalse();
});

it('obnova hesla zruší upozornění v telefonu, neověřenému účtu i odkaz na sdílený seznam (R185)', function (bool $verified): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    $user->renewShoppingShareToken();
    PushSubscription::query()->create(['user_id' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/utocnik', 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    $shareToken = $user->shopping_share_token;
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

    $user->refresh();
    expect($user->pushSubscriptions()->exists())->toBeFalse()
        // Ověřený účet sdílel odkaz sám — rodině ho obnova hesla nebere
        ->and($user->shopping_share_token)->toBe($verified ? $shareToken : null);
})->with(['ověřený účet' => true, 'neověřený účet' => false]);
