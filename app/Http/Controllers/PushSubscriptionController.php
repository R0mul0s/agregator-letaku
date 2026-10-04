<?php

/**
 * Upozornění v telefonu (web push, R66) v Můj účet: zapnutí a vypnutí na tomto zařízení
 * a zkušební upozornění. Odběr vytvoří prohlížeč (resources/js/lib/push.js), server ho jen uloží.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Push\PushSubscriptions;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /** Kódy stavu pro toast (R47, lang: ui.toast.messages). */
    public const STATUS_ENABLED = 'push-enabled';

    public const STATUS_DISABLED = 'push-disabled';

    public const STATUS_TEST_SENT = 'push-test-sent';

    public const STATUS_TEST_FAILED = 'push-test-failed';

    /** Adresa odběru u vypnutí a zkoušky — jen k vyhledání vlastního zařízení. */
    private const ENDPOINT_RULES = ['required', 'string', 'max:500'];

    /**
     * Zapne upozornění na zařízení, ze kterého přišel požadavek.
     */
    public function store(PushSubscriptionRequest $request, PushSubscriptions $subscriptions): RedirectResponse
    {
        $subscriptions->subscribe($this->user($request), $request->endpoint(), $request->publicKey(), $request->authToken(), $request->userAgent());

        return back()->with('status', self::STATUS_ENABLED);
    }

    /**
     * Vypne upozornění na zařízení (prohlížeč odběr zruší sám).
     */
    public function destroy(Request $request, PushSubscriptions $subscriptions): RedirectResponse
    {
        $request->validate(['endpoint' => self::ENDPOINT_RULES]);
        $subscriptions->unsubscribe($this->user($request), $request->string('endpoint')->toString());

        return back()->with('status', self::STATUS_DISABLED);
    }

    /**
     * Pošle zkušební upozornění na zařízení — ověří, že je telefon opravdu ukáže.
     */
    public function test(Request $request, PushSubscriptions $subscriptions): RedirectResponse
    {
        $request->validate(['endpoint' => self::ENDPOINT_RULES]);
        $sent = $subscriptions->sendTest($this->user($request), $request->string('endpoint')->toString());

        return back()->with('status', $sent ? self::STATUS_TEST_SENT : self::STATUS_TEST_FAILED);
    }

    /**
     * Přihlášený uživatel (routy jsou za middlewarem auth).
     */
    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
