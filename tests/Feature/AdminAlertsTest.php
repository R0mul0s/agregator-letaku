<?php

/**
 * Upozornění adminům (R125, R126): hlášení chyby a výpadek stahování nebo úloh cronu jako záznam
 * v centru upozornění a hned i do telefonu; výpadek se ohlásí jen při změně a jednou při návratu.
 * Cron upozornění tyhle záznamy do telefonu znovu neposílá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use App\Domain\Notifications\Actions\RecordHealthAlerts;
use App\Domain\Notifications\AdminAlerts;
use App\Domain\Push\Actions\SendPushNotifications;
use App\Domain\Push\PushDelivery;
use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSender;
use App\Enums\Chain;
use App\Enums\CronTask;
use App\Enums\NotificationKind;
use App\Enums\ScrapeStatus;
use App\Models\PushSubscription;
use App\Models\ScrapeRun;
use App\Models\Store;
use App\Models\User;
use App\Support\TaskHeartbeats;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Minishlink\WebPush\VAPID as VapidKeys;

beforeEach(function (): void {
    $keys = VapidKeys::createVapidKeys();
    config(['letaky.push.vapid.public_key' => $keys['publicKey'], 'letaky.push.vapid.private_key' => $keys['privateKey']]);
    $this->travelTo('2026-10-10 10:00:00');
    $this->admin = User::factory()->create(['is_admin' => true]);
    PushSubscription::query()->create(['user_id' => $this->admin->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/admin', 'public_key' => 'k', 'auth_token' => 'a', 'device' => 'Chrome']);
    $this->sender = new class implements PushSender
    {
        /** @var list<PushMessage> */
        public array $sent = [];

        /**
         * Zapamatuje si zprávu.
         */
        public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
        {
            $this->sent[] = $message;

            return PushDelivery::Sent;
        }
    };
    app()->instance(PushSender::class, $this->sender);
});

/**
 * Všechno v pořádku: čerstvé stažení každého obchodu, seznamy prodejen a běh každé úlohy.
 */
function allHealthy(): void
{
    foreach (Chain::cases() as $chain) {
        ScrapeRun::query()->create(['chain' => $chain, 'status' => ScrapeStatus::Succeeded, 'started_at' => CarbonImmutable::now()->subHour(), 'finished_at' => CarbonImmutable::now()->subHour()]);
    }
    Store::query()->create(['chain' => Chain::Kaufland, 'code' => '1100', 'name' => 'Kaufland Praha', 'city' => 'Praha', 'offer_keys_fetched_at' => CarbonImmutable::now()->subHour()]);
    foreach (CronTask::cases() as $task) {
        TaskHeartbeats::succeeded($task);
    }
}

/**
 * Nadpisy záznamů výpadku v centru upozornění admina, od nejstaršího.
 *
 * @return list<string>
 */
function systemAlertTitles(User $admin): array
{
    // Relace notifications() řadí od nejnovějšího — pořadí se přepíše
    return $admin->notifications()->where('type', NotificationKind::SystemAlert->value)->reorder()->oldest()->get()
        ->map(fn ($notification): string => $notification->data['title'])->all();
}

it('výpadek ohlásí jednou, trvající neopakuje, nový přidá a návrat ohlásí jednou', function (): void {
    allHealthy();
    $alerts = app(RecordHealthAlerts::class);

    expect($alerts())->toBe(0);

    ScrapeRun::query()->where('chain', Chain::Tesco)->update(['finished_at' => CarbonImmutable::now()->subDays(2)]);
    expect($alerts())->toBe(1)
        ->and($alerts())->toBe(0)
        ->and(systemAlertTitles($this->admin))->toBe(['Výpadek: 1 věc nefunguje']);

    TaskHeartbeats::failed(CronTask::Digest);
    $this->travel(10)->hours();
    ScrapeRun::query()->where('chain', '!=', Chain::Tesco)->update(['finished_at' => CarbonImmutable::now()->subHour()]);
    Store::query()->update(['offer_keys_fetched_at' => CarbonImmutable::now()->subHour()]);
    foreach (CronTask::cases() as $task) {
        if ($task !== CronTask::Digest) {
            TaskHeartbeats::succeeded($task);
        }
    }
    expect($alerts())->toBe(1);
    $latest = $this->admin->notifications()->latest()->first();
    expect($latest->data['title'])->toBe('Výpadek: 2 věci nefungují')
        ->and($latest->data['body'])->toContain('Tesco — VÝPADEK')->toContain('E-mailové souhrny — VÝPADEK');

    // O hodinu později — záznamy se řadí podle času vzniku
    $this->travel(1)->hours();
    ScrapeRun::query()->update(['finished_at' => CarbonImmutable::now()->subHour()]);
    TaskHeartbeats::succeeded(CronTask::Digest);
    expect($alerts())->toBe(1)
        ->and($alerts())->toBe(0)
        ->and(systemAlertTitles($this->admin))->toBe(['Výpadek: 1 věc nefunguje', 'Výpadek: 2 věci nefungují', 'Všechno zase běží'])
        ->and(count($this->sender->sent))->toBe(3)
        ->and($this->sender->sent[0]->url)->toStartWith('/upozorneni/');
});

it('upozornění na výpadek nedostane, kdo není admin', function (): void {
    $user = User::factory()->create();

    app(RecordHealthAlerts::class)();

    expect(systemAlertTitles($this->admin))->toHaveCount(1)
        ->and($user->notifications()->count())->toBe(0);
});

it('záznam pro adminy ukáže centrum upozornění s textem a odkazem a cron ho do telefonu znovu nepošle', function (): void {
    app(AdminAlerts::class)->send(NotificationKind::OfferReport, 'Nové hlášení chyby v akci', 'Cena je jiná — Máslo (Lidl)', '/hlaseni');
    expect($this->sender->sent)->toHaveCount(1);
    $id = $this->admin->notifications()->sole()->id;

    $this->actingAs($this->admin)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->where('notifications.0.kind', 'offer_report')
        ->where('notifications.0.title', 'Nové hlášení chyby v akci')
        ->where('notifications.0.text', 'Cena je jiná — Máslo (Lidl)'));
    $this->get(route('notifications.show', $id))->assertInertia(fn (Assert $page) => $page
        ->where('announcement.body', 'Cena je jiná — Máslo (Lidl)')
        ->where('announcement.url', '/hlaseni'));

    app(SendPushNotifications::class)();

    expect($this->sender->sent)->toHaveCount(1);
});
