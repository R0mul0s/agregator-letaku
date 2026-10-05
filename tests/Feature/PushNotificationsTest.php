<?php

/**
 * Upozornění v telefonu — web push (R66): zapnutí a vypnutí na zařízení, adresy jen push
 * služeb (SSRF), zkušební upozornění, upozornění na nové akce z cronu a skutečné odeslání
 * zašifrované zprávy (s podvrženou odpovědí push služby — testy nesahají na síť, R11).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Domain\Notifications\Actions\RecordNewOffers;
use App\Domain\Push\Actions\SendPushNotifications;
use App\Domain\Push\PushDelivery;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSender;
use App\Domain\Push\Vapid;
use App\Domain\Push\WebPushSender;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Http\Controllers\PushSubscriptionController;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\PushSubscription;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Inertia\Testing\AssertableInertia as Assert;
use Minishlink\WebPush\VAPID as VapidKeys;

/** Adresa push služby Chromu (FCM) — tvar jako ze skutečného prohlížeče. */
const PUSH_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/dXJkZW1vOkFQQTkx';

/**
 * Klíče prohlížeče pro šifrování zprávy: bod křivky P-256 (p256dh) a tajemství auth, base64url.
 *
 * @return array{p256dh: string, auth: string}
 */
function browserPushKeys(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = $key === false ? throw new RuntimeException('OpenSSL neumí P-256.') : openssl_pkey_get_details($key);
    $encode = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return [
        'p256dh' => $encode("\x04".$details['ec']['x'].$details['ec']['y']),
        'auth' => $encode(random_bytes(16)),
    ];
}

/**
 * Odesílač, který si zprávy jen zapamatuje — stav doručení podle adresy.
 *
 * @param  array<string, PushDelivery>  $deliveries
 */
function fakePushSender(array $deliveries = []): PushSender
{
    $sender = new class($deliveries) implements PushSender
    {
        /** @var list<array{endpoint: string, message: PushMessage}> */
        public array $sent = [];

        /**
         * @param  array<string, PushDelivery>  $deliveries
         */
        public function __construct(private readonly array $deliveries) {}

        /**
         * Zapamatuje si zprávu a vrátí nastavený stav (výchozí odesláno).
         */
        public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
        {
            $this->sent[] = ['endpoint' => $subscription->endpoint, 'message' => $message];

            return $this->deliveries[$subscription->endpoint] ?? PushDelivery::Sent;
        }
    };
    app()->instance(PushSender::class, $sender);

    return $sender;
}

/**
 * Jako cron: zapíše záznamy centra upozornění (R74) a pošle z nich upozornění v telefonu.
 */
function recordAndPush(): int
{
    app(RecordNewOffers::class)();

    return app(SendPushNotifications::class)();
}

/**
 * Akce Kauflandu, jako by ji právě přineslo stažení (upozornění počítá jen se staženími).
 *
 * @param  array<string, mixed>  $attributes
 */
function pushImportedOffer(array $attributes): Offer
{
    ScrapeRun::query()->create(['chain' => Chain::Kaufland, 'status' => ScrapeStatus::Succeeded, 'started_at' => now(), 'finished_at' => now()]);

    return Offer::factory()->create(['chain' => Chain::Kaufland, ...$attributes]);
}

beforeEach(function (): void {
    $keys = VapidKeys::createVapidKeys();
    config(['letaky.push.vapid.public_key' => $keys['publicKey'], 'letaky.push.vapid.private_key' => $keys['privateKey']]);
    $this->travelTo('2026-10-02 06:30:00');
    $this->user = User::factory()->create();
});

it('zapne upozornění na zařízení, první začne počítat akce od teď', function (): void {
    $keys = browserPushKeys();

    $this->actingAs($this->user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36')
        ->from(route('account'))
        ->post(route('account.push.store'), ['endpoint' => PUSH_ENDPOINT, 'keys' => $keys])
        ->assertRedirect(route('account'))
        ->assertSessionHas('status', PushSubscriptionController::STATUS_ENABLED);

    $subscription = $this->user->pushSubscriptions()->sole();
    expect($subscription->public_key)->toBe($keys['p256dh'])
        ->and($subscription->device)->toBe('Chrome · Android')
        ->and($this->user->fresh()?->push_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');
});

it('nepřijme adresu, která nepatří push službě prohlížeče', function (string $endpoint): void {
    $this->actingAs($this->user)
        ->post(route('account.push.store'), ['endpoint' => $endpoint, 'keys' => browserPushKeys()])
        ->assertSessionHasErrors('endpoint');

    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'vnitřní síť' => 'https://192.168.1.10/push',
    'cizí doména' => 'https://evil.example.com/fcm.googleapis.com',
    'podvržená doména' => 'https://fcm.googleapis.com.evil.example.com/send',
    'bez šifrování' => 'http://fcm.googleapis.com/fcm/send/abc',
    'jiný port' => 'https://fcm.googleapis.com:8443/fcm/send/abc',
]);

it('přijme push služby Apple, Mozilla a Microsoft', function (string $endpoint): void {
    $this->actingAs($this->user)
        ->post(route('account.push.store'), ['endpoint' => $endpoint, 'keys' => browserPushKeys()])
        ->assertSessionHasNoErrors();
})->with([
    'https://web.push.apple.com/QGuQyavXutnMH4',
    'https://updates.push.services.mozilla.com/wpush/v2/gAAAAA',
    'https://wns2-par02p.notify.windows.com/w/?token=BQYAAAB',
]);

it('stejný prohlížeč po přihlášení jiného účtu upozorňuje nový účet', function (): void {
    $other = User::factory()->create();
    PushSubscription::query()->create(['user_id' => $other->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'old', 'auth_token' => 'old', 'device' => 'Chrome']);

    $this->actingAs($this->user)->post(route('account.push.store'), ['endpoint' => PUSH_ENDPOINT, 'keys' => browserPushKeys()]);

    expect(PushSubscription::query()->sole()->user_id)->toBe($this->user->id);
});

it('vypne upozornění jen na vlastním zařízení', function (): void {
    $other = User::factory()->create();
    PushSubscription::query()->create(['user_id' => $other->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT.'-moje', 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);

    $this->actingAs($this->user)->delete(route('account.push.destroy'), ['endpoint' => PUSH_ENDPOINT])
        ->assertSessionHas('status', PushSubscriptionController::STATUS_DISABLED);
    $this->actingAs($this->user)->delete(route('account.push.destroy'), ['endpoint' => PUSH_ENDPOINT.'-moje']);

    expect(PushSubscription::query()->pluck('user_id')->all())->toBe([$other->id]);
});

it('odhlášení smaže odběr zařízení, ze kterého se uživatel odhlásil, jiná nechá (R67)', function (): void {
    $other = User::factory()->create();
    PushSubscription::query()->create(['user_id' => $other->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT.'-telefon', 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT.'-pocitac', 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Edge']);

    // Cizí adresu odhlášení nesmaže
    $this->actingAs($this->user)->post(route('logout'), ['push_endpoint' => PUSH_ENDPOINT]);
    $this->actingAs($this->user)->post(route('logout'), ['push_endpoint' => PUSH_ENDPOINT.'-telefon'])->assertRedirect();

    expect(PushSubscription::query()->orderBy('id')->pluck('endpoint')->all())->toBe([PUSH_ENDPOINT, PUSH_ENDPOINT.'-pocitac'])
        ->and(auth()->check())->toBeFalse();
});

it('zkušební upozornění pošle jen na vlastní zařízení', function (): void {
    $sender = fakePushSender();
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);

    $this->actingAs($this->user)->post(route('account.push.test'), ['endpoint' => PUSH_ENDPOINT])
        ->assertSessionHas('status', PushSubscriptionController::STATUS_TEST_SENT);
    $this->actingAs($this->user)->post(route('account.push.test'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/cizi'])
        ->assertSessionHas('status', PushSubscriptionController::STATUS_TEST_FAILED);

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['message']->title)->toBe('Upozornění fungují');
});

it('Můj účet dostane klíč a zařízení, bez klíčů VAPID nic', function (): void {
    PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome · Android']);

    $this->actingAs($this->user)->get(route('account'))->assertInertia(fn (Assert $page) => $page
        ->where('push.publicKey', config('letaky.push.vapid.public_key'))
        ->where('push.devices', [['endpoint' => PUSH_ENDPOINT, 'device' => 'Chrome · Android']]));

    config(['letaky.push.vapid.public_key' => '']);
    $this->actingAs($this->user)->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('push', null));
});

describe('upozornění na nové akce', function (): void {
    beforeEach(function (): void {
        FollowedChain::query()->create(['user_id' => $this->user->id, 'chain' => Chain::Kaufland, 'include_online_only' => true]);
        WatchItem::factory()->for($this->user)->create(['name' => 'Máslo', 'keywords' => 'máslo']);
        $this->user->forceFill(['push_sent_at' => now()->subHours(2), 'notified_at' => now()->subHours(2)])->save();
        PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    });

    it('pošle upozornění s akcí, cenou a obchodem, odkazem na záznam centra a číslem na ikonu', function (): void {
        $sender = fakePushSender();
        pushImportedOffer(['name' => 'Máslo 250 g', 'price' => 3990]);

        expect(recordAndPush())->toBe(1);

        $message = $sender->sent[0]['message'];
        expect($message->title)->toBe('Máslo je v akci')
            ->and($message->body)->toBe("Máslo 250 g — 39,90\u{00A0}Kč, Kaufland")
            ->and($message->url)->toBe('/upozorneni/'.$this->user->notifications()->sole()->id)
            ->and($message->badge)->toBe(1)
            ->and($this->user->fresh()?->push_sent_at?->toDateTimeString())->toBe('2026-10-02 06:30:00');
    });

    it('víc akcí shrne počtem a vypíše jen první', function (): void {
        $sender = fakePushSender();
        foreach (['Máslo A', 'Máslo B', 'Máslo C', 'Máslo D', 'Máslo E'] as $name) {
            pushImportedOffer(['name' => $name]);
        }

        recordAndPush();

        $message = $sender->sent[0]['message'];
        expect($message->title)->toBe('5 nových akcí na hlídané zboží')
            ->and(explode("\n", $message->body))->toHaveCount(4)
            ->and($message->body)->toEndWith('a 2 další…');
    });

    it('pošle nejvýš jednou za interval a jen akce, které přibyly', function (): void {
        $sender = fakePushSender();
        pushImportedOffer(['name' => 'Máslo staré']);
        recordAndPush();

        $this->travelTo('2026-10-02 07:00:00');
        pushImportedOffer(['name' => 'Máslo nové']);
        expect(recordAndPush())->toBe(0);

        $this->travelTo('2026-10-02 07:31:00');
        expect(recordAndPush())->toBe(1)
            ->and($sender->sent)->toHaveCount(2)
            ->and($sender->sent[1]['message']->body)->toStartWith('Máslo nové');
    });

    it('neověřenému účtu nic nepošle (R67)', function (): void {
        $sender = fakePushSender();
        $this->user->forceFill(['email_verified_at' => null])->save();
        pushImportedOffer(['name' => 'Máslo 250 g']);

        expect(recordAndPush())->toBe(0)
            ->and($sender->sent)->toBe([]);
    });

    it('bez klíčů VAPID nic nepošle', function (): void {
        $sender = fakePushSender();
        config(['letaky.push.vapid.private_key' => null]);
        pushImportedOffer(['name' => 'Máslo 250 g']);

        expect(recordAndPush())->toBe(0)
            ->and($sender->sent)->toBe([]);
    });

    it('zařízení, které push služba nezná, smaže', function (): void {
        fakePushSender([PUSH_ENDPOINT => PushDelivery::Expired]);
        pushImportedOffer(['name' => 'Máslo 250 g']);

        expect(recordAndPush())->toBe(0)
            ->and(PushSubscription::query()->count())->toBe(0);
    });

    it('cron souhrnů pošle i upozornění v telefonu', function (): void {
        fakePushSender();
        config(['letaky.cron.token' => 'tajny-token']);
        pushImportedOffer(['name' => 'Máslo 250 g']);

        $this->get(route('cron.send-digests', ['token' => 'tajny-token']))
            ->assertOk()
            ->assertSeeText('Souhrny — odesláno: 0')
            ->assertSeeText('Upozornění v telefonu — odesláno: 1');
    });
});

it('zprávu zašifruje a pošle push službě s podpisem VAPID', function (): void {
    $history = [];
    $handler = HandlerStack::create(new MockHandler([new Response(201), new Response(410)]));
    $handler->push(Middleware::history($history));
    $sender = new WebPushSender(app(Vapid::class), new Client(['handler' => $handler]));
    $keys = browserPushKeys();
    $subscription = PushSubscription::query()->create(['user_id' => $this->user->id, 'endpoint' => PUSH_ENDPOINT, 'public_key' => $keys['p256dh'], 'auth_token' => $keys['auth'], 'device' => 'Chrome']);
    $message = new PushMessage('Máslo je v akci', 'Máslo 250 g — 39,90 Kč', '/', 'new-offers', 1);

    expect($sender->send($subscription, $message))->toBe(PushDelivery::Sent)
        ->and($sender->send($subscription, $message))->toBe(PushDelivery::Expired);

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toBe(PUSH_ENDPOINT)
        ->and($request->getHeaderLine('Content-Encoding'))->toBe('aes128gcm')
        ->and($request->getHeaderLine('TTL'))->toBe((string) config('letaky.push.ttl_seconds'))
        ->and($request->getHeaderLine('Topic'))->toBe('new-offers')
        ->and($request->getHeaderLine('Authorization'))->toStartWith('vapid t=')
        ->and((string) $request->getBody())->not->toContain('Máslo');
});
