<?php

/**
 * Odeslání upozornění push službě prohlížeče (R66). Rozhraní kvůli testům — skutečné
 * odeslání (WebPushSender) jde na síť.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

use App\Models\PushSubscription;

interface PushSender
{
    /**
     * Zašifruje zprávu klíči zařízení a pošle ji jeho push službě.
     */
    public function send(PushSubscription $subscription, PushMessage $message): PushDelivery;
}
