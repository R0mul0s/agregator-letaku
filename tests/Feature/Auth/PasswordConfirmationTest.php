<?php

/**
 * Potvrzení hesla Fortify (`/ucet/potvrzeni-hesla`): aplikace ho nepoužívá (citlivé změny
 * potvrzuje heslem přímo ve formuláři, účet bez hesla přes poskytovatele, R96), routy ale
 * existují — nesmí spadnout ani potvrdit špatné heslo (R170).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Models\User;

it('stránka potvrzení hesla přesměruje do Mého účtu, nespadne', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertRedirect(route('account'));
});

it('potvrdí jen správné heslo a stav vrátí jako JSON', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson(route('password.confirmation'))->assertOk()->assertJson(['confirmed' => false]);
    $this->from(route('account'))->post(route('password.confirm.store'), ['password' => 'spatne'])->assertSessionHasErrors('password');
    $this->getJson(route('password.confirmation'))->assertJson(['confirmed' => false]);

    $this->post(route('password.confirm.store'), ['password' => 'password'])->assertSessionHasNoErrors();
    $this->getJson(route('password.confirmation'))->assertJson(['confirmed' => true]);
});

it('nepřihlášeného pošle na přihlášení', function (): void {
    $this->get(route('password.confirm'))->assertRedirect(route('login'));
});
