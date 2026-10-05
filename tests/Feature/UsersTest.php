<?php

/**
 * Přehled uživatelů pro admina (R84): jen admin, poslední aktivita (zápis nejvýš jednou
 * za interval, bez změny updated_at), kdo je online, souhrn podle filtrů, nastavení
 * uživatele, hledání, řazení a profilový obrázek.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\LoyaltyProgram;
use App\Enums\StoreFormat;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-05 10:00:00');
    $this->admin = User::factory()->create(['name' => 'Admin Správce', 'is_admin' => true]);
    $this->user = User::factory()->create(['name' => 'Jana Nováková', 'email' => 'jana@example.com']);
});

it('přehled vidí jen admin', function (): void {
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->actingAs($this->user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($this->user)->get(route('users.avatar', $this->admin))->assertForbidden();

    $this->actingAs($this->admin)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users')
            ->where('auth.usersUrl', '/uzivatele')
            ->where('auth.accountActive', true));
});

it('odkaz na přehled v menu nemá běžný uživatel', function (): void {
    $this->actingAs($this->user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.usersUrl', null));
});

it('požadavek přihlášeného zapíše poslední aktivitu nejvýš jednou za interval', function (): void {
    $updatedAt = $this->user->updated_at;

    $this->actingAs($this->user)->get(route('home'))->assertOk();
    expect($this->user->fresh()?->last_seen_at?->toIso8601String())->toBe('2026-10-05T10:00:00+00:00')
        ->and($this->user->fresh()?->updated_at->equalTo($updatedAt))->toBeTrue();

    // Do minuty se znovu nezapisuje
    $this->travelTo('2026-10-05 10:00:59');
    $this->actingAs($this->user->fresh() ?? $this->user)->get(route('home'));
    expect($this->user->fresh()?->last_seen_at?->toIso8601String())->toBe('2026-10-05T10:00:00+00:00');

    $this->travelTo('2026-10-05 10:01:00');
    $this->actingAs($this->user->fresh() ?? $this->user)->get(route('offers'));
    expect($this->user->fresh()?->last_seen_at?->toIso8601String())->toBe('2026-10-05T10:01:00+00:00');
});

it('nepřihlášený návštěvník aktivitu nezapisuje', function (): void {
    $this->get(route('offers'))->assertOk();

    expect(User::query()->whereNotNull('last_seen_at')->count())->toBe(0);
});

it('ukáže, kdo je online a kdy byl kdo naposledy, se souhrnem podle filtrů', function (): void {
    $this->user->forceFill(['last_seen_at' => now()->subMinutes(3)])->save();
    User::factory()->create(['last_seen_at' => now()->subDays(3)]);
    User::factory()->create(['last_seen_at' => now()->subDays(20), 'email_verified_at' => null]);

    $this->actingAs($this->admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            // Admin právě přišel, Jana před 3 minutami — oba online
            ->where('counts.all', 4)
            ->where('counts.online', 2)
            ->where('counts.aktivni-1', 2)
            ->where('counts.aktivni-7', 3)
            ->where('counts.aktivni-30', 4)
            ->where('counts.neovereni', 1)
            ->where('total', 4)
            ->where('users.0.id', $this->admin->id)
            ->where('users.0.online', true)
            ->where('users.1.id', $this->user->id)
            ->where('users.1.online', true)
            ->where('users.1.lastSeenAt', '2026-10-05T09:57:00+00:00')
            ->where('users.2.online', false)
            ->where('users.3.emailVerified', false));
});

it('uživatel bez aktivity je v řazení podle aktivity na konci', function (): void {
    $this->user->forceFill(['last_seen_at' => now()->subHour()])->save();
    $never = User::factory()->create();

    $this->actingAs($this->admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.2.id', $never->id)
            ->where('users.2.lastSeenAt', null));
});

it('u uživatele ukáže jeho nastavení', function (): void {
    $this->user->forceFill([
        'digest_frequency' => DigestFrequency::Daily,
        'loyalty_programs' => collect([LoyaltyProgram::Clubcard]),
        'marketing_consent_at' => now()->subDay(),
        'min_discount_percent' => 30,
    ])->save();
    $this->user->followedChains()->create(['chain' => Chain::Tesco, 'store_format' => StoreFormat::Hypermarket, 'include_online_only' => false]);
    $this->user->followedChains()->create(['chain' => Chain::Kaufland, 'store_format' => null, 'include_online_only' => true, 'store_codes' => ['1100', '1200']]);
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'public_key' => 'key', 'auth_token' => 'auth', 'device' => 'Chrome']);

    $this->actingAs($this->admin)->get(route('users.index', ['q' => 'jana']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)
            ->where('users.0.chains', [
                ['chain' => 'kaufland', 'detail' => '2 prodejny'],
                ['chain' => 'tesco', 'detail' => 'Hypermarket, bez e-shopu'],
            ])
            ->where('users.0.loyaltyPrograms', ['Clubcard'])
            ->where('users.0.digestFrequency', 'daily')
            ->where('users.0.marketingConsent', true)
            ->where('users.0.minDiscountPercent', 30)
            ->where('users.0.pushDevices', 1)
            ->where('users.0.watchItems', 0));
});

it('filtruje podle nastavení a řadí podle jména', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Weekly])->save();

    $this->actingAs($this->admin)->get(route('users.index', ['kdo' => 'souhrn']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.souhrn', 1)
            ->where('total', 1)
            ->where('users.0.id', $this->user->id));

    $this->actingAs($this->admin)->get(route('users.index', ['razeni' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.0.name', 'Admin Správce')
            ->where('users.1.name', 'Jana Nováková'));

    $this->actingAs($this->admin)->get(route('users.index', ['kdo' => 'neexistuje']))->assertSessionHasErrors('kdo');
});

it('admin vidí profilový obrázek uživatele', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('avatars/jana.png', 'png');
    $this->user->forceFill(['avatar_path' => 'avatars/jana.png'])->save();

    $this->actingAs($this->admin)->get(route('users.index', ['q' => 'jana']))
        ->assertInertia(fn (Assert $page) => $page->where('users.0.avatarUrl', "/uzivatele/{$this->user->id}/obrazek?v=jana"));
    $this->actingAs($this->admin)->get(route('users.avatar', $this->user))->assertOk();
    $this->actingAs($this->admin)->get(route('users.avatar', $this->admin))->assertNotFound();
});
