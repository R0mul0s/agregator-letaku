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
use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use App\Http\Responses\RegisterResponse;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('zobrazí registrační stránku s ochranou proti botům', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('urls.submit', '/registrace')
            ->where('urls.terms', '/podminky')
            ->where('urls.privacy', '/ochrana-udaju')
            ->where('guard.tokenField', RegistrationGuard::TOKEN_FIELD)
            ->where('guard.trapField', RegistrationGuard::TRAP_FIELD)
            ->where('guard.token', fn (string $token): bool => $token !== ''));
});

it('vedle registrace i přihlášení ukáže počet akcí, obchody a akce s nejvyšší slevou (R56)', function (string $route, string $component): void {
    $this->travelTo('2026-10-02 10:00:00');
    Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Máslo', 'discount_percent' => 40, 'image_url' => 'https://example.com/maslo.jpg']);
    Offer::factory()->create(['chain' => Chain::Penny, 'name' => 'Bez obrázku', 'discount_percent' => 50, 'image_url' => null]);

    $this->get(route($route))->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->where('showcase.offers', 2)
        ->where('showcase.chains', fn ($chains): bool => in_array('lidl', $chains->all(), true))
        ->has('showcase.deals', 1)
        ->where('showcase.deals.0.name', 'Máslo'));
})->with([
    'registrace' => ['register', 'Auth/Register'],
    'přihlášení' => ['login', 'Auth/Login'],
]);

it('zaregistruje uživatele, přihlásí ho, e-mail uloží malými písmeny a pošle odkaz na ověření', function (): void {
    Notification::fake();
    $this->travelTo('2026-10-03 10:00:00');

    $this->post(route('register.store'), registrationInput(['email' => 'Roman@Example.com']))
        ->assertRedirect(route('watch-items.index'))
        ->assertSessionHas('status', RegisterResponse::STATUS_REGISTERED);

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

it('nový účet sleduje všechny obchody se zdrojem, bez upřesnění (R55)', function (): void {
    $this->post(route('register.store'), registrationInput());

    $followed = User::query()->sole()->followedChains()->get();
    expect($followed->map(fn (FollowedChain $chain): string => $chain->chain->value)->sort()->values()->all())
        ->toBe(collect(app(ChainCatalog::class)->available())->map(fn (Chain $chain): string => $chain->value)->sort()->values()->all())
        ->and($followed->every(fn (FollowedChain $chain): bool => $chain->store_format === null && $chain->include_online_only))->toBeTrue();
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

it('heslo při registraci nechce potvrzení — formulář má tlačítko Ukázat heslo (R56)', function (): void {
    $input = registrationInput();
    unset($input['password_confirmation']);

    $this->post(route('register.store'), $input)->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('krátké heslo odmítne a chybu napíše česky', function (): void {
    $this->post(route('register.store'), registrationInput(['password' => 'kratke']))
        ->assertSessionHasErrors('password');

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
