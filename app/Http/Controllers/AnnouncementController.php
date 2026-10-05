<?php

/**
 * Zprávy od nás pro admina (R74, etapa 11d, /zpravy): formulář s počty příjemců podle druhu
 * zprávy a přehled odeslaných zpráv. Odeslání zapíše zprávu příjemcům do centra upozornění,
 * do telefonu ji pošle cron souhrnů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Notifications\Actions\SendAnnouncement;
use App\Enums\AnnouncementCategory;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /** Kód stavu po odeslání zprávy — toast (R47, lang: ui.toast.messages). */
    public const STATUS_SENT = 'announcement-sent';

    /** Kolik posledních odeslaných zpráv stránka ukáže. */
    private const LISTED = 20;

    /**
     * Formulář a přehled odeslaných zpráv.
     */
    public function index(SendAnnouncement $send): Response
    {
        return Inertia::render('Announcements', [
            'storeUrl' => route('announcements.store', absolute: false),
            // Kolik uživatelů zprávu dostane podle druhu — formulář to ukáže před odesláním
            'recipients' => array_combine(
                array_map(fn (AnnouncementCategory $category): string => $category->value, AnnouncementCategory::cases()),
                array_map($send->recipientCount(...), AnnouncementCategory::cases()),
            ),
            'limits' => [
                'title' => config()->integer('letaky.notifications.announcement.title_max_length'),
                'body' => config()->integer('letaky.notifications.announcement.body_max_length'),
            ],
            'announcements' => Announcement::query()->latest('id')->limit(self::LISTED)->get()
                ->map(fn (Announcement $announcement): array => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'category' => $announcement->category->value,
                    'push' => $announcement->push,
                    'recipients' => $announcement->recipients,
                    'createdAt' => $announcement->created_at->toIso8601String(),
                ])->all(),
        ]);
    }

    /**
     * Odešle zprávu do centra upozornění příjemcům.
     */
    public function store(AnnouncementRequest $request, SendAnnouncement $send): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $send($user, $request->announcement());

        return to_route('announcements.index')->with('status', self::STATUS_SENT);
    }
}
