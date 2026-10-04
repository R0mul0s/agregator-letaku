<?php

/**
 * Odhlášení zruší odběr upozornění v telefonu na zařízení, ze kterého se uživatel odhlásil
 * (R67). Prohlížeč odběr zruší sám (UserMenu.vue) a adresu odběru pošle s odhlášením —
 * na sdíleném zařízení by jinak chodila upozornění odhlášeného uživatele dál a záznam
 * by zůstal, dokud push služba neodpoví, že odběr skončil.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Push\PushSubscriptions;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

final readonly class ForgetPushSubscriptionOnLogout
{
    /** Pole požadavku na odhlášení s adresou odběru tohoto prohlížeče. */
    public const ENDPOINT_FIELD = 'push_endpoint';

    public function __construct(
        private Request $request,
        private PushSubscriptions $subscriptions,
    ) {}

    /**
     * Smaže odběr s adresou z požadavku — jen odběr odhlašovaného uživatele.
     */
    public function handle(Logout $event): void
    {
        $endpoint = $this->request->input(self::ENDPOINT_FIELD);
        if (! $event->user instanceof User || ! is_string($endpoint) || $endpoint === '') {
            return;
        }

        $this->subscriptions->unsubscribe($event->user, $endpoint);
    }
}
