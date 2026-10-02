<?php

/**
 * Stránka účtu — osobní údaje a změna hesla (Fortify, R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('zobrazí účet s adresami formulářů a názvy sad chyb', function (): void {
    $this->actingAs(User::factory()->create(['name' => 'Roman']))
        ->get(route('account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('auth.user.name', 'Roman')
            ->where('urls.profile', '/user/profile-information')
            ->where('urls.password', '/user/password')
            ->where('errorBags.profile', UpdateUserProfileInformation::ERROR_BAG)
            ->where('errorBags.password', UpdateUserPassword::ERROR_BAG));
});

it('uloží jméno a e-mail', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account'))
        ->put(route('user-profile-information.update'), ['name' => 'Nové jméno', 'email' => 'nove@example.com'])
        ->assertRedirect(route('account'))
        ->assertSessionHas('status', 'profile-information-updated');

    expect($user->fresh())
        ->name->toBe('Nové jméno')
        ->email->toBe('nove@example.com');
});

it('nezmění heslo se špatným současným heslem a chybu dá do vlastní sady', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account'))
        ->put(route('user-password.update'), [
            'current_password' => 'spatne-heslo',
            'password' => 'Nove-heslo-2026',
            'password_confirmation' => 'Nove-heslo-2026',
        ])
        ->assertSessionHasErrorsIn(UpdateUserPassword::ERROR_BAG, ['current_password' => __('validation.current_password')]);

    expect(Hash::check(UserFactory::PASSWORD, $user->fresh()?->password ?? ''))->toBeTrue();
});

it('změní heslo se správným současným heslem', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account'))
        ->put(route('user-password.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'Nove-heslo-2026',
            'password_confirmation' => 'Nove-heslo-2026',
        ])
        ->assertSessionHas('status', 'password-updated');

    expect(Hash::check('Nove-heslo-2026', $user->fresh()?->password ?? ''))->toBeTrue();
});
