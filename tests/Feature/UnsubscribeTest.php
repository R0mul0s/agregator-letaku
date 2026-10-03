<?php

/**
 * Odhlášení z e-mailů jedním klepnutím bez přihlášení (R51).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use App\Domain\Account\MailingSubscriptions;
use App\Enums\DigestFrequency;
use App\Enums\MailingList;
use App\Http\Controllers\UnsubscribeController;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'roman@example.com']);
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Daily])->save();
    $this->url = app(MailingSubscriptions::class)->unsubscribeUrl($this->user, MailingList::Digest);
});

it('otevření odkazu jen ukáže potvrzení a nic nezmění (odkazy otevírají i skenery)', function (): void {
    $this->get($this->url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Unsubscribe')
            ->where('email', 'roman@example.com')
            ->where('list', 'souhrn akcí')
            ->where('subscribed', true));

    expect($this->user->fresh()?->digest_frequency)->toBe(DigestFrequency::Daily);
});

it('odeslání vypne souhrn bez přihlášení', function (): void {
    $this->post($this->url)
        ->assertRedirect($this->url)
        ->assertSessionHas('status', UnsubscribeController::STATUS_UNSUBSCRIBED);

    expect($this->user->fresh()?->digest_frequency)->toBe(DigestFrequency::Off);
    $this->assertGuest();
});

it('odkaz bez platného podpisu odmítne', function (): void {
    $this->post(route('unsubscribe.store', ['user' => $this->user->id, 'list' => MailingList::Digest->value]))->assertForbidden();
    // Podepsaný odkaz s vyměněným uživatelem = odhlášení cizího účtu
    $other = User::factory()->create();
    $other->forceFill(['digest_frequency' => DigestFrequency::Daily])->save();
    $this->post(str_replace('/'.$this->user->id.'/', '/'.$other->id.'/', $this->url))->assertForbidden();

    expect($this->user->fresh()?->digest_frequency)->toBe(DigestFrequency::Daily)
        ->and($other->fresh()?->digest_frequency)->toBe(DigestFrequency::Daily);
});

it('z obchodních sdělení odhlásí odvoláním souhlasu', function (): void {
    $subscriptions = app(MailingSubscriptions::class);
    $subscriptions->setMarketingConsent($this->user, true);

    $this->post($subscriptions->unsubscribeUrl($this->user, MailingList::Marketing));

    expect($this->user->fresh())
        ->hasMarketingConsent()->toBeFalse()
        ->marketing_consent_withdrawn_at->not->toBeNull();
});
