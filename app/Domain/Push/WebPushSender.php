<?php

/**
 * Skutečné odeslání upozornění v telefonu (R66) přes knihovnu minishlink/web-push: zašifruje
 * zprávu klíči prohlížeče (RFC 8291), podepíše požadavek klíčem VAPID a pošle ho push službě
 * prohlížeče. Na hostingu bez fronty (R20) synchronně, jedno zařízení po druhém.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

final class WebPushSender implements PushSender
{
    /** Šifrování obsahu, které umí všechny současné prohlížeče (RFC 8188). */
    private const CONTENT_ENCODING = 'aes128gcm';

    /** Push služba doručí upozornění hned, ale nebudí kvůli němu telefon v úsporném režimu. */
    private const URGENCY = 'normal';

    /** Klient knihovny — vytvoří se až při prvním odeslání (kontroluje klíče a rozšíření PHP). */
    private ?WebPush $webPush = null;

    /**
     * @param  ClientInterface|null  $http  HTTP klient (v testech s podvrženými odpověďmi); null = Guzzle s timeoutem z konfigurace
     */
    public function __construct(
        private readonly Vapid $vapid,
        private readonly ?ClientInterface $http = null,
    ) {}

    /**
     * Pošle zprávu na jedno zařízení. Chyba sítě nebo push služby se zapíše do logu.
     */
    public function send(PushSubscription $subscription, PushMessage $message): PushDelivery
    {
        $report = $this->client()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => self::CONTENT_ENCODING,
            ]),
            $message->toJson(),
            [
                'TTL' => config()->integer('letaky.push.ttl_seconds'),
                'urgency' => self::URGENCY,
                // Nedoručené upozornění se stejným tématem push služba nahradí novějším
                'topic' => $message->tag,
            ],
        );

        if ($report->isSuccess()) {
            return PushDelivery::Sent;
        }
        if ($report->isSubscriptionExpired()) {
            return PushDelivery::Expired;
        }

        Log::warning('Upozornění v telefonu se nepodařilo odeslat.', [
            'subscription' => $subscription->id,
            'reason' => $report->getReason(),
        ]);

        return PushDelivery::Failed;
    }

    /**
     * Klient knihovny s klíči VAPID.
     */
    private function client(): WebPush
    {
        return $this->webPush ??= new WebPush(
            ['VAPID' => [
                'subject' => $this->vapid->subject(),
                'publicKey' => $this->vapid->publicKey(),
                'privateKey' => $this->vapid->privateKey(),
            ]],
            [],
            $this->http ?? new Client(['timeout' => config()->integer('letaky.push.timeout_seconds')]),
        );
    }
}
