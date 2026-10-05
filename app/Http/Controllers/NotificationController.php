<?php

/**
 * Centrum upozornění (R74, /upozorneni): záznamy za posledních `letaky.notifications.retention_days`
 * dní, detail záznamu s akcemi (cíl klepnutí na upozornění v telefonu) a označení přečtených.
 * Zobrazení nic nemění — přečtení posílá stránka po načtení zvlášť (GET bez vedlejších účinků,
 * stránku si může načíst i service worker nebo obnovení po návratu do aplikace).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Matching\MyOffers;
use App\Domain\Notifications\NewOffersNotification;
use App\Domain\Notifications\NotificationPresenter;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPresenter;
use App\Enums\NotificationKind;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Seznam záznamů od nejnovějšího.
     */
    public function index(Request $request, NotificationPresenter $presenter): Response
    {
        $notifications = $this->user($request)->notifications()
            ->limit(config()->integer('letaky.notifications.max_listed'))
            ->get()
            ->map(fn (DatabaseNotification $notification): ?array => $presenter->summary($notification))
            ->filter()
            ->values();

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'readUrl' => route('notifications.read', absolute: false),
            'watchItemsUrl' => route('watch-items.index', absolute: false),
            'retentionDays' => config()->integer('letaky.notifications.retention_days'),
        ]);
    }

    /**
     * Detail záznamu: akce po hlídaných položkách, jak jsou teď; skončené označené.
     */
    public function show(
        Request $request,
        string $notification,
        NotificationPresenter $presenter,
        OfferPresenter $offerPresenter,
        MyOffers $myOffers,
        LocalCalendar $calendar,
    ): Response {
        $user = $this->user($request);
        /** @var DatabaseNotification $record */
        $record = $user->notifications()->findOrFail($notification);
        $summary = $presenter->summary($record);
        abort_if($summary === null || $record->type !== NotificationKind::NewOffers->value, 404);

        $offers = Offer::query()->with('stores')->findMany(NewOffersNotification::offerIds($record))->keyBy('id');
        $storeCodes = $user->selectedStoreCodes();
        $today = $calendar->today();

        $groups = array_map(fn (array $group): array => [
            'watchItem' => $group['watchItem'],
            'offers' => array_values(array_filter(array_map(function (int $id) use ($offers, $offerPresenter, $myOffers, $user, $storeCodes, $today): ?array {
                $offer = $offers->get($id);

                return $offer === null ? null : [
                    ...$offerPresenter->toPage($offer, $storeCodes),
                    'userPrice' => $myOffers->userPrice($user, $offer),
                    // Obchod akci stáhl (R16) nebo už skončila
                    'ended' => $offer->withdrawn_at !== null || $offer->valid_to->lessThan($today),
                ];
            }, $group['offerIds']))),
        ], NewOffersNotification::groups($record));

        return Inertia::render('NotificationDetail', [
            'notification' => $summary,
            'groups' => $groups,
            'indexUrl' => route('notifications.index', absolute: false),
            'readUrl' => route('notifications.read', absolute: false),
        ]);
    }

    /**
     * Označí vybrané záznamy uživatele jako přečtené (stránka je posílá po zobrazení).
     */
    public function markRead(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.config()->integer('letaky.notifications.max_listed')],
            'ids.*' => ['required', 'uuid'],
        ]);

        $this->user($request)->unreadNotifications()->whereIn('id', $validated['ids'])->update(['read_at' => now()]);

        return back(fallback: route('notifications.index'));
    }

    /**
     * Přihlášený uživatel (routy jsou za middleware auth).
     */
    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
