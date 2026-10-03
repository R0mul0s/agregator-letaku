<?php

/**
 * Odhlášení z e-mailů jedním klepnutím bez přihlášení (R51) — podepsaný odkaz v patičce
 * e-mailu a hlavička List-Unsubscribe (RFC 8058). GET jen ukáže stránku s tlačítkem:
 * odkazy v e-mailech otevírají i antivirové skenery a odhlásit nesmí. Odhlásí až POST —
 * z tlačítka, nebo přímo od poštovního klienta (proto bez CSRF tokenu, chrání ho podpis).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\MailingSubscriptions;
use App\Enums\MailingList;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnsubscribeController extends Controller
{
    /** Kód stavu po odhlášení — toast (R47, lang: ui.toast.messages). */
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public function __construct(private readonly MailingSubscriptions $subscriptions) {}

    /**
     * Stránka s potvrzením odhlášení, nebo informací, že už je odhlášený.
     */
    public function show(Request $request, User $user, MailingList $list): Response
    {
        return Inertia::render('Unsubscribe', [
            'list' => $list->label(),
            'email' => $user->email,
            'subscribed' => $this->subscriptions->isSubscribed($user, $list),
            'submitUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * Odhlásí a vrátí se na stránku odhlášení (poštovnímu klientovi stačí úspěšná odpověď).
     */
    public function store(Request $request, User $user, MailingList $list): RedirectResponse
    {
        $this->subscriptions->unsubscribe($user, $list);

        return redirect()->to($request->fullUrl())->with('status', self::STATUS_UNSUBSCRIBED);
    }
}
