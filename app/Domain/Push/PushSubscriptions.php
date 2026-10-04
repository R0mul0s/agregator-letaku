<?php

/**
 * Zařízení s upozorněním v telefonu (R66): přihlášení a odhlášení odběru, doručení zprávy.
 * Odběr patří prohlížeči — když se v něm přihlásí jiný účet a upozornění zapne, odběr
 * přejde na něj (adresa je jedinečná). Odběr, který push služba už nezná, se smaže.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

use App\Domain\Account\DeviceName;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;

final class PushSubscriptions
{
    /** Značka zkušebního upozornění — nenahradí upozornění na akce. */
    private const TEST_TAG = 'test';

    public function __construct(private readonly PushSender $sender) {}

    /**
     * Uloží odběr zařízení. První zařízení uživatele začne počítat nové akce od teď —
     * jinak by první upozornění vyjmenovalo všechno, co je v akci.
     */
    public function subscribe(User $user, string $endpoint, string $publicKey, string $authToken, ?string $userAgent): PushSubscription
    {
        if (! $user->pushSubscriptions()->exists()) {
            $user->forceFill(['push_sent_at' => CarbonImmutable::now()])->save();
        }

        $subscription = PushSubscription::query()->updateOrCreate(['endpoint' => $endpoint], [
            'user_id' => $user->id,
            'public_key' => $publicKey,
            'auth_token' => $authToken,
            'device' => DeviceName::fromUserAgent($userAgent),
        ]);
        // updateOrCreate nezmění updated_at, když se nic nezměnilo — pořadí podle posledního přihlášení
        $subscription->touch();

        // Nejvýš max_subscriptions_per_user zařízení — nejstarší odběry (dávno vyměněné telefony) pryč
        $user->pushSubscriptions()
            ->whereNotIn('id', $user->pushSubscriptions()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(config()->integer('letaky.push.max_subscriptions_per_user'))
                ->pluck('id'))
            ->delete();

        return $subscription;
    }

    /**
     * Zruší odběr zařízení uživatele (cizí adresu nesmaže).
     */
    public function unsubscribe(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }

    /**
     * Pošle zkušební upozornění na zařízení uživatele; false = zařízení nepatří uživateli
     * nebo upozornění nedošlo.
     */
    public function sendTest(User $user, string $endpoint): bool
    {
        $subscription = $user->pushSubscriptions()->where('endpoint', $endpoint)->first();

        return $subscription !== null && $this->deliver($subscription, new PushMessage(
            title: __('app.push.test_title'),
            body: __('app.push.test_body'),
            url: route('home', absolute: false),
            tag: self::TEST_TAG,
        ));
    }

    /**
     * Pošle zprávu na zařízení; odběr, který push služba už nezná, smaže.
     */
    public function deliver(PushSubscription $subscription, PushMessage $message): bool
    {
        $delivery = $this->sender->send($subscription, $message);
        if ($delivery === PushDelivery::Expired) {
            $subscription->delete();
        }

        return $delivery === PushDelivery::Sent;
    }
}
