<?php

/**
 * Můj účet — osobní údaje a heslo (Fortify, R12), profilový obrázek, přihlášená zařízení
 * a zrušení účtu (R40).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domain\Account\UserSessions;
use App\Enums\OffersSort;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Requests\OffersPreferencesRequest;
use App\Models\User;
use App\Models\WatchItem;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

/**
 * Hlavička obrázku PNG daných rozměrů — stačí na kontrolu typu a rozměrů
 * (getimagesize čte jen IHDR), kontejner nemusí mít GD.
 */
function pngOfSize(int $width, int $height): string
{
    $header = pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

    return "\x89PNG\r\n\x1a\n".pack('N', strlen($header)).'IHDR'.$header.pack('N', crc32('IHDR'.$header));
}

it('nahraje profilový obrázek, starý smaže a ukáže ho jen vlastníkovi', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->createWithContent('avatar.png', pngOfSize(256, 256))])
        ->assertSessionHas('status', AvatarController::STATUS_UPDATED);
    $first = $user->fresh()?->avatar_path;
    Storage::disk('local')->assertExists((string) $first);

    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.avatarUrl', fn (string $url): bool => str_starts_with($url, '/ucet/obrazek?v=')));
    $this->get(route('account.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png');

    $this->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->createWithContent('avatar.png', pngOfSize(128, 128))]);
    Storage::disk('local')->assertMissing((string) $first);

    $this->delete(route('account.avatar.destroy'));
    expect($user->fresh()?->avatar_path)->toBeNull();
    $this->get(route('account.avatar'))->assertNotFound();
});

it('obrázek větší než povolený rozměr nebo jiný soubor než obrázek nepřijme', function (): void {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    $this->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->createWithContent('avatar.png', pngOfSize(1024, 1024))])
        ->assertSessionHasErrors('avatar');
    $this->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->createWithContent('avatar.png', 'není obrázek')])
        ->assertSessionHasErrors('avatar');
});

it('účet není v hlavní navigaci, ale v menu pod avatarem', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.accountUrl', '/ucet')
            ->where('navigation', fn ($items): bool => ! collect($items)->contains('url', '/ucet')));
});

it('odhlásí ostatní zařízení jen se správným heslem a změní token pro zapamatování', function (): void {
    $user = User::factory()->create(['remember_token' => 'puvodni-token']);
    $this->actingAs($user)->from(route('account'));

    $this->delete(route('account.devices.logout'), ['password' => 'spatne-heslo'])
        ->assertSessionHasErrorsIn(AccountController::ERROR_BAG_DEVICES, 'password');
    expect($user->fresh()?->remember_token)->toBe('puvodni-token');

    $this->delete(route('account.devices.logout'), ['password' => UserFactory::PASSWORD])
        ->assertSessionHas('status', AccountController::STATUS_DEVICES_LOGGED_OUT);
    expect($user->fresh()?->remember_token)->not->toBe('puvodni-token');
});

it('vypíše platná přihlášení z databáze a ostatní zařízení odhlásí', function (): void {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
    DB::table('sessions')->insert([
        ['id' => 'tady', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => $chrome, 'payload' => '', 'last_activity' => 1_790_000_000],
        ['id' => 'mobil', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => $iphone, 'payload' => '', 'last_activity' => 1_790_000_100],
        ['id' => 'cizi', 'user_id' => $other->id, 'ip_address' => '10.0.0.3', 'user_agent' => $chrome, 'payload' => '', 'last_activity' => 1_790_000_200],
    ]);
    $this->travelTo(CarbonImmutable::createFromTimestampUTC(1_790_000_300));
    DB::table('sessions')->insert(['id' => 'vyprsela', 'user_id' => $user->id, 'ip_address' => '10.0.0.4', 'user_agent' => $chrome, 'payload' => '', 'last_activity' => 1_780_000_000]);
    $sessions = app(UserSessions::class);

    expect($sessions->forUser($user, 'tady'))->sequence(
        fn ($session) => $session->toMatchArray(['device' => 'Safari · iOS', 'current' => false]),
        fn ($session) => $session->toMatchArray(['device' => 'Chrome · Windows', 'ipAddress' => '10.0.0.1', 'current' => true]),
    );

    $sessions->logoutOthers($user, 'tady');

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['cizi', 'tady']);
});

it('zruší účet jen se správným heslem i s hlídanými položkami a obrázkem', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('avatars/obrazek.png', pngOfSize(256, 256));
    $user = User::factory()->create();
    $user->forceFill(['avatar_path' => 'avatars/obrazek.png'])->save();
    WatchItem::factory()->for($user)->create();
    $this->actingAs($user)->from(route('account'));

    $this->delete(route('account.destroy'), ['password' => 'spatne-heslo'])
        ->assertSessionHasErrorsIn(AccountController::ERROR_BAG_DELETE, 'password');
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $this->delete(route('account.destroy'), ['password' => UserFactory::PASSWORD])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('app.ui.account.deleted'));

    $this->assertGuest();
    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(WatchItem::query()->where('user_id', $user->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('avatars/obrazek.png');
});

it('uloží předvolby Mých slev a nepovolenou hodnotu odmítne (R41)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->from(route('account'));

    $this->put(route('account.offers-preferences'), ['offers_sort' => 'discount', 'min_discount_percent' => 30])
        ->assertSessionHas('status', AccountController::STATUS_OFFERS_PREFERENCES_SAVED);
    expect($user->fresh())
        ->offers_sort->toBe(OffersSort::Discount)
        ->min_discount_percent->toBe(30);

    $this->put(route('account.offers-preferences'), ['offers_sort' => 'unit_price', 'min_discount_percent' => null]);
    expect($user->fresh()?->min_discount_percent)->toBeNull();

    $this->put(route('account.offers-preferences'), ['offers_sort' => 'nahodne', 'min_discount_percent' => 33])
        ->assertSessionHasErrorsIn(OffersPreferencesRequest::ERROR_BAG, ['offers_sort', 'min_discount_percent']);
});
