<?php

/**
 * E-mailový souhrn nových akcí hlídaných položek (R42).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Account\MailingSubscriptions;
use App\Domain\Digest\Actions\SendDigests;
use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\MailingList;
use App\Http\Controllers\AccountController;
use App\Http\Requests\DigestRequest;
use App\Mail\DigestMail;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2026-10-02 06:30:00');
    $this->user = User::factory()->create(['name' => 'Roman Hlaváček']);
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Daily])->save();
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
});

it('první souhrn pošle se všemi aktuálními akcemi a zapamatuje si čas', function (): void {
    Offer::factory()->create(['name' => 'Máslo 250 g']);
    Offer::factory()->create(['name' => 'Vejce M']);

    expect(app(SendDigests::class)())->toBe(1);

    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo($this->user->email)
        && $mail->groups === [['name' => 'Máslo', 'offers' => [$mail->groups[0]['offers'][0]]]]
        && $mail->groups[0]['offers'][0]['name'] === 'Máslo 250 g');
    expect($this->user->fresh()?->digest_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');
});

it('další souhrn pošle až po intervalu a jen s akcemi, které přibyly', function (): void {
    Offer::factory()->create(['name' => 'Máslo staré']);
    app(SendDigests::class)();

    $this->travelTo('2026-10-02 13:00:00');
    Offer::factory()->create(['name' => 'Máslo nové']);
    expect(app(SendDigests::class)())->toBe(0);

    $this->travelTo('2026-10-03 06:30:00');
    expect(app(SendDigests::class)())->toBe(1);

    Mail::assertSent(DigestMail::class, 2);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => array_column($mail->groups[0]['offers'], 'name') === ['Máslo nové']);
});

it('bez nových akcí ani s vypnutým souhrnem nic nepošle', function (): void {
    expect(app(SendDigests::class)())->toBe(0);

    Offer::factory()->create(['name' => 'Máslo 250 g']);
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Off])->save();
    expect(app(SendDigests::class)())->toBe(0);

    Mail::assertNothingSent();
});

it('týdenní souhrn přijde nejdřív po týdnu', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Weekly, 'digest_sent_at' => now()->subDays(3)])->save();
    Offer::factory()->create(['name' => 'Máslo 250 g']);
    expect(app(SendDigests::class)())->toBe(0);

    $this->travelTo('2026-10-06 06:30:00');
    expect(app(SendDigests::class)())->toBe(1);
});

it('e-mail má předmět s počtem akcí a odkazy na Moje slevy a nastavení', function (): void {
    Offer::factory()->create(['name' => 'Máslo 250 g', 'price' => 3990]);
    app(SendDigests::class)();

    Mail::assertSent(DigestMail::class, function (DigestMail $mail): bool {
        $mail->assertHasSubject('Slevohlídka: 1 nová akce na hlídané zboží');
        $mail->assertSeeInHtml('Máslo 250 g');
        $mail->assertSeeInHtml('39,90');
        $mail->assertSeeInHtml(route('account').'#souhrn');

        return true;
    });
});

it('cron URL pošle souhrny jen s tokenem', function (): void {
    config(['letaky.cron.token' => 'tajny-token']);
    Offer::factory()->create(['name' => 'Máslo 250 g']);

    $this->get(route('cron.send-digests'))->assertNotFound();
    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
        ->assertOk()
        ->assertSeeText('Souhrny — odesláno: 1');
});

it('uloží četnost souhrnu; po zapnutí přijde první souhrn znovu celý', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Off, 'digest_sent_at' => now()->subMonth()])->save();
    $this->actingAs($this->user)->from(route('account'));

    $this->put(route('account.digest'), ['digest_frequency' => 'weekly'])
        ->assertSessionHas('status', AccountController::STATUS_DIGEST_SAVED);
    expect($this->user->fresh())
        ->digest_frequency->toBe(DigestFrequency::Weekly)
        ->digest_sent_at->toBeNull();

    $this->put(route('account.digest'), ['digest_frequency' => 'kazdou-hodinu'])
        ->assertSessionHasErrorsIn(DigestRequest::ERROR_BAG, 'digest_frequency');
});

it('na neověřenou adresu souhrn nepošle (R51)', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();
    Offer::factory()->create(['name' => 'Máslo 250 g']);

    expect(app(SendDigests::class)())->toBe(0);
    Mail::assertNothingSent();
});

it('e-mail má odhlášení jedním klepnutím v patičce i v hlavičkách (R51)', function (): void {
    Offer::factory()->create(['name' => 'Máslo 250 g']);
    app(SendDigests::class)();
    $unsubscribeUrl = app(MailingSubscriptions::class)->unsubscribeUrl($this->user, MailingList::Digest);

    Mail::assertSent(DigestMail::class, function (DigestMail $mail) use ($unsubscribeUrl): bool {
        $mail->assertSeeInHtml(e($unsubscribeUrl), escape: false);
        $mail->assertSeeInHtml(config('letaky.operator.name'));

        return $mail->headers()->text === [
            'List-Unsubscribe' => '<'.$unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    });
});
