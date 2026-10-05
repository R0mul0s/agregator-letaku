<?php

/**
 * Zprávy od nás do centra upozornění (R74, etapa 11d): jen admin, zpráva o službě všem,
 * propagační jen se souhlasem a nikdy do telefonu, validace odkazu, zobrazení v centru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use App\Http\Controllers\AnnouncementController;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Data formuláře zprávy.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function announcementInput(array $overrides = []): array
{
    return [
        'title' => 'Nově sledujeme Makro',
        'body' => "Od dnešního dne hlídáme i akce Makra.\nZapněte si ho v Mých obchodech.",
        'url' => '/obchody',
        'category' => 'service',
        'push' => true,
        ...$overrides,
    ];
}

beforeEach(function (): void {
    $this->travelTo('2026-10-05 10:00:00');
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create();
});

it('zprávy smí posílat jen admin', function (): void {
    $this->actingAs($this->user)->get(route('announcements.index'))->assertForbidden();
    $this->actingAs($this->user)->post(route('announcements.store'), announcementInput())->assertForbidden();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('admin vidí formulář s počty příjemců a v menu odkaz', function (): void {
    $this->user->forceFill(['marketing_consent_at' => now()])->save();

    $this->actingAs($this->admin)->get(route('announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Announcements')
            ->where('recipients', ['service' => 2, 'marketing' => 1])
            ->where('auth.announcementsUrl', '/zpravy'));

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.announcementsUrl', null));
});

it('zprávu o službě zapíše všem do centra', function (): void {
    $this->actingAs($this->admin)
        ->post(route('announcements.store'), announcementInput())
        ->assertRedirect(route('announcements.index'))
        ->assertSessionHas('status', AnnouncementController::STATUS_SENT);

    $announcement = Announcement::query()->sole();
    expect($announcement->recipients)->toBe(2)
        ->and($announcement->push)->toBeTrue()
        ->and($announcement->user_id)->toBe($this->admin->id)
        ->and(DatabaseNotification::query()->where('type', 'announcement')->pluck('notifiable_id')->sort()->values()->all())
        ->toBe([$this->admin->id, $this->user->id]);

    $record = $this->user->notifications()->sole();
    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.0.kind', 'announcement')
            ->where('notifications.0.title', 'Nově sledujeme Makro')
            ->where('notifications.0.text', "Od dnešního dne hlídáme i akce Makra.\nZapněte si ho v Mých obchodech.")
            ->where('notificationCenter.unread', 1));
    $this->get(route('notifications.show', $record->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('NotificationDetail')
            ->where('groups', [])
            ->where('announcement.url', '/obchody')
            ->where('announcement.external', false));
});

it('propagační zprávu zapíše jen se platným souhlasem a nikdy ne do telefonu', function (): void {
    $this->user->forceFill(['marketing_consent_at' => now()->subDay()])->save();
    $withdrawn = User::factory()->create();
    $withdrawn->forceFill(['marketing_consent_at' => now()->subDays(2), 'marketing_consent_withdrawn_at' => now()->subDay()])->save();

    $this->actingAs($this->admin)
        ->post(route('announcements.store'), announcementInput(['category' => 'marketing', 'push' => false, 'url' => 'https://partner.example.com/akce']))
        ->assertSessionHasNoErrors();

    expect(DatabaseNotification::query()->pluck('notifiable_id')->all())->toBe([$this->user->id])
        ->and(Announcement::query()->sole()->recipients)->toBe(1);

    $this->actingAs($this->user)->get(route('notifications.show', $this->user->notifications()->sole()->id))
        ->assertInertia(fn (Assert $page) => $page->where('announcement.external', true));
});

it('propagační zprávu do telefonu poslat nejde', function (): void {
    $this->actingAs($this->admin)
        ->post(route('announcements.store'), announcementInput(['category' => 'marketing', 'push' => true]))
        ->assertSessionHasErrors('push');

    expect(Announcement::query()->count())->toBe(0);
});

it('odkaz musí být cesta v aplikaci nebo https adresa', function (string $url, bool $valid): void {
    $response = $this->actingAs($this->admin)->post(route('announcements.store'), announcementInput(['url' => $url]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('url');
})->with([
    'cesta v aplikaci' => ['/akce?q=máslo', true],
    'https' => ['https://slevohlidka.rhsoft.cz/kontakt', true],
    'http' => ['http://example.com', false],
    'javascript' => ['javascript:alert(1)', false],
    'adresa bez schématu' => ['//evil.example.com', false],
]);

it('bez nadpisu a textu zprávu neodešle', function (): void {
    $this->actingAs($this->admin)
        ->post(route('announcements.store'), announcementInput(['title' => '', 'body' => '']))
        ->assertSessionHasErrors(['title', 'body']);
});

it('stránka ukáže odeslané zprávy od nejnovější', function (): void {
    $this->actingAs($this->admin)->post(route('announcements.store'), announcementInput(['title' => 'První']));
    $this->actingAs($this->admin)->post(route('announcements.store'), announcementInput(['title' => 'Druhá', 'push' => false]));

    $this->actingAs($this->admin)->get(route('announcements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('announcements.0.title', 'Druhá')
            ->where('announcements.0.push', false)
            ->where('announcements.0.recipients', 2)
            ->where('announcements.1.title', 'První'));
});
