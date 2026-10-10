<?php

/**
 * Upozornění pro adminy (R125, R126): záznam v centru upozornění každému adminovi a hned i do
 * telefonu na jeho zařízení se zapnutými upozorněními — cron upozornění by je poslal až za
 * hodinu a přes noc vůbec. Klepnutí otevře záznam (označí se jako přečtený), z něj tlačítko
 * vede dál. Chyba push služby zápis ani ostatní adminy nezastaví — zapíše se do logu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Push\PushMessage;
use App\Domain\Push\PushSubscriptions;
use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

final readonly class AdminAlerts
{
    public function __construct(private PushSubscriptions $subscriptions) {}

    /**
     * Zapíše záznam všem adminům a pošle ho na jejich zařízení; vrátí počet adminů, kterým
     * záznam vznikl.
     *
     * @param  string|null  $url  Adresa v aplikaci pro tlačítko v detailu záznamu
     */
    public function send(NotificationKind $kind, string $title, string $body, ?string $url): int
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orderBy('id')
            ->with('pushSubscriptions')
            ->get();

        foreach ($admins as $admin) {
            $notification = new AdminAlertNotification($kind, $title, $body, $url);
            // ID předem — upozornění v telefonu na něj odkazuje
            $notification->id = (string) Str::uuid();
            $admin->notify($notification);
            $this->push($admin, $kind, $notification->id, $title, $body);
        }

        return $admins->count();
    }

    /**
     * Pošle záznam na zařízení admina; neověřenému účtu nic (jako SendPushNotifications).
     */
    private function push(User $admin, NotificationKind $kind, string $notificationId, string $title, string $body): void
    {
        if ($admin->email_verified_at === null || $admin->pushSubscriptions->isEmpty()) {
            return;
        }

        $message = new PushMessage(
            title: $title,
            body: Str::limit($body, config()->integer('letaky.notifications.announcement_excerpt')),
            url: route('notifications.show', $notificationId, absolute: false),
            tag: $kind->pushTag(),
            badge: $admin->unreadNotifications()->count(),
        );
        foreach ($admin->pushSubscriptions as $subscription) {
            try {
                $this->subscriptions->deliver($subscription, $message);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
