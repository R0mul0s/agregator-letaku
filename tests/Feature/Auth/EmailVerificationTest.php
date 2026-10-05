<?php

/**
 * Ověření e-mailu (R51): odkaz z e-mailu, nový odkaz a lišta pro neověřený e-mail.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use App\Http\Responses\VerifyEmailResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

it('podepsaný odkaz z e-mailu adresu ověří a vrátí na Moje slevy s toastem', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('home', absolute: false))
        ->assertSessionHas('status', VerifyEmailResponse::STATUS_VERIFIED);

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

it('odkaz s otiskem cizího e-mailu adresu neověří', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('cizi@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('pošle nový odkaz a neověřenému sdílí stav pro lištu', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.emailVerified', false)
            ->where('auth.verificationSendUrl', '/overeni-emailu/znovu'));

    $this->from(route('offers'))
        ->post(route('verification.send'))
        ->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);
});
