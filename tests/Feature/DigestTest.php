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
use App\Enums\ScrapeStatus;
use App\Http\Controllers\AccountController;
use App\Http\Requests\DigestRequest;
use App\Mail\DigestMail;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Akce Kauflandu, jako by ji právě přineslo stažení — souhrn počítá jen s uživateli,
 * od jejichž posledního souhrnu stažení doběhlo (R58).
 *
 * @param  array<string, mixed>  $attributes
 */
function importedOffer(array $attributes): Offer
{
    ScrapeRun::query()->create(['chain' => Chain::Kaufland, 'status' => ScrapeStatus::Succeeded, 'started_at' => now(), 'finished_at' => now()]);

    return Offer::factory()->create($attributes);
}

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2026-10-02 06:30:00');
    $this->user = User::factory()->create(['name' => 'Roman Hlaváček']);
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Daily])->save();
    FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
});

it('první souhrn pošle se všemi aktuálními akcemi a zapamatuje si čas', function (): void {
    importedOffer(['name' => 'Máslo 250 g']);
    importedOffer(['name' => 'Vejce M']);

    expect(app(SendDigests::class)())->toBe(1);

    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo($this->user->email)
        && $mail->groups === [['name' => 'Máslo', 'offers' => [$mail->groups[0]['offers'][0]]]]
        && $mail->groups[0]['offers'][0]['name'] === 'Máslo 250 g');
    expect($this->user->fresh()?->digest_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');
});

it('další souhrn pošle až po intervalu a jen s akcemi, které přibyly', function (): void {
    importedOffer(['name' => 'Máslo staré']);
    app(SendDigests::class)();

    $this->travelTo('2026-10-02 13:00:00');
    importedOffer(['name' => 'Máslo nové']);
    expect(app(SendDigests::class)())->toBe(0);

    $this->travelTo('2026-10-03 06:30:00');
    expect(app(SendDigests::class)())->toBe(1);

    Mail::assertSent(DigestMail::class, 2);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => array_column($mail->groups[0]['offers'], 'name') === ['Máslo nové']);
});

it('bez nových akcí ani s vypnutým souhrnem nic nepošle', function (): void {
    expect(app(SendDigests::class)())->toBe(0);

    importedOffer(['name' => 'Máslo 250 g']);
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Off])->save();
    expect(app(SendDigests::class)())->toBe(0);

    Mail::assertNothingSent();
});

it('týdenní souhrn přijde nejdřív po týdnu', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Weekly, 'digest_sent_at' => now()->subDays(3)])->save();
    importedOffer(['name' => 'Máslo 250 g']);
    expect(app(SendDigests::class)())->toBe(0);

    $this->travelTo('2026-10-06 06:30:00');
    expect(app(SendDigests::class)())->toBe(1);
});

it('e-mail má předmět s počtem akcí a odkazy na Moje slevy a nastavení', function (): void {
    importedOffer(['name' => 'Máslo 250 g', 'price' => 3990]);
    app(SendDigests::class)();

    Mail::assertSent(DigestMail::class, function (DigestMail $mail): bool {
        $mail->assertHasSubject('Slevohlídka: 1 nová akce na hlídané zboží');
        $mail->assertSeeInHtml('Máslo 250 g');
        $mail->assertSeeInHtml('39,90');
        $mail->assertSeeInHtml(route('account').'#souhrn');

        return true;
    });
});

it('akci, která ještě nezačala, pošle hned se začátkem platnosti, aktuální jen s koncem (R76)', function (): void {
    importedOffer(['name' => 'Máslo dnes', 'valid_from' => '2026-10-01', 'valid_to' => '2026-10-07']);
    importedOffer(['name' => 'Máslo ve středu', 'valid_from' => '2026-10-08', 'valid_to' => '2026-10-14']);
    app(SendDigests::class)();

    Mail::assertSent(DigestMail::class, function (DigestMail $mail): bool {
        $mail->assertSeeInHtml('Máslo ve středu');
        $mail->assertSeeInHtml("od 8.\u{00A0}10. do 14.\u{00A0}10.");
        $mail->assertSeeInHtml("Kaufland · do 7.\u{00A0}10.");

        return true;
    });
});

it('cron URL pošle souhrny jen s tokenem', function (): void {
    config(['letaky.cron.token' => 'tajny-token']);
    importedOffer(['name' => 'Máslo 250 g']);

    $this->get(route('cron.send-digests'))->assertNotFound();
    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
        ->assertOk()
        ->assertSeeText('Souhrny — odesláno: 1');
});

it('souběžné spuštění cronu upozornění nic nepošle (R113)', function (): void {
    config(['letaky.cron.token' => 'tajny-token']);
    importedOffer(['name' => 'Máslo 250 g']);
    $running = Cache::lock('cron.exclusive.notification-channels', 300);
    $running->get();

    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))->assertConflict();
    $this->artisan('letaky:send-digests')->assertFailed();
    Mail::assertNothingSent();

    $running->release();
    $this->get(route('cron.send-digests', ['token' => 'tajny-token']))->assertOk();
    Mail::assertSent(DigestMail::class);
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
    importedOffer(['name' => 'Máslo 250 g']);

    expect(app(SendDigests::class)())->toBe(0);
    Mail::assertNothingSent();
});

it('e-mail má odhlášení jedním klepnutím v patičce i v hlavičkách a jen motto bez provozovatele (R51, R81)', function (): void {
    importedOffer(['name' => 'Máslo 250 g']);
    app(SendDigests::class)();
    $unsubscribeUrl = app(MailingSubscriptions::class)->unsubscribeUrl($this->user, MailingList::Digest);

    Mail::assertSent(DigestMail::class, function (DigestMail $mail) use ($unsubscribeUrl): bool {
        $mail->assertSeeInHtml(e($unsubscribeUrl), escape: false);
        $mail->assertSeeInHtml(__('app.mail.footer'));
        $mail->assertDontSeeInHtml(config('letaky.operator.email'));

        return $mail->headers()->text === [
            'List-Unsubscribe' => '<'.$unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    });
});

it('jedno volání zpracuje jen dávku uživatelů, od nejdéle čekajících (R54)', function (): void {
    config(['letaky.digest.users_per_run' => 1]);
    $other = User::factory()->create();
    $other->forceFill(['digest_frequency' => DigestFrequency::Daily])->save();
    FollowedChain::query()->create(['user_id' => $other->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
    WatchItem::factory()->for($other)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
    importedOffer(['name' => 'Máslo 250 g']);

    expect(app(SendDigests::class)())->toBe(1);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo($this->user->email));

    expect(app(SendDigests::class)())->toBe(1);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo($other->email));

    expect(app(SendDigests::class)())->toBe(0);
});

it('uživatele bez nových akcí zapíše jako zpracovaného, aby nezabíral dávku (R54)', function (): void {
    // Stažení doběhlo, ale hlídanou položku nenašlo
    importedOffer(['name' => 'Vejce M']);

    expect(app(SendDigests::class)())->toBe(0)
        ->and($this->user->fresh()?->digest_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');

    // Akce, která přibyla po zpracování, přijde v dalším souhrnu
    $this->travelTo('2026-10-02 12:00:00');
    importedOffer(['name' => 'Máslo 250 g']);
    $this->travelTo('2026-10-03 06:30:00');

    expect(app(SendDigests::class)())->toBe(1);
});

it('okamžité upozornění přijde po stažení s novou akcí, nejvýš jednou za hodinu (R58)', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Instant])->save();
    importedOffer(['name' => 'Máslo ráno']);
    expect(app(SendDigests::class)())->toBe(1);

    // Další stažení za půl hodiny — hodina od posledního upozornění ještě neuplynula
    $this->travelTo('2026-10-02 07:00:00');
    importedOffer(['name' => 'Máslo dopoledne']);
    expect(app(SendDigests::class)())->toBe(0);

    $this->travelTo('2026-10-02 07:30:00');
    expect(app(SendDigests::class)())->toBe(1);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => array_column($mail->groups[0]['offers'], 'name') === ['Máslo dopoledne']);
});

it('bez stažení od posledního souhrnu uživatele vůbec nezpracuje (R58)', function (): void {
    $this->user->forceFill(['digest_frequency' => DigestFrequency::Instant])->save();
    importedOffer(['name' => 'Máslo ráno']);
    app(SendDigests::class)();

    $this->travelTo('2026-10-02 09:30:00');
    expect(app(SendDigests::class)())->toBe(0)
        ->and($this->user->fresh()?->digest_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');
});
