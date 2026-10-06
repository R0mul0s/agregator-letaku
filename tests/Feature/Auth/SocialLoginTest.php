<?php

/**
 * Přihlášení přes Google a Facebook (R96): přihlášení a dokončení registrace se souhlasy,
 * připojení k účtu jen s ověřeným e-mailem, propojení a odpojení v Mém účtu a potvrzení
 * totožnosti účtu bez hesla před citlivou změnou. Poskytovatele nahrazuje Socialite::fake.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Account\Social\SocialLoginRefused;
use App\Enums\SocialProvider;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Responses\RegisterResponse;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/** ID účtu Google v testech (OpenID `sub`). */
const GOOGLE_ID = '109876543210';

beforeEach(function (): void {
    config([
        'services.google.client_id' => 'google-client',
        'services.google.client_secret' => 'google-secret',
        'services.facebook.client_id' => 'facebook-client',
        'services.facebook.client_secret' => 'facebook-secret',
    ]);
});

/**
 * Uživatel, kterého „vrátí“ poskytovatel. Google posílá v odpovědi userinfo `email_verified`.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeSocialUser(SocialProvider $provider, array $overrides = []): void
{
    Socialite::fake($provider->value, SocialiteUser::fake([
        'id' => GOOGLE_ID,
        'name' => 'Roman Hlaváček',
        'email' => 'roman@gmail.com',
        'email_verified' => $provider === SocialProvider::Google,
        ...$overrides,
    ]));
}

/**
 * Účet bez hesla propojený s Googlem.
 */
function passwordlessUser(): User
{
    $user = User::factory()->create(['email' => 'roman@gmail.com', 'password' => null]);
    $user->socialAccounts()->create(['provider' => SocialProvider::Google, 'provider_user_id' => GOOGLE_ID]);

    return $user;
}

it('nabídne na přihlášení a registraci jen poskytovatele s klíči', function (): void {
    config(['services.facebook.client_id' => null]);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('Auth/Login')
        ->has('social', 1)
        ->where('social.0.provider', 'google')
        ->where('social.0.url', '/prihlaseni/google')
        ->where('rememberParameter', SocialLoginController::REMEMBER_PARAMETER));
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->has('social', 1));

    $this->get('/prihlaseni/facebook')->assertNotFound();
});

it('bez klíčů v .env (null) přihlášení funguje bez tlačítek', function (): void {
    config(['services.google.client_id' => null, 'services.facebook.client_id' => null]);

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('social', 0));
    $this->get('/prihlaseni/google')->assertNotFound();
});

it('pošle nepřihlášeného k poskytovateli', function (): void {
    fakeSocialUser(SocialProvider::Google);

    $this->get(route('social.redirect', ['provider' => 'google']))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

it('nového uživatele z Googlu pošle dokončit registraci a založí účet bez hesla s ověřeným e-mailem', function (): void {
    Notification::fake();
    $this->travelTo('2026-10-06 10:00:00');
    fakeSocialUser(SocialProvider::Google, ['email' => 'Roman@Gmail.com']);

    $this->get(route('social.callback', ['provider' => 'google']))->assertRedirect(route('social.register'));
    $this->assertGuest();

    $this->get(route('social.register'))->assertInertia(fn (Assert $page) => $page
        ->component('Auth/SocialRegister')
        ->where('identity.provider', 'google')
        ->where('identity.name', 'Roman Hlaváček')
        ->where('identity.email', 'roman@gmail.com')
        ->where('identity.emailVerified', true));

    $this->post(route('social.register.store'), ['name' => 'Roman', 'terms' => true])
        ->assertRedirect(route('watch-items.index'))
        ->assertSessionHas('status', RegisterResponse::STATUS_REGISTERED);

    $user = User::query()->sole();
    expect($user)
        ->name->toBe('Roman')
        ->email->toBe('roman@gmail.com')
        ->hasPassword()->toBeFalse()
        ->email_verified_at->not->toBeNull()
        ->terms_accepted_at->toDateTimeString()->toBe('2026-10-06 10:00:00')
        ->hasMarketingConsent()->toBeFalse()
        ->and($user->followedChains()->count())->toBeGreaterThan(0)
        ->and($user->socialAccounts()->sole())
        ->provider->toBe(SocialProvider::Google)
        ->provider_user_id->toBe(GOOGLE_ID);
    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

it('bez souhlasu s podmínkami účet nezaloží', function (): void {
    fakeSocialUser(SocialProvider::Google);
    $this->get(route('social.callback', ['provider' => 'google']));

    $this->post(route('social.register.store'), ['name' => 'Roman', 'terms' => false])
        ->assertSessionHasErrors('terms');
    expect(User::query()->exists())->toBeFalse();
});

it('dokončení registrace bez přihlášení u poskytovatele vrátí na registraci', function (): void {
    $this->get(route('social.register'))->assertRedirect(route('register'));
    $this->post(route('social.register.store'), ['name' => 'Roman', 'terms' => true])->assertRedirect(route('register'));
});

it('e-mail z Facebooku bere jako neověřený a pošle odkaz k ověření', function (): void {
    Notification::fake();
    fakeSocialUser(SocialProvider::Facebook);

    $this->get(route('social.callback', ['provider' => 'facebook']));
    $this->post(route('social.register.store'), ['name' => 'Roman', 'terms' => true]);

    $user = User::query()->sole();
    expect($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('po registraci přes Google začne hlídat produkt z karty akce (R60)', function (): void {
    $product = Product::factory()->create();
    fakeSocialUser(SocialProvider::Google);

    $this->get(route('register', [RegisterResponse::WATCH_PARAMETER => $product->id]));
    $this->get(route('social.callback', ['provider' => 'google']));
    $this->post(route('social.register.store'), ['name' => 'Roman', 'terms' => true]);

    expect(User::query()->sole()->watchItems()->sole()->product_id)->toBe($product->id);
});

it('uživatele s propojeným účtem přihlásí a se „Zapamatovat si mě“ uloží cookie', function (): void {
    $user = passwordlessUser();
    fakeSocialUser(SocialProvider::Google, ['email' => 'jiny@gmail.com']);

    $this->get(route('social.redirect', ['provider' => 'google', SocialLoginController::REMEMBER_PARAMETER => 1]));
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect('/')
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

it('k účtu s ověřeným e-mailem od Googlu Google připojí a přihlásí', function (): void {
    $user = User::factory()->create(['email' => 'roman@gmail.com']);
    fakeSocialUser(SocialProvider::Google);

    $this->get(route('social.callback', ['provider' => 'google']))->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->hasPassword())->toBeTrue()
        ->and($user->socialAccounts()->sole()->provider_user_id)->toBe(GOOGLE_ID);
});

it('účet s neověřeným e-mailem převezme majitel adresy z Googlu a heslo zakladatele zahodí', function (): void {
    $user = User::factory()->create(['email' => 'roman@gmail.com', 'email_verified_at' => null]);
    fakeSocialUser(SocialProvider::Google);

    $this->get(route('social.callback', ['provider' => 'google']));

    $user->refresh();
    $this->assertAuthenticatedAs($user);
    expect($user->hasPassword())->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('neověřený e-mail z Facebooku k existujícímu účtu nepřipojí', function (): void {
    $user = User::factory()->create(['email' => 'roman@gmail.com']);
    fakeSocialUser(SocialProvider::Facebook);

    $this->get(route('social.callback', ['provider' => 'facebook']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([SocialLoginController::ERROR_KEY => __('app.ui.auth.social.refused.'.SocialLoginRefused::EMAIL_TAKEN)]);

    $this->assertGuest();
    expect($user->socialAccounts()->exists())->toBeFalse();
});

it('bez e-mailu od poskytovatele účet nezaloží', function (): void {
    fakeSocialUser(SocialProvider::Facebook, ['email' => null]);

    $this->get(route('social.callback', ['provider' => 'facebook']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([SocialLoginController::ERROR_KEY => __('app.ui.auth.social.refused.'.SocialLoginRefused::EMAIL_MISSING)]);
});

it('zrušené přihlášení u poskytovatele vrátí na přihlášení s vysvětlením', function (): void {
    fakeSocialUser(SocialProvider::Google);

    $this->get(route('social.callback', ['provider' => 'google', 'error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([SocialLoginController::ERROR_KEY => __('app.ui.auth.social.refused.'.SocialLoginRefused::CANCELLED)]);
    $this->assertGuest();
});

it('v Mém účtu propojí Google a ukáže stav propojení', function (): void {
    $user = User::factory()->create();
    fakeSocialUser(SocialProvider::Google);
    $this->actingAs($user);

    $this->get(route('social.link', ['provider' => 'google', SocialLoginController::SECTION_PARAMETER => 'zabezpeceni']))
        ->assertRedirect('https://socialite.fake/google/authorize');
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('account').'#zabezpeceni')
        ->assertSessionHas('status', SocialLoginController::STATUS_LINKED);

    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page
        ->where('social.hasPassword', true)
        ->where('social.providers.0.provider', 'google')
        ->where('social.providers.0.linked', true)
        ->where('social.providers.1.linked', false));
});

it('účet Googlu propojený s jiným uživatelem znovu nepropojí', function (): void {
    passwordlessUser();
    $other = User::factory()->create();
    fakeSocialUser(SocialProvider::Google);
    $this->actingAs($other);

    $this->get(route('social.link', ['provider' => 'google']));
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertSessionHas('status', __('app.ui.auth.social.refused.'.SocialLoginRefused::ALREADY_LINKED_ELSEWHERE));

    expect($other->socialAccounts()->exists())->toBeFalse();
});

it('poslední způsob přihlášení odpojit nedovolí, s heslem ano', function (): void {
    $user = passwordlessUser();
    $this->actingAs($user)->from(route('account'));

    $this->delete(route('social.unlink', ['provider' => 'google']))
        ->assertSessionHas('status', __('app.ui.auth.social.refused.'.SocialLoginRefused::LAST_LOGIN_METHOD));
    expect($user->socialAccounts()->exists())->toBeTrue();

    $user->forceFill(['password' => UserFactory::PASSWORD])->save();
    $this->delete(route('social.unlink', ['provider' => 'google']))
        ->assertSessionHas('status', SocialLoginController::STATUS_UNLINKED);
    expect($user->socialAccounts()->exists())->toBeFalse();
});

it('účet bez hesla zruší až po potvrzení přes propojený Google', function (): void {
    $user = passwordlessUser();
    fakeSocialUser(SocialProvider::Google);
    $this->actingAs($user)->from(route('account'));

    $this->delete(route('account.destroy'))
        ->assertSessionHasErrorsIn(AccountController::ERROR_BAG_DELETE, ['password' => __('app.ui.account.social.confirm_required')]);
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $this->get(route('social.confirm', ['provider' => 'google', SocialLoginController::SECTION_PARAMETER => 'zruseni-uctu']));
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('account').'#zruseni-uctu')
        ->assertSessionHas('status', SocialLoginController::STATUS_CONFIRMED);
    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('social.identityConfirmed', true));

    $this->delete(route('account.destroy'))->assertRedirect(route('login'));
    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(SocialAccount::query()->exists())->toBeFalse();
});

it('potvrzení vyprší po nastavené době', function (): void {
    $user = passwordlessUser();
    fakeSocialUser(SocialProvider::Google);
    $this->actingAs($user)->from(route('account'));

    $this->get(route('social.confirm', ['provider' => 'google']));
    $this->get(route('social.callback', ['provider' => 'google']));
    $this->travel(config()->integer('letaky.auth.social.confirmation_minutes') + 1)->minutes();

    $this->delete(route('account.destroy'))->assertSessionHasErrorsIn(AccountController::ERROR_BAG_DELETE, 'password');
});

it('potvrzení jiným účtem Googlu, než který je propojený, nepřijme', function (): void {
    $user = passwordlessUser();
    fakeSocialUser(SocialProvider::Google, ['id' => 'cizi-ucet']);
    $this->actingAs($user);

    $this->get(route('social.confirm', ['provider' => 'google']));
    $this->get(route('social.callback', ['provider' => 'google']))
        ->assertSessionHas('status', __('app.ui.auth.social.refused.'.SocialLoginRefused::CONFIRMATION_MISMATCH));

    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('social.identityConfirmed', false));
});

it('potvrzení přes nepropojeného poskytovatele nenabídne', function (): void {
    $this->actingAs(passwordlessUser());

    $this->get(route('social.confirm', ['provider' => 'facebook']))->assertNotFound();
});

it('účet bez hesla si po potvrzení nastaví heslo bez současného hesla a změní e-mail', function (): void {
    $user = passwordlessUser();
    fakeSocialUser(SocialProvider::Google);
    $this->actingAs($user)->from(route('account'));

    $this->put(route('user-password.update'), ['password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertSessionHasErrorsIn('updatePassword', 'current_password');
    $this->put(route('user-profile-information.update'), ['name' => $user->name, 'email' => 'novy@example.com'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');

    $this->get(route('social.confirm', ['provider' => 'google']));
    $this->get(route('social.callback', ['provider' => 'google']));

    $this->put(route('user-password.update'), ['password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])
        ->assertSessionHasNoErrors();
    expect($user->fresh()?->hasPassword())->toBeTrue();
});

it('heslem se účet bez hesla nepřihlásí', function (): void {
    passwordlessUser();

    $this->post(route('login.store'), ['email' => 'roman@gmail.com', 'password' => ''])->assertSessionHasErrors();
    $this->post(route('login.store'), ['email' => 'roman@gmail.com', 'password' => 'cokoli-dlouheho'])->assertSessionHasErrors('email');

    $this->assertGuest();
});
